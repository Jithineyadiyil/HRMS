<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\{Employee, Payslip, Payroll, LeaveRequest, LeaveAllocation, LeaveType,
                AttendanceLog, Loan, LoanInstallment, Department};
use App\Services\ExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    protected ExportService $export;

    public function __construct(ExportService $export)
    {
        $this->export = $export;
    }

    // ── Preview (JSON) ────────────────────────────────────────────────────

    public function employees(Request $request)
    {
        $rows = Employee::with(['department','designation'])
            ->when($request->department_id, fn($q) => $q->where('department_id',$request->department_id))
            ->when($request->status,        fn($q) => $q->where('status',$request->status))
            ->when($request->from,          fn($q) => $q->whereDate('hire_date','>=',$request->from))
            ->when($request->to,            fn($q) => $q->whereDate('hire_date','<=',$request->to))
            ->orderBy('first_name')
            ->get()
            ->map(fn($e) => [
                'code'        => $e->employee_code,
                'name'        => trim("{$e->first_name} {$e->last_name}"),
                'department'  => $e->department?->name ?? '—',
                'designation' => $e->designation?->title ?? '—',
                'hire_date'   => $e->hire_date?->format('d M Y') ?? '—',
                'nationality' => $e->nationality ?? '—',
                'status'      => $e->status,
                'email'       => $e->email,
                'phone'       => $e->phone ?? '—',
                'salary'      => number_format($e->salary ?? 0, 2),
            ]);

        return response()->json(['data' => $rows, 'total' => $rows->count()]);
    }

    public function payroll(Request $request)
    {
        $request->validate(['month' => 'required|date_format:Y-m']);
        [$year,$month] = explode('-', $request->month);

        $rows = Payslip::with(['employee.department'])
            ->whereHas('payroll', fn($q) => $q->where('month', $request->month))
            ->when($request->department_id, fn($q) =>
                $q->whereHas('employee', fn($eq) => $eq->where('department_id',$request->department_id))
            )
            ->get()
            ->map(fn($p) => [
                'code'         => $p->employee?->employee_code,
                'name'         => trim("{$p->employee?->first_name} {$p->employee?->last_name}"),
                'department'   => $p->employee?->department?->name ?? '—',
                'basic'        => number_format($p->basic_salary ?? 0, 2),
                'housing'      => number_format($p->housing_allowance ?? 0, 2),
                'transport'    => number_format($p->transport_allowance ?? 0, 2),
                'other_earn'   => number_format($p->other_allowances ?? 0, 2),
                'gross'        => number_format($p->gross_salary ?? 0, 2),
                'gosi'         => number_format($p->gosi_employee ?? 0, 2),
                'deductions'   => number_format($p->total_deductions ?? 0, 2),
                'net'          => number_format($p->net_salary ?? 0, 2),
                'working_days' => $p->working_days ?? '—',
                'absent_days'  => $p->absent_days ?? 0,
            ]);

        $totals = [
            'gross' => $rows->sum(fn($r) => str_replace(',','',$r['gross'])),
            'deductions' => $rows->sum(fn($r) => str_replace(',','',$r['deductions'])),
            'net'   => $rows->sum(fn($r) => str_replace(',','',$r['net'])),
        ];

        return response()->json(['data' => $rows, 'totals' => $totals, 'total' => $rows->count()]);
    }

    public function leaveBalance(Request $request)
    {
        $year = $request->year ?? now()->year;
        $annualType = LeaveType::where('code','AL')->orWhere('name','like','%Annual%')->orderBy('id')->first();

        $rows = LeaveAllocation::with(['employee.department','leaveType'])
            ->where('year', $year)
            ->when($annualType && $request->boolean('annual_only', true),
                fn($q) => $q->where('leave_type_id', $annualType?->id))
            ->when($request->department_id, fn($q) =>
                $q->whereHas('employee', fn($eq) => $eq->where('department_id',$request->department_id))
            )
            ->get()
            ->map(fn($a) => [
                'code'          => $a->employee?->employee_code,
                'name'          => trim("{$a->employee?->first_name} {$a->employee?->last_name}"),
                'department'    => $a->employee?->department?->name ?? '—',
                'leave_type'    => $a->leaveType?->name ?? '—',
                'entitlement'   => $a->annual_entitlement ?? $a->allocated_days,
                'used'          => $a->used_days,
                'pending'       => $a->pending_days,
                'remaining'     => $a->remaining_days,
                'year'          => $a->year,
            ]);

        return response()->json(['data' => $rows, 'total' => $rows->count(), 'year' => $year]);
    }

    public function leaveRequests(Request $request)
    {
        $rows = LeaveRequest::with(['employee.department','leaveType','approver'])
            ->when($request->from,          fn($q) => $q->whereDate('start_date','>=',$request->from))
            ->when($request->to,            fn($q) => $q->whereDate('end_date','<=',$request->to))
            ->when($request->status,        fn($q) => $q->where('status',$request->status))
            ->when($request->leave_type_id, fn($q) => $q->where('leave_type_id',$request->leave_type_id))
            ->when($request->department_id, fn($q) =>
                $q->whereHas('employee', fn($eq) => $eq->where('department_id',$request->department_id))
            )
            ->orderBy('start_date','desc')
            ->get()
            ->map(fn($r) => [
                'code'       => $r->employee?->employee_code,
                'name'       => trim("{$r->employee?->first_name} {$r->employee?->last_name}"),
                'department' => $r->employee?->department?->name ?? '—',
                'leave_type' => $r->leaveType?->name ?? '—',
                'from'       => $r->start_date?->format('d M Y') ?? '—',
                'to'         => $r->end_date?->format('d M Y')   ?? '—',
                'days'       => $r->total_days,
                'status'     => $r->status,
                'reason'     => $r->reason,
                'approved_by'=> optional($r->approver)->name ?? '—',
            ]);

        return response()->json(['data' => $rows, 'total' => $rows->count()]);
    }

    public function attendance(Request $request)
    {
        $request->validate(['month' => 'required|date_format:Y-m']);
        [$year, $month] = explode('-', $request->month);

        $rows = AttendanceLog::with(['employee.department'])
            ->whereYear('date', $year)->whereMonth('date', $month)
            ->when($request->department_id, fn($q) =>
                $q->whereHas('employee', fn($eq) => $eq->where('department_id',$request->department_id))
            )
            ->when($request->status, fn($q) => $q->where('status',$request->status))
            ->orderBy('date','desc')->orderBy('employee_id')
            ->get()
            ->map(fn($l) => [
                'code'       => $l->employee?->employee_code,
                'name'       => trim("{$l->employee?->first_name} {$l->employee?->last_name}"),
                'department' => $l->employee?->department?->name ?? '—',
                'date'       => Carbon::parse($l->date)->format('d M Y'),
                'check_in'   => $l->check_in  ? substr($l->check_in, 0, 5) : '—',
                'check_out'  => $l->check_out ? substr($l->check_out, 0, 5) : '—',
                'hours'      => $l->duration_label ?? '—',
                'status'     => $l->status,
                'source'     => $l->source ?? 'system',
                'notes'      => $l->notes ?? '',
            ]);

        return response()->json(['data' => $rows, 'total' => $rows->count()]);
    }

    public function loans(Request $request)
    {
        $rows = Loan::with(['employee.department','loanType'])
            ->when($request->status,        fn($q) => $q->where('status',$request->status))
            ->when($request->department_id, fn($q) =>
                $q->whereHas('employee', fn($eq) => $eq->where('department_id',$request->department_id))
            )
            ->when($request->from, fn($q) => $q->whereDate('disbursement_date','>=',$request->from))
            ->when($request->to,   fn($q) => $q->whereDate('disbursement_date','<=',$request->to))
            ->orderBy('created_at','desc')
            ->get()
            ->map(fn($l) => [
                'code'          => $l->employee?->employee_code,
                'name'          => trim("{$l->employee?->first_name} {$l->employee?->last_name}"),
                'department'    => $l->employee?->department?->name ?? '—',
                'loan_type'     => $l->loanType?->name ?? '—',
                'amount'        => number_format($l->amount ?? 0, 2),
                'outstanding'   => number_format($l->outstanding_balance ?? $l->amount ?? 0, 2),
                'installment'   => number_format($l->monthly_installment ?? 0, 2),
                'installments'  => ($l->paid_installments ?? 0) . '/' . ($l->total_installments ?? '—'),
                'disbursed'     => $l->disbursement_date ? Carbon::parse($l->disbursement_date)->format('d M Y') : '—',
                'status'        => $l->status,
            ]);

        return response()->json(['data' => $rows, 'total' => $rows->count()]);
    }

    // ── CSV Downloads ─────────────────────────────────────────────────────

    public function downloadCsv(Request $request, string $type)
    {
        $data = $this->getReportData($request, $type);

        if ($data->isEmpty()) {
            return response()->json(['message' => 'No data to export.'], 404);
        }

        $headers   = array_keys($data->first());
        $filename  = $type . '_report_' . now()->format('Ymd_His') . '.csv';

        return $this->export->csvDownload($filename, $this->humanHeaders($type), $data);
    }

    // ── PDF Downloads ─────────────────────────────────────────────────────

    public function downloadPdf(Request $request, string $type)
    {
        $data     = $this->getReportData($request, $type);
        $title    = $this->reportTitle($type);
        $filters  = $this->filterSummary($request, $type);
        $headers  = $this->humanHeaders($type);

        $pdf = Pdf::loadView('reports.generic', compact('data','title','filters','headers'))
                  ->setPaper('a4', 'landscape')
                  ->setOptions(['dpi' => 150, 'defaultFont' => 'Arial']);

        $filename = $type . '_report_' . now()->format('Ymd_His') . '.pdf';

        return $pdf->download($filename);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function getReportData(Request $request, string $type)
    {
        return match ($type) {
            'employees'      => $this->employees($request)->getData(true)['data'],
            'payroll'        => $this->payroll($request)->getData(true)['data'],
            'leave-balance'  => $this->leaveBalance($request)->getData(true)['data'],
            'leave-requests' => $this->leaveRequests($request)->getData(true)['data'],
            'attendance'     => $this->attendance($request)->getData(true)['data'],
            'loans'          => $this->loans($request)->getData(true)['data'],
            default          => collect(),
        };
    }

    private function humanHeaders(string $type): array
    {
        return match ($type) {
            'employees'      => ['Code','Name','Department','Designation','Hire Date','Nationality','Status','Email','Phone','Basic Salary (SAR)'],
            'payroll'        => ['Code','Name','Department','Basic','Housing Allow.','Transport Allow.','Other Earnings','Gross','GOSI Employee','Total Deductions','Net Salary','Working Days','Absent Days'],
            'leave-balance'  => ['Code','Name','Department','Leave Type','Entitlement','Used','Pending','Remaining','Year'],
            'leave-requests' => ['Code','Name','Department','Leave Type','From','To','Days','Status','Reason','Approved By'],
            'attendance'     => ['Code','Name','Department','Date','Check In','Check Out','Hours','Status','Source','Notes'],
            'loans'          => ['Code','Name','Department','Loan Type','Amount (SAR)','Outstanding (SAR)','Monthly Installment','Installments (Paid/Total)','Disbursed Date','Status'],
            default          => [],
        };
    }

    private function reportTitle(string $type): string
    {
        return match ($type) {
            'employees'      => 'Employee Report',
            'payroll'        => 'Payroll Report',
            'leave-balance'  => 'Leave Balance Report',
            'leave-requests' => 'Leave Requests Report',
            'attendance'     => 'Attendance Report',
            'loans'          => 'Loan Report',
            default          => 'Report',
        };
    }

    private function filterSummary(Request $request, string $type): array
    {
        $filters = ['Generated' => now()->format('d M Y H:i'), 'Report Type' => $this->reportTitle($type)];
        if ($request->month)         $filters['Month']      = $request->month;
        if ($request->from)          $filters['From']       = $request->from;
        if ($request->to)            $filters['To']         = $request->to;
        if ($request->status)        $filters['Status']     = ucfirst($request->status);
        if ($request->department_id) {
            $dept = Department::find($request->department_id);
            if ($dept) $filters['Department'] = $dept->name;
        }
        if ($request->year)          $filters['Year']       = $request->year;
        return $filters;
    }
}
