<?php
namespace App\Services;

use App\Mail\PayslipMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

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

            // Mark loan installment as paid and update outstanding balance
            if (!empty($slip['loan_installment_id']) && !empty($slip['loan_id'])) {
                try {
                    \App\Models\LoanInstallment::where('id', $slip['loan_installment_id'])
                        ->update(['status' => 'paid', 'paid_date' => now(), 'paid_amount' => $slip['loan_deduction']]);
                    $loan = \App\Models\Loan::find($slip['loan_id']);
                    if ($loan) {
                        $paidCount = \App\Models\LoanInstallment::where('loan_id', $loan->id)->where('status','paid')->count();
                        $remaining = max(0, round((float)$loan->amount - ($paidCount * (float)$loan->monthly_installment), 2));
                        $loan->update([
                            'paid_installments'   => $paidCount,
                            'outstanding_balance' => $remaining,
                            'status'              => $paidCount >= (int)$loan->total_installments ? 'settled' : 'active',
                        ]);
                    }
                } catch (\Throwable $e) {}
            }
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
        $absentDays    = $this->getAbsentDays($employee->id, $data['period_start'], $data['period_end']);
        $leaveDays     = $this->getApprovedLeaveDays($employee->id, $data['period_start'], $data['period_end']);
        $unpaidLeaveDays = $this->getUnpaidLeaveDays($employee->id, $data['period_start'], $data['period_end']);

        // ── Load payroll settings ─────────────────────────────────────────
        $deductUnpaid   = \App\Models\PayrollSetting::get('deduct_unpaid_leave', true);
        $deductAbsences = \App\Models\PayrollSetting::get('deduct_absences', true);
        $ratesBasis     = \App\Models\PayrollSetting::get('daily_rate_basis', 'monthly');
        $fixedDays      = (int) \App\Models\PayrollSetting::get('working_days_per_month', 26);

        // ── Basic salary (pro-rated for absences + unpaid leave) ──────────
        $fullBasic  = (float) $employee->salary;
        $dailyRate  = match($ratesBasis) {
            'fixed'  => $fixedDays > 0 ? $fullBasic / $fixedDays : 0,
            'annual' => $fullBasic * 12 / 260,
            default  => $workingDays > 0 ? $fullBasic / $workingDays : 0, // 'monthly'
        };

        // Days to deduct from basic salary
        $deductDays  = 0;
        if ($deductAbsences) $deductDays += $absentDays;
        if ($deductUnpaid)   $deductDays += $unpaidLeaveDays;

        $leaveDeductionAmt = round($dailyRate * $unpaidLeaveDays, 2);

        // ── Active loan installment deduction ────────────────────────────
        $loanDeduction  = 0;
        $activeLoanId   = null;
        $loanInstallId  = null;
        try {
            $activeLoan = \App\Models\Loan::where('employee_id', $employee->id)
                ->where('status', 'active')
                ->first();
            if ($activeLoan) {
                $loanDeduction = (float)($activeLoan->monthly_installment ?? 0);
                $activeLoanId  = $activeLoan->id;
                // Find the next unpaid installment to mark as paid on payroll run
                $nextInst = \App\Models\LoanInstallment::where('loan_id', $activeLoan->id)
                    ->where('status', 'pending')
                    ->orderBy('due_date')
                    ->first();
                if ($nextInst) $loanInstallId = $nextInst->id;
            }
        } catch (\Throwable $e) {
            // Loan table may not exist in older deployments
        }
        $basicSalary = round($fullBasic - ($dailyRate * $deductDays), 2);
        $basicSalary = max(0, $basicSalary);

        // ── Allowances ────────────────────────────────────────────────────
        // Use employee-specific values if set, otherwise apply Saudi standard rates
        $deductAllowances = \App\Models\PayrollSetting::get('deduct_allowances_on_leave', false);
        $housingAllowance   = $employee->housing_allowance !== null
            ? round((float)$employee->housing_allowance, 2)
            : round($basicSalary * self::HOUSING_RATE, 2);

        $transportAllowance = $employee->transport_allowance !== null
            ? round((float)$employee->transport_allowance, 2)
            : (($workingDays > $absentDays) ? self::TRANSPORT_FIXED : 0);

        // Additional fixed allowances from employee record
        $mobileAllowance = round((float)($employee->mobile_allowance ?? 0), 2);
        $foodAllowance   = round((float)($employee->food_allowance   ?? 0), 2);
        $extraAllowances = round((float)($employee->other_allowances ?? 0), 2);

        // ── Extra components from DB (bonuses etc.) ───────────────────────
        $components    = PayrollComponent::where('is_active', true)
            ->whereNotIn('code', ['HRA','TA','GOSI_EMP','GOSI_EMP_ER']) // handled separately
            ->get();
        $otherAllowances  = $mobileAllowance + $foodAllowance + $extraAllowances; // start with employee-specific
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
        $totalDeductions = round($gosiEmployee + $otherDeductions + ($deductUnpaid ? $leaveDeductionAmt : 0) + $loanDeduction, 2);
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
            'unpaid_leave_days'   => $unpaidLeaveDays,
            'loan_deduction'      => $loanDeduction,
            'loan_id'             => $activeLoanId,
            'loan_installment_id' => $loanInstallId,
            'leave_deduction'     => $leaveDeductionAmt,
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
                array_filter($componentBreakdown, fn($c) => $c !== null),
                $loanDeduction > 0 ? [
                    ['code'=>'LOAN', 'name'=>'Loan Installment', 'type'=>'deduction', 'amount'=>$loanDeduction],
                ] : [],
                $isSaudi ? array_filter([
                    ['code'=>'GOSI_EMP',   'name'=>'GOSI (Employee 9%)',   'type'=>'deduction', 'amount'=>$gosiEmployee],
                    $unpaidLeaveDays > 0 ? ['code'=>'LEAVE_DED', 'name'=>'Unpaid Leave ('.$unpaidLeaveDays.' days)', 'type'=>'deduction', 'amount'=>$leaveDeductionAmt] : null,
                    ['code'=>'GOSI_EMPER', 'name'=>'GOSI (Employer 11.75%)', 'type'=>'info',    'amount'=>$gosiEmployer],
                ]) : []
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

    protected function getUnpaidLeaveDays(int $empId, string $from, string $to): float
    {
        return \App\Models\LeaveRequest::where('employee_id', $empId)
            ->where('status', 'approved')
            ->whereHas('leaveType', fn($q) => $q->where('is_paid', false))
            ->where(function($q) use ($from, $to) {
                $q->whereBetween('start_date', [$from, $to])
                  ->orWhereBetween('end_date', [$from, $to]);
            })
            ->sum('total_days') ?? 0;
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
    public function generatePayslipPdf(Payslip $payslip)
    {
        // Eager-load all relations needed by the blade template
        $payslip->load(['employee.department', 'employee.designation', 'payroll']);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('payslip', ['payslip' => $payslip]);

        // A4 portrait, 150 DPI for crisp logo rendering
        $pdf->setPaper('a4', 'portrait');

        return $pdf;
    }

    public function dispatchPayslipEmails(Payroll $payroll): void
    {
        $payroll->load('payslips.employee');
        foreach ($payroll->payslips as $payslip) {
            try {
                $email = $payslip->employee?->email;
                if (!$email) continue;
                Mail::to($email)->queue(new PayslipMail($payslip));
                $payslip->update(['email_sent' => true, 'email_sent_at' => now()]);
            } catch (\Throwable $e) {
                Log::warning("Payslip email failed for payslip {$payslip->id}: " . $e->getMessage());
            }
        }
    }

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
