<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AttendanceLog — records a single day's attendance for one employee.
 *
 * @property int         $id
 * @property int         $employee_id
 * @property \Carbon\Carbon $date
 * @property string|null $check_in        HH:MM:SS
 * @property string|null $check_out       HH:MM:SS
 * @property int|null    $total_minutes
 * @property string      $status          present|absent|late|half_day
 * @property string      $source          api|manual|biometric
 * @property string|null $ip_address
 * @property string|null $notes
 *
 * @property-read string $duration_label  Human-readable duration, e.g. "7h 30m"
 * @property-read bool   $is_complete     True when both check-in and check-out exist
 */
class AttendanceLog extends Model
{
    protected $fillable = [
        'employee_id',
        'date',
        'check_in',
        'check_out',
        'total_minutes',
        'status',
        'source',
        'ip_address',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    protected $appends = [
        'duration_label',
        'is_complete',
    ];

    // ── Relations ─────────────────────────────────────────────────────────

    /**
     * The employee this log belongs to.
     *
     * @return BelongsTo<Employee, AttendanceLog>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    // ── Accessors ─────────────────────────────────────────────────────────

    /**
     * Return a human-readable duration string from total_minutes.
     *
     * Examples:
     *  - null / 0     → "—"
     *  - 45           → "45m"
     *  - 90           → "1h 30m"
     *  - 480          → "8h 0m"
     *
     * @return string
     */
    public function getDurationLabelAttribute(): string
    {
        if (! $this->total_minutes) {
            return '—';
        }

        $hours   = (int) floor($this->total_minutes / 60);
        $minutes = $this->total_minutes % 60;

        if ($hours === 0) {
            return "{$minutes}m";
        }

        return "{$hours}h {$minutes}m";
    }

    /**
     * Whether the attendance record has both a check-in and a check-out.
     *
     * @return bool
     */
    public function getIsCompleteAttribute(): bool
    {
        return $this->check_in !== null && $this->check_out !== null;
    }
}
