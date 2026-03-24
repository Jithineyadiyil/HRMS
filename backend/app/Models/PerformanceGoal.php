<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class PerformanceGoal extends Model {
    use SoftDeletes;
    protected $fillable = ['employee_id','review_id','title','description','category','priority',
        'status','target_value','current_value','unit','start_date','due_date','achieved_at','created_by'];
    protected $casts = ['start_date'=>'date','due_date'=>'date','achieved_at'=>'date'];
    public function employee() { return $this->belongsTo(Employee::class); }
    public function review()   { return $this->belongsTo(PerformanceReview::class, 'review_id'); }
    public function getProgressAttribute(): int {
        if (!$this->target_value || $this->target_value == 0) return 0;
        return min(100, (int) round(($this->current_value / $this->target_value) * 100));
    }
    protected $appends = ['progress'];
}
