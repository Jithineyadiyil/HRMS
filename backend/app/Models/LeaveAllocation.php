<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class LeaveAllocation extends Model {
    protected $fillable = [
        'employee_id','leave_type_id','year',
        'allocated_days','used_days','pending_days','remaining_days',
        'carried_forward_days',
        'used_hours','pending_hours',
        'accrual_year_start','last_accrual_date','annual_entitlement',
    ];
    protected $casts = [
        'accrual_year_start'    => 'date',
        'last_accrual_date'     => 'date',
        'allocated_days'        => 'float',
        'used_days'             => 'float',
        'pending_days'          => 'float',
        'remaining_days'        => 'float',
        'carried_forward_days'  => 'float',
        'used_hours'            => 'float',
        'pending_hours'         => 'float',
    ];
    public function employee()  { return $this->belongsTo(Employee::class); }
    public function leaveType() { return $this->belongsTo(LeaveType::class); }
}
