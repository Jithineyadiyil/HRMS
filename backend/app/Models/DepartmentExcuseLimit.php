<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DepartmentExcuseLimit extends Model
{
    protected $table    = 'department_excuse_limits';
    protected $fillable = ['department_id', 'leave_type_id', 'monthly_hours_limit', 'is_limited'];
    protected $casts    = [
        'monthly_hours_limit' => 'float',
        'is_limited'          => 'boolean',
    ];

    public function department() { return $this->belongsTo(Department::class); }
    public function leaveType()  { return $this->belongsTo(LeaveType::class); }
}
