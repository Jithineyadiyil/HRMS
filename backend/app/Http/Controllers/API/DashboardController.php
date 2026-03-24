<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeRequest;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Payroll;
use App\Models\PerformanceReview;
use App\Models\LeaveRequest;
use App\Models\Separation;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats()
    {
        $today     = now()->toDateString();
        $thisMonth = now()->month;
        $thisYear  = now()->year;
        $in30      = now()->addDays(30)->toDateString();
        $in60      = now()->addDays(60)->toDateString();

        /* ── Employees ─────────────────────────── */
        $empTotal     = $this->safe(fn() => Employee::count());
        $empActive    = $this->safe(fn() => Employee::where('status','active')->count());
        $empProbation = $this->safe(fn() => Employee::where('status','probation')->count());
        $empOnLeave   = $this->safe(fn() => Employee::where('status','on_leave')->count());
        $empNewMonth  = $this->safe(fn() => Employee::whereMonth('hire_date',$thisMonth)
                                                     ->whereYear('hire_date',$thisYear)->count());

        /* ── Contracts ─────────────────────────── */
        [$cTotal,$cActive,$cExp30,$cExp60,$cExpired,$cRenewals] = $this->contractStats($today,$in30,$in60);

        /* ── Leave ─────────────────────────────── */
        $lvTotal      = $this->safe(fn() => LeaveRequest::count());
        $lvPending    = $this->safe(fn() => LeaveRequest::where('status','pending')->count());
        $lvApproved   = $this->safe(fn() => LeaveRequest::where('status','approved')->count());
        $lvRejected   = $this->safe(fn() => LeaveRequest::where('status','rejected')->count());
        $lvApprMonth  = $this->safe(fn() => LeaveRequest::where('status','approved')
                                                         ->whereMonth('updated_at',$thisMonth)
                                                         ->whereYear('updated_at',$thisYear)->count());
        $lvToday      = $this->safe(fn() => LeaveRequest::where('status','approved')
                                                         ->whereDate('start_date','<=',$today)
                                                         ->whereDate('end_date','>=',$today)->count());

        /* ── Loans ─────────────────────────────── */
        $loTotal     = $this->safe(fn() => Loan::count());
        $loPending   = $this->safe(fn() => Loan::where('status','pending')->count());
        $loActive    = $this->safe(fn() => Loan::where('status','active')->count());
        $loDisbursed = $this->safe(fn() => Loan::whereIn('status',['active','settled'])->count());
        $loSettled   = $this->safe(fn() => Loan::where('status','settled')->count());
        $loOverdue   = $this->safe(fn() => LoanInstallment::where('status','pending')
                                                           ->whereDate('due_date','<',$today)->count());

        /* ── Separations ───────────────────────── */
        $sepTotal     = $this->safe(fn() => Separation::count());
        $sepPending   = $this->safe(fn() => Separation::where('status','pending')->count());
        $sepApproved  = $this->safe(fn() => Separation::where('status','approved')->count());
        $sepRejected  = $this->safe(fn() => Separation::where('status','rejected')->count());
        $sepCompleted = $this->safe(fn() => Separation::where('status','completed')->count());
        $sepMonth     = $this->safe(fn() => Separation::whereMonth('created_at',$thisMonth)
                                                        ->whereYear('created_at',$thisYear)->count());

        /* ── Requests ──────────────────────────── */
        $reqTotal     = $this->safe(fn() => EmployeeRequest::count());
        $reqPending   = $this->safe(fn() => EmployeeRequest::where('status','pending')->count());
        $reqApproved  = $this->safe(fn() => EmployeeRequest::where('status','approved')->count());
        $reqRejected  = $this->safe(fn() => EmployeeRequest::where('status','rejected')->count());
        $reqCompleted = $this->safe(fn() => EmployeeRequest::where('status','completed')->count());
        $reqMonth     = $this->safe(fn() => EmployeeRequest::whereMonth('created_at',$thisMonth)
                                                             ->whereYear('created_at',$thisYear)->count());

        /* ── Recruitment ───────────────────────── */
        $recOpen      = $this->safe(fn() => JobPosting::where('status','open')->count());
        $recApps      = $this->safe(fn() => JobApplication::count());
        $recOffers    = $this->safe(fn() => JobApplication::where('status','offer_sent')->count());
        $recHired     = $this->safe(fn() => JobApplication::where('status','hired')
                                                            ->whereMonth('updated_at',$thisMonth)
                                                            ->whereYear('updated_at',$thisYear)->count());

        /* ── Performance ───────────────────────── */
        $perfTotal    = $this->safe(fn() => PerformanceReview::count());
        $perfPending  = $this->safe(fn() => PerformanceReview::whereIn('status',['pending_self','pending_manager','pending'])->count());
        $perfDone     = $this->safe(fn() => PerformanceReview::where('status','completed')->count());
        $perfOverdue  = $this->safe(fn() => PerformanceReview::whereNotIn('status',['completed','cancelled'])
                                                               ->whereNotNull('due_date')
                                                               ->whereDate('due_date','<',$today)->count());
        $perfAvg      = $this->safe(fn() => round(PerformanceReview::whereNotNull('final_score')->avg('final_score') ?? 0, 1));

        /* ── Payroll ───────────────────────────── */
        $payTotal     = $this->safe(fn() => Payroll::count());
        $payProcessed = $this->safe(fn() => Payroll::whereIn('status',['approved','paid'])->count());
        $payPending   = $this->safe(fn() => Payroll::where('status','pending_approval')->count());
        $payErrors    = $this->safe(fn() => Payroll::where('status','error')->count());
        $payHold      = $this->safe(fn() => Payroll::where('status','on_hold')->count());
        $payDue       = $this->safe(fn() => Payroll::whereMonth('period_end',$thisMonth)->count());

        /* ── Attendance ───────────────────────── */
        $attTotal   = $this->safe(fn() => \App\Models\AttendanceLog::whereDate('date', $today)->count());
        $attPresent = $this->safe(fn() => \App\Models\AttendanceLog::whereDate('date', $today)
                                                    ->whereIn('status',['present','late'])->count());
        $attRate    = $empActive > 0 ? round(($attPresent / max($empActive, 1)) * 100) : 0;

        /* ── Departments ───────────────────────── */
        $deptTotal    = $this->safe(fn() => Department::count());
        $deptManaged  = $this->safe(fn() => Department::whereNotNull('manager_id')->count());
        $deptVacant   = $this->safe(fn() => Department::whereNull('manager_id')->count());

        return response()->json([
            'employees' => [
                'total'              => $empTotal,
                'active'             => $empActive,
                'probation'          => $empProbation,
                'on_leave'           => $empOnLeave,
                'new_this_month'     => $empNewMonth,
                'contracts_expiring' => $cExp30,
            ],
            'contracts' => [
                'total'            => $cTotal,
                'active'           => $cActive,
                'expiring_30'      => $cExp30,
                'expiring_60'      => $cExp60,
                'expired'          => $cExpired,
                'pending_renewals' => $cRenewals,
            ],
            'leave' => [
                'total'               => $lvTotal,
                'pending'             => $lvPending,
                'approved'            => $lvApproved,
                'rejected'            => $lvRejected,
                'on_leave_today'      => $lvToday,
                'approved_this_month' => $lvApprMonth,
            ],
            'loans' => [
                'total'     => $loTotal,
                'pending'   => $loPending,
                'active'    => $loActive,
                'disbursed' => $loDisbursed,
                'settled'   => $loSettled,
                'overdue'   => $loOverdue,
            ],
            'separations' => [
                'total'      => $sepTotal,
                'pending'    => $sepPending,
                'approved'   => $sepApproved,
                'rejected'   => $sepRejected,
                'completed'  => $sepCompleted,
                'this_month' => $sepMonth,
            ],
            'requests' => [
                'total'      => $reqTotal,
                'pending'    => $reqPending,
                'approved'   => $reqApproved,
                'rejected'   => $reqRejected,
                'completed'  => $reqCompleted,
                'this_month' => $reqMonth,
            ],
            'recruitment' => [
                'open_positions'   => $recOpen,
                'applicants'       => $recApps,
                'interviews_today' => 0,
                'offers_sent'      => $recOffers,
                'hired_this_month' => $recHired,
            ],
            'performance' => [
                'total'       => $perfTotal,
                'pending'     => $perfPending,
                'in_progress' => $perfPending,
                'completed'   => $perfDone,
                'overdue'     => $perfOverdue,
                'avg_score'   => $perfAvg ?: '—',
            ],
            'payroll' => [
                'total'             => $payTotal,
                'due_this_month'    => $payDue,
                'processed'         => $payProcessed,
                'pending_approvals' => $payPending,
                'errors'            => $payErrors,
                'on_hold'           => $payHold,
            ],
            'attendance' => [
                'present'  => $attPresent,
                'total'    => $attTotal,
                'rate'     => $attRate,
            ],
            'departments' => [
                'total'      => $deptTotal,
                'teams'      => $deptTotal,
                'managers'   => $deptManaged,
                'vacant_mgr' => $deptVacant,
            ],
        ]);
    }

    public function charts()
    {
        $hireTrend = $this->safe(fn() =>
            Employee::selectRaw("DATE_FORMAT(hire_date,'%b %Y') as month, COUNT(*) as count")
                ->where('hire_date','>=',now()->subMonths(6)->startOfMonth())
                ->groupByRaw("YEAR(hire_date),MONTH(hire_date)")
                ->orderByRaw("YEAR(hire_date),MONTH(hire_date)")->get(), []);

        $exitTrend = $this->safe(fn() =>
            Separation::selectRaw("DATE_FORMAT(created_at,'%b %Y') as month, COUNT(*) as count")
                ->where('status','completed')
                ->where('created_at','>=',now()->subMonths(6)->startOfMonth())
                ->groupByRaw("YEAR(created_at),MONTH(created_at)")
                ->orderByRaw("YEAR(created_at),MONTH(created_at)")->get(), []);

        $deptDist = $this->safe(fn() =>
            Department::withCount('employees')->having('employees_count','>',0)
                ->orderByDesc('employees_count')->limit(8)->get(['name','employees_count'])
                ->map(fn($d) => ['name'=>$d->name,'count'=>$d->employees_count]), []);

        $leaveByType = $this->safe(fn() =>
            LeaveRequest::join('leave_types','leave_requests.leave_type_id','=','leave_types.id')
                ->selectRaw('leave_types.name as leave_type, COUNT(*) as count')
                ->groupBy('leave_types.name')->get(), []);

        $payrollTrend = $this->safe(fn() =>
            Payroll::selectRaw("DATE_FORMAT(period_end,'%b %Y') as month, SUM(total_net) as total")
                ->where('period_end','>=',now()->subMonths(6)->startOfMonth())
                ->whereIn('status',['approved','paid'])
                ->groupByRaw("YEAR(period_end),MONTH(period_end)")
                ->orderByRaw("YEAR(period_end),MONTH(period_end)")->get(), []);

        $loanStatus = $this->safe(fn() =>
            Loan::selectRaw('status, COUNT(*) as count')->groupBy('status')->get()
                ->map(fn($l) => ['status'=>ucfirst($l->status),'count'=>$l->count]), []);

        $perfRatings = $this->safe(fn() =>
            PerformanceReview::selectRaw("
                CASE
                  WHEN final_score>=4.5 THEN 'Outstanding'
                  WHEN final_score>=3.5 THEN 'Exceeds Exp'
                  WHEN final_score>=2.5 THEN 'Meets Exp'
                  WHEN final_score>=1.5 THEN 'Below Avg'
                  ELSE 'Poor'
                END as rating, COUNT(*) as count")
                ->whereNotNull('final_score')->groupByRaw("1")->get(), []);

        return response()->json([
            'hire_trend'          => $hireTrend,
            'exit_trend'          => $exitTrend,
            'dept_distribution'   => $deptDist,
            'leave_by_type'       => $leaveByType,
            'payroll_trend'       => $payrollTrend,
            'loan_status'         => $loanStatus,
            'performance_ratings' => $perfRatings,
        ]);
    }

    public function recentActivity()
    {
        // Spatie activity log
        if (class_exists(\Spatie\Activitylog\Models\Activity::class)) {
            $logs = \Spatie\Activitylog\Models\Activity::with('causer')
                ->latest()->limit(20)->get()
                ->map(fn($a) => [
                    'action'     => $a->event ?? $a->description,
                    'module'     => $a->log_name,
                    'user'       => ['name' => $a->causer?->name ?? 'System'],
                    'created_at' => $a->created_at,
                ]);
            return response()->json($logs);
        }

        // Fallback
        $items = collect();
        $this->safe(fn() => Employee::latest()->limit(5)->get()->each(fn($e) =>
            $items->push(['action'=>'joined','module'=>'Employees',
                'user'=>['name'=>trim($e->first_name.' '.$e->last_name)],
                'created_at'=>$e->created_at])));
        $this->safe(fn() => LeaveRequest::with('employee')->latest()->limit(5)->get()->each(fn($r) =>
            $items->push(['action'=>$r->status==='approved'?'approved':'created','module'=>'Leave',
                'user'=>['name'=>trim(($r->employee->first_name??'').(' '.($r->employee->last_name??'')))],
                'created_at'=>$r->updated_at])));
        $this->safe(fn() => Separation::with('employee')->latest()->limit(4)->get()->each(fn($s) =>
            $items->push(['action'=>'updated','module'=>'Separations',
                'user'=>['name'=>trim(($s->employee->first_name??'').(' '.($s->employee->last_name??'')))],
                'created_at'=>$s->updated_at])));

        return response()->json($items->sortByDesc('created_at')->values()->take(20));
    }

    /* ── Helpers ───────────────────────────────────────────────────────── */

    /** Run a query safely — return $default on any exception */
    private function safe(callable $fn, $default = 0)
    {
        try { return $fn(); } catch (\Throwable $e) { return $default; }
    }

    /** Try dedicated contracts table first, fall back to employee columns */
    private function contractStats(string $today, string $in30, string $in60): array
    {
        try {
            // Check for a dedicated contracts table
            $hasCT = DB::select("SHOW TABLES LIKE 'contracts'");
            if ($hasCT) {
                return [
                    DB::table('contracts')->count(),
                    DB::table('contracts')->where('status','active')->count(),
                    DB::table('contracts')->where('status','active')->whereDate('end_date','>=',$today)->whereDate('end_date','<=',$in30)->count(),
                    DB::table('contracts')->where('status','active')->whereDate('end_date','>=',$today)->whereDate('end_date','<=',$in60)->count(),
                    DB::table('contracts')->whereDate('end_date','<',$today)->count(),
                    DB::table('contracts')->where('status','pending_renewal')->count(),
                ];
            }
        } catch (\Throwable) {}

        // Fallback: look for contract_end_date on employees
        try {
            $cols = DB::select("SHOW COLUMNS FROM employees LIKE 'contract_end_date'");
            if ($cols) {
                return [
                    Employee::whereNotNull('contract_end_date')->count(),
                    Employee::whereNotNull('contract_end_date')->whereDate('contract_end_date','>=',$today)->count(),
                    Employee::whereDate('contract_end_date','>=',$today)->whereDate('contract_end_date','<=',$in30)->count(),
                    Employee::whereDate('contract_end_date','>=',$today)->whereDate('contract_end_date','<=',$in60)->count(),
                    Employee::whereDate('contract_end_date','<',$today)->count(),
                    0,
                ];
            }
        } catch (\Throwable) {}

        return [0, 0, 0, 0, 0, 0];
    }
}
