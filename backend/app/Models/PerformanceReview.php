<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIX: Removed SoftDeletes — the performance_reviews table (created by
 * 2024_01_01_000007) does not have a deleted_at column.
 *
 * FIX: Removed reference_no auto-generation — the actual DB table from
 * 2024_01_01_000007 does not have a reference_no column. The newer migration
 * (2024_01_20_000002) that added it was skipped because the table already existed.
 *
 * Actual DB columns: cycle_id, employee_id, reviewer_id, status,
 * self_rating, self_comments, self_kpi_scores, manager_rating, manager_comments,
 * manager_kpi_scores, final_rating, performance_band, development_plan, hr_notes.
 */
class PerformanceReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'cycle_id', 'employee_id', 'reviewer_id', 'status',
        'self_rating', 'self_comments', 'self_kpi_scores',
        'manager_rating', 'manager_comments', 'manager_kpi_scores',
        'final_rating', 'performance_band', 'development_plan', 'hr_notes',
    ];

    protected $casts = [
        'self_kpi_scores'     => 'array',
        'manager_kpi_scores'  => 'array',
        'self_rating'         => 'decimal:1',
        'manager_rating'      => 'decimal:1',
        'final_rating'        => 'decimal:1',
    ];

    public function cycle()    { return $this->belongsTo(PerformanceCycle::class, 'cycle_id'); }
    public function employee() { return $this->belongsTo(Employee::class); }
    public function reviewer() { return $this->belongsTo(Employee::class, 'reviewer_id'); }
}
