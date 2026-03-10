<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model {
    protected $fillable = [
        'employee_id','leave_type_id',
        'start_date','start_time','end_date','end_time',
        'total_days','total_hours',
        'status','reason','rejection_reason',
        'approved_by','approved_at','document_path',
    ];
    protected $casts = [
        'start_date'  => 'date',
        'end_date'    => 'date',
        'approved_at' => 'datetime',
        'total_days'  => 'decimal:1',
        'total_hours' => 'decimal:2',
    ];
    public function employee()  { return $this->belongsTo(Employee::class); }
    public function leaveType() { return $this->belongsTo(LeaveType::class); }
    public function approver()  { return $this->belongsTo(User::class,'approved_by'); }
    public function scopePending($q) { return $q->where('status','pending'); }
}
