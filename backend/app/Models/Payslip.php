<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payslip extends Model
{
    protected $fillable = [
        'payroll_id', 'employee_id',
        // Earnings
        'basic_salary', 'housing_allowance', 'transport_allowance', 'other_allowances',
        'total_earnings', 'gross_salary',
        // Deductions
        'gosi_employee', 'gosi_employer', 'other_deductions', 'total_deductions',
        // Net
        'net_salary',
        // Attendance
        'working_days', 'absent_days', 'leave_days',
        // Leave & Loan deductions
        'unpaid_leave_days', 'leave_deduction', 'loan_deduction',
        // Meta
        'is_saudi', 'pdf_path', 'email_sent', 'email_sent_at', 'components',
    ];

    protected $casts = [
        'components'         => 'array',
        'email_sent'         => 'boolean',
        'email_sent_at'      => 'datetime',
        'is_saudi'           => 'boolean',
        'basic_salary'       => 'float',
        'housing_allowance'  => 'float',
        'transport_allowance'=> 'float',
        'other_allowances'   => 'float',
        'gosi_employee'      => 'float',
        'gosi_employer'      => 'float',
        'other_deductions'   => 'float',
        'total_earnings'     => 'float',
        'total_deductions'   => 'float',
        'gross_salary'       => 'float',
        'net_salary'         => 'float',
        'unpaid_leave_days'  => 'float',
        'leave_deduction'    => 'float',
        'loan_deduction'     => 'float',
    ];

    public function payroll()  { return $this->belongsTo(Payroll::class); }
    public function employee() { return $this->belongsTo(Employee::class); }
}
