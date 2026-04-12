<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a contract renewal request with 3-level approval workflow.
 *
 * Approval chain: Manager → HR Manager → CEO (super_admin)
 *
 * @property int         $id
 * @property int         $contract_id
 * @property int         $employee_id
 * @property string      $reference
 * @property string      $status
 * @property string      $proposed_start_date
 * @property string|null $proposed_end_date
 * @property float|null  $proposed_salary
 * @property string|null $proposed_type
 * @property string|null $notes
 */
class ContractRenewalRequest extends Model
{
    use SoftDeletes;

    protected $table = 'contract_renewal_requests';

    protected $fillable = [
        'contract_id', 'employee_id', 'reference', 'status',
        'proposed_start_date', 'proposed_end_date', 'proposed_salary',
        'proposed_type', 'notes',
        'manager_id', 'manager_approved_by', 'manager_approved_at', 'manager_notes',
        'hr_approved_by',  'hr_approved_at',  'hr_notes',
        'ceo_approved_by', 'ceo_approved_at', 'ceo_notes',
        'rejected_by', 'rejected_at', 'rejected_stage', 'rejection_reason',
        'new_contract_id', 'auto_generated', 'notified_at',
    ];

    protected $casts = [
        'proposed_start_date' => 'date',
        'proposed_end_date'   => 'date',
        'proposed_salary'     => 'decimal:2',
        'manager_approved_at' => 'datetime',
        'hr_approved_at'      => 'datetime',
        'ceo_approved_at'     => 'datetime',
        'rejected_at'         => 'datetime',
        'notified_at'         => 'datetime',
        'auto_generated'      => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────

    /** @return BelongsTo<Contract, ContractRenewalRequest> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /** @return BelongsTo<Employee, ContractRenewalRequest> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<Employee, ContractRenewalRequest> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    /** @return BelongsTo<User, ContractRenewalRequest> */
    public function managerApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_approved_by');
    }

    /** @return BelongsTo<User, ContractRenewalRequest> */
    public function hrApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_approved_by');
    }

    /** @return BelongsTo<User, ContractRenewalRequest> */
    public function ceoApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ceo_approved_by');
    }

    /** @return BelongsTo<User, ContractRenewalRequest> */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /** @return BelongsTo<Contract, ContractRenewalRequest> */
    public function newContract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'new_contract_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /**
     * Generate a unique renewal reference number.
     *
     * @return string  e.g. RNW-2024-00012
     */
    public static function generateReference(): string
    {
        $year = now()->year;
        $last = static::withTrashed()->whereYear('created_at', $year)->count();
        return sprintf('RNW-%d-%05d', $year, $last + 1);
    }

    /**
     * Determine which approval stage is currently pending.
     *
     * @return string  'manager' | 'hr' | 'ceo' | 'done' | 'rejected'
     */
    public function getCurrentStageAttribute(): string
    {
        return match ($this->status) {
            'pending'          => 'manager',
            'manager_approved' => 'hr',
            'hr_approved'      => 'ceo',
            'approved'         => 'done',
            default            => 'rejected',
        };
    }

    /**
     * Progress percentage (0, 33, 66, 100).
     *
     * @return int
     */
    public function getProgressAttribute(): int
    {
        return match ($this->status) {
            'pending'          => 0,
            'manager_approved' => 33,
            'hr_approved'      => 66,
            'approved'         => 100,
            default            => 0,
        };
    }
}
