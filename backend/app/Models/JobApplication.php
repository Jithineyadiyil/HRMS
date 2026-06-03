<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class JobApplication extends Model {
    protected $fillable = [
        'job_posting_id','applicant_name','applicant_email','applicant_phone',
        'cv_path','cover_letter_path','cover_letter_text','stage','hr_notes',
        'expected_salary','available_from',
        // CV Bank fields
        'is_cv_bank','position_applied','nationality','experience_years',
        'skills','source','rating','notes',
    ];
    protected $casts = [
        'available_from'   => 'date',
        'expected_salary'  => 'decimal:2',
        'is_cv_bank'       => 'boolean',
        'experience_years' => 'integer',
    ];
    public function jobPosting()  { return $this->belongsTo(JobPosting::class); }
    public function interviews()  { return $this->hasMany(Interview::class,'application_id'); }
}
