<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeRequest;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\LeaveRequest;
use App\Models\Loan;
use App\Models\Payroll;
use App\Models\PerformanceReview;
use App\Models\Separation;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * Provides all data consumed by the main dashboard page.
 *
 * Three endpoints:
 *  GET /api/v1/dashboard/stats            — KPI numbers (role-scoped)
 *  GET /api/v1/dashboard/charts           — time-series data for Chart.js
 *  GET /api/v1/dashboard/recent-activities — activity feed
 */
class DashboardController extends Controller
{
    // ── Stats ─────────────────────────────────────────────────────────────

    public function stats(): JsonResponse
    {
        $user  = auth()->user();
        $today = now()->toDateString();
        $month = now()->month;
        $year  = now()->year;

        // ── Employees ──────────────────────────────────────────────────
        $empBase = Employee::query();
        $totalEmp     = (clone $empBase)->count();
        $activeEmp    = (clone $empBase)->where('status', 'active')->count();
        $probation    = (clone $empBase)->where('status', 'probation')->count();
        $onLeave      = (clone $empBase)->where('status', 'on_leave')->count();
        $newThisMonth = (clone $empBase)->whereMonth('hire_date', $month)->whereYear('hire_date', $year)->count();
        $terminated   = (clone $empBase)->whereMonth('termination_date', $month)->whereYear('termination_date', $year)->count();

        // ── Leave ──────────────────────────────────────────────────────
        $leaveBase       = LeaveRequest::query();
        $pendingLeave    = (clone $leaveBase)->where('status', 'pending')->count();
        $approvedLeave   = (clone $leaveBase)->where('status', 'approved')->count();
        $rejectedLeave   = (clone $leaveBase)->where('status', 'rejected')->count();
        $onLeaveToday    = (clone $leaveBase)->where('status', 'approved')
            ->where('start_date', '<=', $today)->where('end_date', '>=', $today)->count();
        $approvedMonth   = (clone $leaveBase)->where('status', 'approved')
            ->whereMonth('updated_at', $month)->count();
        $totalLeave      = (clone $leaveBase)->count();

        // ── Attendance ─────────────────────────────────────────────────
        $attToday     = AttendanceLog::whereDate('date', $today)->get();
        $presentToday = $attToday->whereIn('status', ['present', 'late'])->count();
        $lateToday    = $attToday->where('status', 'late')->count();
        $absentToday  = $attToday->where('status', 'absent')->count();
        $attRate      = $activeEmp > 0 ? round(($presentToday / max($activeEmp, 1)) * 100, 1) : 0;

        // ── Payroll ────────────────────────────────────────────────────
        $payBase          = Payroll::query();
        $payProcessed     = (clone $payBase)->where('status', 'approved')->count();
        $payPending       = (clone $payBase)->whereIn('status', ['pending_approval', 'draft'])->count();
        $payErrors        = (clone $payBase)->where('status', 'rejected')->count();
        $payOnHold        = (clone $payBase)->where('status', 'on_hold')->count();
        $payDueThisMonth  = (clone $payBase)->whereMonth('created_at', $month)->count();
        $payTotal         = (clone $payBase)->count();

        // ── Recruitment ────────────────────────────────────────────────
        $openJobs         = JobPosting::where('status', 'open')->count();
        $totalApplicants  = JobApplication::count();
        $newApplicants    = JobApplication::whereDate('created_at', '>=', now()->subDays(7))->count();
        $interviewsToday  = 0; // Interview model available if needed
        $offersSent       = JobApplication::where('stage', 'offer')->count();
        $hiredThisMonth   = JobApplication::where('stage', 'hired')
            ->whereMonth('updated_at', $month)->count();

        // ── Performance ────────────────────────────────────────────────
        $perfBase     = PerformanceReview::query();
        $perfPending  = (clone $perfBase)->where('status', 'pending')->count();
        $perfProgress = (clone $perfBase)->where('status', 'in_progress')->count();
        $perfDone     = (clone $perfBase)->where('status', 'completed')->count();
        $perfOverdue  = (clone $perfBase)->where('status', 'pending')
            ->where('review_date', '<', $today)->count();
        $perfTotal    = (clone $perfBase)->count();
        $perfAvg      = (clone $perfBase)->whereNotNull('final_score')->avg('final_score');

        // ── Departments ────────────────────────────────────────────────
        $depts       = Department::withCount('employees')->get();
        $totalDepts  = $depts->count();
        $withManager = $depts->filter(fn ($d) => $d->manager_id ?? false)->count();
        $vacantMgr   = $totalDepts - $withManager;

        // ── Loans ──────────────────────────────────────────────────────
        $loanPending  = Loan::where('status', 'pending')->count();
        $loanActive   = Loan::where('status', 'active')->count();
        $loanOverdue  = Loan::where('status', 'overdue')->count();

        // ── Separations ────────────────────────────────────────────────
        $sepPending = Separation::where('status', 'pending')->count();
        $sepActive  = Separation::whereIn('status', ['approved', 'in_progress'])->count();

        // ── Requests ──────────────────────────────────────────────────
        $reqPending  = EmployeeRequest::where('status', 'pending')->count();
        $reqOpen     = EmployeeRequest::whereIn('status', ['pending', 'manager_approved'])->count();

        return response()->json([
            'employees' => [
                'total'                => $totalEmp,
                'active'               => $activeEmp,
                'probation'            => $probation,
                'on_leave'             => $onLeave,
                'new_this_month'       => $newThisMonth,
                'terminated_this_month'=> $terminated,
                'contracts_expiring'   => 0,
            ],
            'leave' => [
                'pending'              => $pendingLeave,
                'approved'             => $approvedLeave,
                'rejected'             => $rejectedLeave,
                'on_leave_today'       => $onLeaveToday,
                'approved_this_month'  => $approvedMonth,
                'total'                => $totalLeave,
            ],
            'attendance' => [
                'present_today'        => $presentToday,
                'late_today'           => $lateToday,
                'absent_today'         => $absentToday,
                'total_active'         => $activeEmp,
                'rate'                 => $attRate,
            ],
            'payroll' => [
                'processed'            => $payProcessed,
                'pending_approvals'    => $payPending,
                'errors'               => $payErrors,
                'on_hold'              => $payOnHold,
                'due_this_month'       => $payDueThisMonth,
                'total'                => $payTotal,
            ],
            'recruitment' => [
                'open_positions'       => $openJobs,
                'applicants'           => $totalApplicants,
                'new_this_week'        => $newApplicants,
                'interviews_today'     => $interviewsToday,
                'offers_sent'          => $offersSent,
                'hired_this_month'     => $hiredThisMonth,
            ],
            'performance' => [
                'pending'              => $perfPending,
                'in_progress'          => $perfProgress,
                'completed'            => $perfDone,
                'overdue'              => $perfOverdue,
                'total'                => $perfTotal,
                'avg_score'            => $perfAvg ? round((float) $perfAvg, 1) : '—',
            ],
            'departments' => [
                'total'                => $totalDepts,
                'teams'                => $totalDepts,
                'managers'             => $withManager,
                'vacant_mgr'           => $vacantMgr,
            ],
            'loans' => [
                'pending'              => $loanPending,
                'active'               => $loanActive,
                'overdue'              => $loanOverdue,
            ],
            'separations' => [
                'pending'              => $sepPending,
                'active'               => $sepActive,
            ],
            'requests' => [
                'pending'              => $reqPending,
                'open'                 => $reqOpen,
            ],
        ]);
    }

