<?php
namespace App\Services;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\Payslip;
use App\Models\PayrollComponent;
use App\Models\AttendanceLog;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class PayrollService
{
    protected ExportService $exportService;

    public function __construct(ExportService $exportService)
    {
        $this->exportService = $exportService;
    }

    // ── Saudi GOSI Rates ──────────────────────────────────────────────────────
    // Applied on BASIC salary only, Saudi nationals only
    const GOSI_EMPLOYEE_RATE = 0.09;    // 9%  — deducted from employee
    const GOSI_EMPLOYER_RATE = 0.1175;  // 11.75% — company cost (annuities 9% + hazard 2% + work injury 0.75%)

    // Saudi standard allowance rates
    const HOUSING_RATE    = 0.25;   // 25% of basic
    const TRANSPORT_FIXED = 400.00; // SAR 400/month fixed

    // Saudi working days Sun–Thu
    private const WORKING_DAYS = [0, 1, 2, 3, 4];

    // ── Run Payroll ───────────────────────────────────────────────────────────
    public function runPayroll(array $data, int $createdBy): Payroll
    {
        // Check if new salary columns exist (migration may not have run yet)
        $hasNewColumns = \Illuminate\Support\Facades\Schema::hasColumn('payslips', 'housing_allowance');

        $payroll = Payroll::create([
            'cycle_name'   => 'Payroll ' . $data['month'],
            'month'        => $data['month'],
            'period_start' => $data['period_start'],
            'period_end'   => $data['period_end'],
            'status'       => 'pending_approval',
            'created_by'   => $createdBy,
        ]);

        $employees    = Employee::where('status', 'active')->get();
        $totalGross   = 0;
        $totalDeduct  = 0;
        $totalNet     = 0;

        foreach ($employees as $employee) {
            $slip = $this->calculatePayslip($employee, $data, $hasNewColumns);
            $payroll->payslips()->create($slip);
            $totalGross  += $slip['gross_salary'];
            $totalDeduct += $slip['total_deductions'];
            $totalNet    += $slip['net_salary'];
        }

        $payroll->update([
            'total_gross'      => round($totalGross, 2),
            'total_deductions' => round($totalDeduct, 2),
            'total_net'        => round($totalNet, 2),
        ]);

        return $payroll->load('payslips');
    }

    // ── Calculate one payslip ─────────────────────────────────────────────────
    public function calculatePayslipPublic(Employee $employee, array $data, bool $hasNewColumns = true): array
    {
        return $this->calculatePayslip($employee, $data, $hasNewColumns);
    }

    protected function calculatePayslip(Employee $employee, array $data, bool $hasNewColumns = true): array
    {
        $isSaudi     = strtolower($employee->nationality ?? '') === 'saudi';
        $workingDays = $this->getPeriodWorkingDays($data['period_start'], $data['period_end']);
        $absentDays  = $this->getAbsentDays($employee->id, $data['period_start'], $data['period_end']);
        $leaveDays   = $this->getApprovedLeaveDays($employee->id, $data['period_start'], $data['period_end']);

        // ── Basic salary (pro-rated for absences) ─────────────────────────
        $fullBasic  = (float) $employee->salary;
        $dailyRate  = $workingDays > 0 ? $fullBasic / $workingDays : 0;
        $basicSalary = round($fullBasic - ($dailyRate * $absentDays), 2);

        // ── Allowances ────────────────────────────────────────────────────
        $housingAllowance   = round($basicSalary * self::HOUSING_RATE, 2);
        $transportAllowance = ($workingDays > $absentDays) ? self::TRANSPORT_FIXED : 0;

        // ── Extra components from DB (bonuses etc.) ───────────────────────
        $components    = PayrollComponent::where('is_active', true)
            ->whereNotIn('code', ['HRA','TA','GOSI_EMP','GOSI_EMP_ER']) // handled separately
            ->get();
        $otherAllowances  = 0;
        $otherDeductions  = 0;
        $componentBreakdown = [];

        foreach ($components as $comp) {
            $amount = $comp->calculation === 'percentage'
                ? round(($fullBasic * $comp->value) / 100, 2)
                : (float) $comp->value;

            if ($comp->type === 'earning') {
                $otherAllowances += $amount;
            } else {
                $otherDeductions += $amount;
            }

            $componentBreakdown[] = [
                'id'     => $comp->id,
                'code'   => $comp->code,
                'name'   => $comp->name,
                'type'   => $comp->type,
                'amount' => $amount,
            ];
        }

        // ── GOSI (Saudi nationals only) ───────────────────────────────────
        $gosiEmployee = $isSaudi ? round($basicSalary * self::GOSI_EMPLOYEE_RATE, 2) : 0;
        $gosiEmployer = $isSaudi ? round($basicSalary * self::GOSI_EMPLOYER_RATE, 2) : 0;

        // ── Totals ────────────────────────────────────────────────────────
        $totalEarnings   = round($basicSalary + $housingAllowance + $transportAllowance + $otherAllowances, 2);
        $totalDeductions = round($gosiEmployee + $otherDeductions, 2);
        $grossSalary     = $totalEarnings;
        $netSalary       = round(max(0, $grossSalary - $totalDeductions), 2);

        $base = [
            'employee_id'    => $employee->id,
            'basic_salary'   => $basicSalary,
            'total_earnings' => $totalEarnings,
            'gross_salary'   => $grossSalary,
            'total_deductions'=> $totalDeductions,
            'net_salary'     => $netSalary,
            'working_days'   => $workingDays - $absentDays,
            'absent_days'    => $absentDays,
            'leave_days'     => $leaveDays,
            'components'     => [],
        ];

        if (!$hasNewColumns) return $base;

        return array_merge($base, [
            'is_saudi'            => $isSaudi,
            // Earnings
            'housing_allowance'   => $housingAllowance,
            'transport_allowance' => $transportAllowance,
            'other_allowances'    => $otherAllowances,
            // Deductions
            'gosi_employee'       => $gosiEmployee,
            'gosi_employer'       => $gosiEmployer,
            'other_deductions'    => $otherDeductions,
            // Breakdown
            'components'          => array_merge(
                [
                    ['code'=>'BASIC',  'name'=>'Basic Salary',        'type'=>'earning',   'amount'=>$basicSalary],
                    ['code'=>'HRA',    'name'=>'Housing Allowance',   'type'=>'earning',   'amount'=>$housingAllowance],
                    ['code'=>'TA',     'name'=>'Transport Allowance', 'type'=>'earning',   'amount'=>$transportAllowance],
                ],
                $componentBreakdown,
                $isSaudi ? [
                    ['code'=>'GOSI_EMP',   'name'=>'GOSI (Employee 9%)',   'type'=>'deduction', 'amount'=>$gosiEmployee],
                    ['code'=>'GOSI_EMPER', 'name'=>'GOSI (Employer 11.75%)', 'type'=>'info',    'amount'=>$gosiEmployer],
                ] : []
            ),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Total Saudi working days (Sun–Thu) in a period */
    protected function getPeriodWorkingDays(string $from, string $to): int
    {
        $count  = 0;
        $period = CarbonPeriod::create($from, $to);
        foreach ($period as $date) {
            if (in_array($date->dayOfWeek, self::WORKING_DAYS)) $count++;
        }
        return $count;
    }

    protected function getAbsentDays(int $empId, string $from, string $to): int
    {
        return AttendanceLog::where('employee_id', $empId)
            ->whereBetween('date', [$from, $to])
            ->where('status', 'absent')
            ->count();
    }

    protected function getApprovedLeaveDays(int $empId, string $from, string $to): int
    {
        return \App\Models\LeaveRequest::where('employee_id', $empId)
            ->where('status', 'approved')
            ->where(function($q) use ($from, $to) {
                $q->whereBetween('start_date', [$from, $to])
                  ->orWhereBetween('end_date', [$from, $to]);
            })
            ->sum('total_days') ?? 0;
    }

    // ── PDF & Export ──────────────────────────────────────────────────────────
    /**
     * PDF generation placeholder — frontend handles printing via browser print API.
     * Implement with DomPDF or Browsershot when PDF library is installed.
     */
    public function generatePayslipPdf(Payslip $payslip): array
    {
        $payslip->load(['employee.department', 'payroll']);
        return ['payslip' => $payslip->toArray()];
    }

    public function dispatchPayslipEmails(Payroll $payroll): void {}

    public function exportBankTransfer(int $payrollId)
    {
        $rows = Payslip::with('employee')
            ->where('payroll_id', $payrollId)
            ->get()
            ->map(fn($p) => [
                'employee_code' => $p->employee->employee_code,
                'name'          => $p->employee->first_name . ' ' . $p->employee->last_name,
                'nationality'   => $p->employee->nationality ?? '',
                'bank_name'     => $p->employee->bank_name ?? '',
                'bank_account'  => $p->employee->bank_account ?? '',
                'basic_salary'  => $p->basic_salary,
                'housing'       => $p->housing_allowance,
                'transport'     => $p->transport_allowance,
                'gross'         => $p->gross_salary,
                'gosi_emp'      => $p->gosi_employee,
                'net_salary'    => $p->net_salary,
            ]);

        return $this->exportService->csvDownload(
            'bank_transfer_' . now()->format('Ymd') . '.csv',
            ['Emp Code','Name','Nationality','Bank','Account','Basic','Housing','Transport','Gross','GOSI(Emp)','Net'],
            $rows
        );
    }
}
