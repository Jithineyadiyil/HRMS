<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard data endpoints.
 * All model queries are individually try/caught so a single missing table
 * or column never crashes the whole response.
 */
class DashboardController extends Controller
{
    // ── Stats ─────────────────────────────────────────────────────────────

    public function stats(): JsonResponse
    {
        $today = now()->toDateString();
        $month = now()->month;
        $year  = now()->year;

        // ── Helpers ────────────────────────────────────────────────────
        $safe = fn (callable $fn, $default = 0) => rescue($fn, $default, false);

        // ── Employees ──────────────────────────────────────────────────
        $totalEmp     = $safe(fn () => DB::table('employees')->whereNull('deleted_at')->count());
        $activeEmp    = $safe(fn () => DB::table('employees')->whereNull('deleted_at')->where('status', 'active')->count());
        $probation    = $safe(fn () => DB::table('employees')->whereNull('deleted_at')->where('status', 'probation')->count());
        $onLeave      = $safe(fn () => DB::table('employees')->whereNull('deleted_at')->where('status', 'on_leave')->count());
        $newThisMonth = $safe(fn () => DB::table('employees')->whereNull('deleted_at')->whereMonth('hire_date', $month)->whereYear('hire_date', $year)->count());
        $terminated   = $safe(fn () => DB::table('employees')->whereNull('deleted_at')->whereMonth('termination_date', $month)->whereYear('termination_date', $year)->count());

        // ── Leave ──────────────────────────────────────────────────────
        $pendingLeave  = $safe(fn () => DB::table('leave_requests')->where('status', 'pending')->count());
        $approvedLeave = $safe(fn () => DB::table('leave_requests')->where('status', 'approved')->count());
        $rejectedLeave = $safe(fn () => DB::table('leave_requests')->where('status', 'rejected')->count());
        $onLeaveToday  = $safe(fn () => DB::table('leave_requests')
            ->where('status', 'approved')
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)->count());
        $approvedMonth = $safe(fn () => DB::table('leave_requests')
            ->where('status', 'approved')
            ->whereMonth('updated_at', $month)->count());
        $totalLeave    = $safe(fn () => DB::table('leave_requests')->count());

        // ── Attendance ─────────────────────────────────────────────────
        $presentToday = $safe(fn () => DB::table('attendance_logs')
            ->whereDate('date', $today)
            ->whereIn('status', ['present', 'late'])->count());
        $lateToday    = $safe(fn () => DB::table('attendance_logs')
            ->whereDate('date', $today)->where('status', 'late')->count());
        $absentToday  = $safe(fn () => DB::table('attendance_logs')
            ->whereDate('date', $today)->where('status', 'absent')->count());
        $attRate      = $activeEmp > 0 ? round(($presentToday / max($activeEmp, 1)) * 100, 1) : 0;

        // ── Payroll ────────────────────────────────────────────────────
        $payProcessed = $safe(fn () => DB::table('payrolls')->where('status', 'approved')->count());
        $payPending   = $safe(fn () => DB::table('payrolls')->whereIn('status', ['pending_approval', 'draft'])->count());
        $payErrors    = $safe(fn () => DB::table('payrolls')->where('status', 'rejected')->count());
        $payOnHold    = $safe(fn () => DB::table('payrolls')->where('status', 'on_hold')->count());
        $payDue       = $safe(fn () => DB::table('payrolls')->whereMonth('created_at', $month)->count());
        $payTotal     = $safe(fn () => DB::table('payrolls')->count());

        // ── Recruitment ────────────────────────────────────────────────
        $openJobs        = $safe(fn () => DB::table('job_postings')->where('status', 'open')->count());
        $totalApplicants = $safe(fn () => DB::table('job_applications')->count());
        $newApplicants   = $safe(fn () => DB::table('job_applications')->where('created_at', '>=', now()->subDays(7))->count());
        $offersSent      = $safe(fn () => DB::table('job_applications')->where('stage', 'offer')->count());
        $hiredThisMonth  = $safe(fn () => DB::table('job_applications')->where('stage', 'hired')->whereMonth('updated_at', $month)->count());

