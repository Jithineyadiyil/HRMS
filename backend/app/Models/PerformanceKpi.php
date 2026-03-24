<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class PerformanceKpi extends Model {
    use SoftDeletes;
    protected $fillable = ['employee_id','review_id','name','description','target','actual',
        'unit','period','frequency','weight','status','created_by'];
    public function employee() { return $this->belongsTo(Employee::class); }
    public function getAchievementAttribute(): int {
        if (!$this->target || $this->target == 0) return 0;
        return min(200, (int) round(($this->actual / $this->target) * 100));
    }
    protected $appends = ['achievement'];
}
