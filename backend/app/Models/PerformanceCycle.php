<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PerformanceCycle extends Model {
    protected $fillable = ['name','type','start_date','end_date','self_assessment_deadline','manager_review_deadline','status'];
    protected $casts = ['start_date'=>'date','end_date'=>'date'];
    public function reviews() { return $this->hasMany(PerformanceReview::class,'cycle_id'); }
}