        // ── Performance ────────────────────────────────────────────────
        $perfPending  = $safe(fn () => DB::table('performance_reviews')->where('status', 'pending')->count());
        $perfProgress = $safe(fn () => DB::table('performance_reviews')->where('status', 'in_progress')->count());
        $perfDone     = $safe(fn () => DB::table('performance_reviews')->where('status', 'completed')->count());
        $perfOverdue  = $safe(fn () => DB::table('performance_reviews')->where('status', 'pending')->where('review_date', '<', $today)->count());
        $perfTotal    = $safe(fn () => DB::table('performance_reviews')->count());
        $perfAvg      = $safe(fn () => DB::table('performance_reviews')->whereNotNull('final_rating')->avg('final_rating'), null);

        // ── Departments ────────────────────────────────────────────────
        $depts       = $safe(fn () => DB::table('departments')->whereNull('deleted_at')->get(), collect());
        $totalDepts  = is_object($depts) ? $depts->count() : 0;
        $withManager = is_object($depts) ? $depts->filter(fn ($d) => !empty($d->manager_id))->count() : 0;
        $vacantMgr   = $totalDepts - $withManager;

        // ── Loans ──────────────────────────────────────────────────────
        $loanPending  = $safe(fn () => DB::table('loans')->where('status', 'pending')->count());
        $loanActive   = $safe(fn () => DB::table('loans')->where('status', 'active')->count());
        $loanOverdue  = $safe(fn () => DB::table('loans')->where('status', 'overdue')->count());

        // ── Separations ────────────────────────────────────────────────
        $sepPending = $safe(fn () => DB::table('separations')->where('status', 'pending')->count());
        $sepActive  = $safe(fn () => DB::table('separations')->whereIn('status', ['approved', 'in_progress'])->count());

        // ── Requests ──────────────────────────────────────────────────
        $reqPending = $safe(fn () => DB::table('employee_requests')->where('status', 'pending')->count());
        $reqOpen    = $safe(fn () => DB::table('employee_requests')->whereIn('status', ['pending', 'manager_approved'])->count());

