<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FIX: Removed SoftDeletes — the performance_cycles table (created by
 * 2024_01_01_000007) does not have a deleted_at column, so SoftDeletes
 * caused every query to fail with "Unknown column 'deleted_at'".
 */
class PerformanceCycle extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'type', 'review_period', 'start_date', 'end_date',
        'self_assessment_deadline', 'manager_review_deadline',
        'include_360', 'status', 'description', 'created_by',
    ];

    protected $casts = [
        'start_date'                  => 'date',
        'end_date'                    => 'date',
        'self_assessment_deadline'    => 'date',
        'manager_review_deadline'     => 'date',
        'include_360'                 => 'boolean',
    ];

    public function reviews() { return $this->hasMany(PerformanceReview::class, 'cycle_id'); }
}
