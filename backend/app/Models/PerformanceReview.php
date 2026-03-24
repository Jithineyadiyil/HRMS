<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class PerformanceReview extends Model {
    use SoftDeletes;
    protected $fillable = ['reference_no','employee_id','reviewer_id','cycle_id','review_type',
        'review_period','due_date','status','self_score','manager_score','feedback_360_score',
        'final_score','self_assessment','manager_evaluation','notes','self_submitted_at',
        'manager_submitted_at','created_by'];
    protected $casts = ['due_date'=>'date','self_submitted_at'=>'datetime',
        'manager_submitted_at'=>'datetime','self_assessment'=>'array','manager_evaluation'=>'array'];
    public function employee() { return $this->belongsTo(Employee::class); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewer_id'); }
    public function cycle()    { return $this->belongsTo(PerformanceCycle::class, 'cycle_id'); }
    public function goals()    { return $this->hasMany(PerformanceGoal::class, 'review_id'); }
    public function kpis()     { return $this->hasMany(PerformanceKpi::class, 'review_id'); }
    public function feedback()  { return $this->hasMany(PerformanceFeedback::class, 'review_id'); }
    protected static function boot() {
        parent::boot();
        static::creating(function ($m) {
            $m->reference_no = 'PRV-' . date('Y') . '-' . str_pad(static::withTrashed()->count()+1, 4, '0', STR_PAD_LEFT);
        });
    }
}
