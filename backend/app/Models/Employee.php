<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Employee extends Model
{
    use SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['first_name','last_name','email','status','department_id','salary'])->logOnlyDirty();
    }

    protected $fillable = [
        'user_id','department_id','designation_id','manager_id',
        'employee_code','prefix','first_name','last_name','arabic_name','email',
        'phone','work_phone','extension',
        'dob','gender','marital_status','nationality','hire_date','confirmation_date',
        'termination_date','employment_type','mode_of_employment','role',
        'status','probation_period','years_of_experience',
        'salary','avatar','address','city','country',
        'national_id','bank_name','bank_account',
        'emergency_contact_name','emergency_contact_phone','emergency_contact_relation',
        'notes',
    ];

    protected $hidden = ['national_id','bank_account'];

    protected $casts = [
        'dob'               => 'date',
        'hire_date'         => 'date',
        'confirmation_date' => 'date',
        'termination_date'  => 'date',
        'salary'            => 'decimal:2',
        'probation_period'  => 'integer',
        'years_of_experience' => 'integer',
    ];

    protected $appends = ['full_name','avatar_url'];

    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? asset('storage/' . $this->avatar) : null;
    }

    public function user()          { return $this->belongsTo(User::class); }
    public function department()    { return $this->belongsTo(Department::class); }
    public function designation()   { return $this->belongsTo(Designation::class); }
    public function manager()       { return $this->belongsTo(Employee::class, 'manager_id'); }
    public function subordinates()  { return $this->hasMany(Employee::class, 'manager_id'); }
    public function documents()     { return $this->hasMany(EmployeeDocument::class); }
    public function payslips()      { return $this->hasMany(Payslip::class); }
    public function leaveRequests() { return $this->hasMany(LeaveRequest::class); }
    public function leaveAllocations() { return $this->hasMany(LeaveAllocation::class); }
    public function attendanceLogs()   { return $this->hasMany(AttendanceLog::class); }
    public function onboardingTasks()  { return $this->hasMany(OnboardingTask::class); }
    public function performanceReviews() { return $this->hasMany(PerformanceReview::class); }
    public function kpis()          { return $this->hasMany(Kpi::class); }

    public function scopeActive($q) { return $q->where('status','active'); }
}
