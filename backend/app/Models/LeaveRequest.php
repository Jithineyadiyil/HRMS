<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'leave_type_id',
        'start_date', 'start_time', 'end_date', 'end_time',
        'total_days', 'total_hours',
        'status', 'reason', 'rejection_reason',
        'approved_by', 'approved_at', 'document_path',
        // Two-level approval fields (added by 2024_01_07_000001 migration)
        'manager_approved_by', 'manager_approved_at', 'manager_notes',
        'hr_notes', 'rejected_stage',
    ];

    protected $casts = [
        'start_date'          => 'date',
        'end_date'            => 'date',
        'approved_at'         => 'datetime',
        'manager_approved_at' => 'datetime',
        'total_days'          => 'decimal:1',
        'total_hours'         => 'decimal:2',
    ];

    public function employee()        { return $this->belongsTo(Employee::class); }
    public function leaveType()       { return $this->belongsTo(LeaveType::class); }
    public function approver()        { return $this->belongsTo(User::class, 'approved_by'); }
    public function managerApprover() { return $this->belongsTo(User::class, 'manager_approved_by'); }
    public function scopePending($q)  { return $q->where('status', 'pending'); }
}
