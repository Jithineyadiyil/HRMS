<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ManpowerRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reference','requested_by','department_id','position_title',
        'headcount','approved_headcount','employment_type','urgency','reason',
        'expected_start_date','salary_min','salary_max','job_description',
        'requirements','notes','hr_notes','rejection_reason','status',
        'approved_by','approved_at','job_posting_created','job_posting_id',
    ];

    protected $casts = [
        'expected_start_date' => 'date',
        'approved_at'         => 'datetime',
        'job_posting_created' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function ($mp) {
            if (!$mp->reference) {
                $year  = now()->format('Y');
                $seq   = static::whereYear('created_at', $year)->count() + 1;
                $mp->reference = 'MPR-' . $year . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function requester()  { return $this->belongsTo(User::class, 'requested_by'); }
    public function department() { return $this->belongsTo(Department::class); }
    public function approver()   { return $this->belongsTo(User::class, 'approved_by'); }
}
