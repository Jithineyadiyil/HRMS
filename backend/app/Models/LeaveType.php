<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model {
    use HasFactory;
    protected $fillable = [
        'name','code','days_allowed','is_paid','carry_forward',
        'max_carry_forward','requires_document','is_active','description',
        'is_hourly','monthly_hours_limit','exempt_department_codes',
        'skip_manager_approval',
    ];
    protected $casts = [
        'is_paid'                  => 'boolean',
        'carry_forward'            => 'boolean',
        'requires_document'        => 'boolean',
        'is_active'                => 'boolean',
        'skip_manager_approval'    => 'boolean',
        'is_hourly'                => 'boolean',
        'exempt_department_codes'  => 'array',
    ];
    public function allocations() { return $this->hasMany(LeaveAllocation::class); }
    public function requests()    { return $this->hasMany(LeaveRequest::class); }
}
