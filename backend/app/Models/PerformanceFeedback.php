<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class PerformanceFeedback extends Model {
    use SoftDeletes;
    protected $fillable = ['subject_employee_id','reviewer_id','review_id','relationship',
        'is_anonymous','communication','teamwork','technical','leadership','initiative',
        'strengths','improvements','overall_comment','submitted_at'];
    protected $casts = ['is_anonymous'=>'boolean','submitted_at'=>'datetime'];
    public function subject()  { return $this->belongsTo(Employee::class, 'subject_employee_id'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewer_id'); }
    public function review()   { return $this->belongsTo(PerformanceReview::class, 'review_id'); }
    public function getAvgScoreAttribute(): float {
        $fields = ['communication','teamwork','technical','leadership','initiative'];
        $vals = array_filter(array_map(fn($f) => $this->$f, $fields));
        return count($vals) ? round(array_sum($vals)/count($vals), 1) : 0;
    }
    protected $appends = ['avg_score'];
}