        return response()->json([
            'employees' => [
                'total'                 => $totalEmp,
                'active'                => $activeEmp,
                'probation'             => $probation,
                'on_leave'              => $onLeave,
                'new_this_month'        => $newThisMonth,
                'terminated_this_month' => $terminated,
                'contracts_expiring'    => 0,
            ],
            'leave' => [
                'pending'             => $pendingLeave,
                'approved'            => $approvedLeave,
                'rejected'            => $rejectedLeave,
                'on_leave_today'      => $onLeaveToday,
                'approved_this_month' => $approvedMonth,
                'total'               => $totalLeave,
            ],
            'attendance' => [
                'present_today' => $presentToday,
                'late_today'    => $lateToday,
                'absent_today'  => $absentToday,
                'total_active'  => $activeEmp,
                'rate'          => $attRate,
            ],
            'payroll' => [
                'processed'         => $payProcessed,
                'pending_approvals' => $payPending,
                'errors'            => $payErrors,
                'on_hold'           => $payOnHold,
                'due_this_month'    => $payDue,
                'total'             => $payTotal,
            ],
            'recruitment' => [
                'open_positions'  => $openJobs,
                'applicants'      => $totalApplicants,
                'new_this_week'   => $newApplicants,
                'interviews_today'=> 0,
                'offers_sent'     => $offersSent,
                'hired_this_month'=> $hiredThisMonth,
            ],
            'performance' => [
                'pending'    => $perfPending,
                'in_progress'=> $perfProgress,
                'completed'  => $perfDone,
                'overdue'    => $perfOverdue,
                'total'      => $perfTotal,
                'avg_score'  => $perfAvg ? round((float) $perfAvg, 1) : '—',
            ],
            'departments' => [
                'total'      => $totalDepts,
                'teams'      => $totalDepts,
                'managers'   => $withManager,
                'vacant_mgr' => $vacantMgr,
            ],
            'loans' => [
                'pending' => $loanPending,
                'active'  => $loanActive,
                'overdue' => $loanOverdue,
            ],
            'separations' => [
                'pending' => $sepPending,
                'active'  => $sepActive,
            ],
            'requests' => [
                'pending' => $reqPending,
                'open'    => $reqOpen,
            ],
        ]);
    }

    // ── Charts ────────────────────────────────────────────────────────────

    public function charts(): JsonResponse
    {
        $safe = fn (callable $fn, $default = []) => rescue($fn, $default, false);

        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i));

        $hireTrend = $safe(fn () => $months->map(fn ($m) => [
            'month' => $m->format('M'),
            'count' => DB::table('employees')
                ->whereNull('deleted_at')
                ->whereYear('hire_date', $m->year)
                ->whereMonth('hire_date', $m->month)->count(),
        ]));

        $exitTrend = $safe(fn () => $months->map(fn ($m) => [
            'month' => $m->format('M'),
            'count' => DB::table('employees')
                ->whereNull('deleted_at')
                ->whereYear('termination_date', $m->year)
                ->whereMonth('termination_date', $m->month)->count(),
        ]));

        $payrollTrend = $safe(fn () => $months->map(fn ($m) => [
            'month' => $m->format('M'),
            'total' => (int) (DB::table('payrolls')
                ->whereYear('created_at', $m->year)
                ->whereMonth('created_at', $m->month)
                ->where('status', 'approved')
                ->sum('total_net') ?? 0),
        ]));

        $deptDist = $safe(fn () => DB::table('departments')
            ->whereNull('deleted_at')
            ->join('employees', 'departments.id', '=', 'employees.department_id')
            ->whereNull('employees.deleted_at')
            ->where('employees.status', 'active')
            ->selectRaw('departments.name, COUNT(employees.id) as count')
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc('count')
            ->limit(8)
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'count' => $r->count]));

        $leaveByType = $safe(fn () => DB::table('leave_requests')
            ->join('leave_types', 'leave_requests.leave_type_id', '=', 'leave_types.id')
            ->where('leave_requests.status', 'approved')
            ->whereYear('leave_requests.created_at', now()->year)
            ->selectRaw('leave_types.name as leave_type, COUNT(*) as count')
            ->groupBy('leave_types.id', 'leave_types.name')
            ->get()
            ->map(fn ($r) => ['leave_type' => $r->leave_type, 'count' => $r->count]));

        $perfRatings = $safe(fn () => DB::table('performance_reviews')
            ->whereNotNull('final_rating')
            ->selectRaw("
                CASE
                    WHEN final_rating >= 4.5 THEN 'Excellent'
                    WHEN final_rating >= 3.5 THEN 'Good'
                    WHEN final_rating >= 2.5 THEN 'Average'
                    ELSE 'Needs Work'
                END as rating,
                COUNT(*) as count
            ")
            ->groupBy('rating')
            ->get()
            ->map(fn ($r) => ['rating' => $r->rating, 'count' => $r->count]));

        $attTrend = $safe(fn () => collect(range(6, 0))->map(fn ($i) => now()->subDays($i))->map(fn ($day) => [
            'day'     => $day->format('D'),
            'date'    => $day->toDateString(),
            'present' => DB::table('attendance_logs')->whereDate('date', $day)->whereIn('status', ['present', 'late'])->count(),
            'absent'  => DB::table('attendance_logs')->whereDate('date', $day)->where('status', 'absent')->count(),
        ]));

        return response()->json([
            'hire_trend'          => $hireTrend,
            'exit_trend'          => $exitTrend,
            'payroll_trend'       => $payrollTrend,
            'dept_distribution'   => $deptDist,
            'leave_by_type'       => $leaveByType,
            'performance_ratings' => $perfRatings,
            'attendance_trend'    => $attTrend,
        ]);
    }

    // ── Recent activities ─────────────────────────────────────────────────

    public function recentActivities(): JsonResponse
    {
        $safe = fn (callable $fn) => rescue($fn, [], false);

        $activities = $safe(function () {
            // Try Spatie activitylog first
            if (class_exists(\Spatie\Activitylog\Models\Activity::class)) {
                return \Spatie\Activitylog\Models\Activity::with('causer')
                    ->latest()
                    ->limit(20)
                    ->get()
                    ->map(fn ($a) => [
                        'id'         => $a->id,
                        'action'     => $a->event ?? $a->description,
                        'module'     => class_basename($a->subject_type ?? ''),
                        'created_at' => $a->created_at,
                        'user'       => $a->causer ? ['name' => $a->causer->name] : ['name' => 'System'],
                    ]);
            }
            return [];
        });

        return response()->json($activities);
    }
}
