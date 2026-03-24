<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Contract extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'employee_id', 'contract_type', 'position',
        'start_date', 'end_date', 'probation_end',
        'salary', 'notes', 'is_active',
        'renewal_notified', 'renewal_requested',
    ];

    protected $casts = [
        'start_date'        => 'date',
        'end_date'          => 'date',
        'probation_end'     => 'date',
        'salary'            => 'decimal:2',
        'is_active'         => 'boolean',
        'renewal_notified'  => 'boolean',
        'renewal_requested' => 'boolean',
    ];

    // ── Relationships ─────────────────────────────────────────────────────

    public function employee()
    {
        return $this->belongsTo(Employee::class)->with('department');
    }

    public function renewals()
    {
        return $this->hasMany(ContractRenewal::class);
    }

    public function latestRenewal()
    {
        return $this->hasOne(ContractRenewal::class)->latestOfMany();
    }

    // ── Computed / Appends ────────────────────────────────────────────────

    protected $appends = ['days_left', 'status', 'has_pending_renewal'];

    public function getDaysLeftAttribute(): int
    {
        return (int) now()->startOfDay()->diffInDays(
            Carbon::parse($this->end_date)->startOfDay(),
            false
        );
    }

    public function getStatusAttribute(): string
    {
        if (!$this->is_active)       return 'inactive';
        if ($this->days_left < 0)    return 'expired';
        if ($this->days_left <= 60)  return 'expiring';
        return 'active';
    }

    public function getHasPendingRenewalAttribute(): bool
    {
        return $this->renewals()
            ->whereNotIn('status', ['approved', 'rejected'])
            ->exists();
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeExpiring($query, int $days = 60)
    {
        return $query->active()
            ->whereDate('end_date', '>=', now())
            ->whereDate('end_date', '<=', now()->addDays($days));
    }

    public function scopeExpired($query)
    {
        return $query->active()->whereDate('end_date', '<', now());
    }
}
