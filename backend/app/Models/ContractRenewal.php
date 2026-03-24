<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractRenewal extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'contract_id', 'proposed_start_date', 'proposed_end_date',
        'status', 'rejected_at_stage', 'rejection_reason',
        'requested_by', 'auto_created',
        'manager_approver_id', 'hr_approver_id', 'ceo_approver_id',
        'manager_approved_at', 'hr_approved_at', 'ceo_approved_at',
        'rejected_at', 'notes',
    ];

    protected $casts = [
        'proposed_start_date'  => 'date',
        'proposed_end_date'    => 'date',
        'auto_created'         => 'boolean',
        'manager_approved_at'  => 'datetime',
        'hr_approved_at'       => 'datetime',
        'ceo_approved_at'      => 'datetime',
        'rejected_at'          => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────

    public function contract()
    {
        return $this->belongsTo(Contract::class)->with(['employee.department']);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function managerApprover()
    {
        return $this->belongsTo(User::class, 'manager_approver_id');
    }

    public function hrApprover()
    {
        return $this->belongsTo(User::class, 'hr_approver_id');
    }

    public function ceoApprover()
    {
        return $this->belongsTo(User::class, 'ceo_approver_id');
    }

    // ── Stage helpers ─────────────────────────────────────────────────────

    public function isActionable(): bool
    {
        return !in_array($this->status, ['approved', 'rejected']);
    }

    public function nextStage(): ?string
    {
        return match($this->status) {
            'pending_manager' => 'pending_hr',
            'pending_hr'      => 'pending_ceo',
            'pending_ceo'     => 'approved',
            default           => null,
        };
    }
}
