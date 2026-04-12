<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int         $id
 * @property int         $employee_id
 * @property string      $reference
 * @property string      $type
 * @property string      $status
 * @property string      $start_date
 * @property string|null $end_date
 * @property float|null  $salary
 * @property string      $currency
 * @property string|null $position
 * @property int|null    $department_id
 * @property string|null $terms
 * @property string|null $pdf_path
 */
class Contract extends Model
{
    use SoftDeletes;

    protected $table = 'employee_contracts';

    protected $fillable = [
        'employee_id', 'reference', 'type', 'status',
        'start_date', 'end_date', 'salary', 'currency',
        'position', 'department_id', 'terms', 'pdf_path',
        'created_by', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'start_date'  => 'date',
        'end_date'    => 'date',
        'approved_at' => 'datetime',
        'salary'      => 'decimal:2',
    ];

    // ── Relationships ──────────────────────────────────────────────────

    /** @return BelongsTo<Employee, Contract> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<Department, Contract> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<User, Contract> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, Contract> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────

    /** @param \Illuminate\Database\Eloquent\Builder $q */
    public function scopeActive($q): mixed
    {
        return $q->where('status', 'active');
    }

    /** @param \Illuminate\Database\Eloquent\Builder $q */
    public function scopeExpiringSoon($q, int $days = 30): mixed
    {
        return $q->where('status', 'active')
                 ->whereNotNull('end_date')
                 ->whereBetween('end_date', [now(), now()->addDays($days)]);
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /**
     * Generate a unique contract reference number.
     *
     * @return string  e.g. CTR-2024-00042
     */
    public static function generateReference(): string
    {
        $year  = now()->year;
        $last  = static::withTrashed()->whereYear('created_at', $year)->count();
        return sprintf('CTR-%d-%05d', $year, $last + 1);
    }

    /**
     * Check whether this contract has expired.
     *
     * @return bool
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->end_date && $this->end_date->isPast() && $this->status === 'active';
    }
}
