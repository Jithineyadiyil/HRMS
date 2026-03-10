<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobPosting extends Model {
    use SoftDeletes;
    protected $fillable = ['title','department_id','designation_id','employment_type','status','vacancies','description','requirements','benefits','salary_min','salary_max','location','closing_date','created_by'];
    protected $casts = ['closing_date'=>'date','salary_min'=>'decimal:2','salary_max'=>'decimal:2'];
    public function department() { return $this->belongsTo(Department::class); }
    public function designation() { return $this->belongsTo(Designation::class); }
    public function applications() { return $this->hasMany(JobApplication::class,'job_posting_id'); }
}
