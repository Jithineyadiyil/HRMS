<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PerformanceReview extends Model {
    protected $fillable = ['cycle_id','employee_id','reviewer_id','status','self_rating','self_comments','self_kpi_scores','manager_rating','manager_comments','manager_kpi_scores','final_rating','performance_band','development_plan','hr_notes'];
    protected $casts = ['self_kpi_scores'=>'array','manager_kpi_scores'=>'array'];
    public function cycle() { return $this->belongsTo(PerformanceCycle::class); }
    public function employee() { return $this->belongsTo(Employee::class); }
    public function reviewer() { return $this->belongsTo(Employee::class,'reviewer_id'); }
}