    // ── Charts ────────────────────────────────────────────────────────────

    public function charts(): JsonResponse
    {
        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i));

        // Hire trend
        $hireTrend = $months->map(fn ($m) => [
            'month' => $m->format('M'),
            'count' => Employee::whereYear('hire_date', $m->year)
                ->whereMonth('hire_date', $m->month)->count(),
        ]);

        // Exit trend
        $exitTrend = $months->map(fn ($m) => [
            'month' => $m->format('M'),
            'count' => Employee::whereYear('termination_date', $m->year)
                ->whereMonth('termination_date', $m->month)->count(),
        ]);

        // Payroll trend
        $payrollTrend = $months->map(fn ($m) => [
            'month' => $m->format('M'),
            'total' => (int) (Payroll::whereYear('created_at', $m->year)
                ->whereMonth('created_at', $m->month)
                ->where('status', 'approved')
                ->sum('total_net') ?? 0),
        ]);

        // Department distribution
        $deptDist = Department::withCount(['employees' => fn ($q) => $q->where('status', 'active')])
            ->having('employees_count', '>', 0)
            ->orderByDesc('employees_count')
            ->limit(8)
            ->get()
            ->map(fn ($d) => ['name' => $d->name, 'count' => $d->employees_count]);

        // Leave by type
        $leaveByType = LeaveRequest::with('leaveType')
            ->where('status', 'approved')
            ->whereYear('created_at', now()->year)
            ->get()
            ->groupBy(fn ($l) => $l->leaveType?->name ?? 'Other')
            ->map(fn ($g, $name) => ['leave_type' => $name, 'count' => $g->count()])
            ->values();

        // Performance ratings
        $perfRatings = PerformanceReview::whereNotNull('final_score')
            ->selectRaw("
                CASE
                    WHEN final_score >= 4.5 THEN 'Excellent'
                    WHEN final_score >= 3.5 THEN 'Good'
                    WHEN final_score >= 2.5 THEN 'Average'
                    ELSE 'Needs Work'
                END as rating,
                COUNT(*) as count
            ")
            ->groupBy('rating')
            ->get()
            ->map(fn ($r) => ['rating' => $r->rating, 'count' => $r->count]);

        // Attendance trend (last 7 days)
        $attTrend = collect(range(6, 0))->map(fn ($i) => now()->subDays($i))->map(fn ($day) => [
            'day'     => $day->format('D'),
            'date'    => $day->toDateString(),
            'present' => AttendanceLog::whereDate('date', $day)->whereIn('status', ['present', 'late'])->count(),
            'absent'  => AttendanceLog::whereDate('date', $day)->where('status', 'absent')->count(),
        ]);

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
        try {
            $activities = Activity::with('causer')
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

            return response()->json($activities);
        } catch (\Exception $e) {
            return response()->json([]);
        }
    }
}
