<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class PerformanceCycle extends Model {
    use SoftDeletes;
    protected $fillable = ['name','type','review_period','start_date','end_date',
        'self_assessment_deadline','manager_review_deadline','include_360','status','description','created_by'];
    protected $casts = ['start_date'=>'date','end_date'=>'date',
        'self_assessment_deadline'=>'date','manager_review_deadline'=>'date','include_360'=>'boolean'];
    public function reviews() { return $this->hasMany(PerformanceReview::class, 'cycle_id'); }
    public function getParticipantsCountAttribute() { return $this->reviews()->distinct('employee_id')->count(); }
    public function getReviewsCountAttribute()      { return $this->reviews()->count(); }
    protected $appends = ['participants_count','reviews_count'];
}
