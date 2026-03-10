<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Separation extends Model {
    protected $fillable = [
        'reference','employee_id','type','status',
        'request_date','last_working_day','notice_period_start','notice_period_days',
        'notice_waived','notice_waived_reason',
        'reason','reason_category','hr_notes',
        'initiated_by','manager_approved_by','manager_approved_at',
        'hr_approved_by','hr_approved_at',
        'rejected_by','rejected_at','rejection_reason',
        'exit_interview_required','exit_interview_done','exit_interview_date','exit_interview_notes',
        'gratuity_amount','leave_encashment','other_deductions','other_additions',
        'final_settlement_amount','settlement_paid','settlement_paid_date','settlement_notes',
    ];
    protected $casts = [
        'request_date'          => 'date',
        'last_working_day'      => 'date',
        'notice_period_start'   => 'date',
        'exit_interview_date'   => 'date',
        'settlement_paid_date'  => 'date',
        'manager_approved_at'   => 'datetime',
        'hr_approved_at'        => 'datetime',
        'rejected_at'           => 'datetime',
        'notice_waived'         => 'boolean',
        'exit_interview_required' => 'boolean',
        'exit_interview_done'   => 'boolean',
        'settlement_paid'       => 'boolean',
        'gratuity_amount'       => 'float',
        'leave_encashment'      => 'float',
        'other_deductions'      => 'float',
        'other_additions'       => 'float',
        'final_settlement_amount' => 'float',
    ];
    public function employee()        { return $this->belongsTo(Employee::class); }
    public function initiatedBy()     { return $this->belongsTo(User::class, 'initiated_by'); }
    public function managerApprover() { return $this->belongsTo(User::class, 'manager_approved_by'); }
    public function hrApprover()      { return $this->belongsTo(User::class, 'hr_approved_by'); }
    public function rejectedBy()      { return $this->belongsTo(User::class, 'rejected_by'); }
    public function checklistItems()  { return $this->hasMany(OffboardingItem::class)->orderBy('sort_order'); }
}
