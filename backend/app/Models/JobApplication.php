<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class JobApplication extends Model {
    protected $fillable = ['job_posting_id','applicant_name','applicant_email','applicant_phone','cv_path','cover_letter_path','cover_letter_text','stage','hr_notes','expected_salary','available_from'];
    protected $casts = ['available_from'=>'date','expected_salary'=>'decimal:2'];
    public function jobPosting() { return $this->belongsTo(JobPosting::class); }
    public function interviews() { return $this->hasMany(Interview::class,'application_id'); }
}
