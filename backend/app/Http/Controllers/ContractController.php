<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\ContractRenewal;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ContractController extends Controller
{
    // ── GET /contracts ────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $q = Contract::with(['employee.department', 'latestRenewal'])
            ->when($request->search, fn($q, $s) =>
                $q->whereHas('employee', fn($e) =>
                    $e->where('first_name', 'like', "%$s%")
                      ->orWhere('last_name',  'like', "%$s%")
                      ->orWhere('employee_code', 'like', "%$s%")
                )
            )
            ->when($request->department_id, fn($q, $d) =>
                $q->whereHas('employee', fn($e) => $e->where('department_id', $d))
            )
            ->when($request->status === 'active',   fn($q) => $q->active()->where('end_date', '>=', now()))
            ->when($request->status === 'expired',  fn($q) => $q->expired())
            ->when($request->status === 'expiring', fn($q) => $q->expiring())
            ->when($request->expiring,              fn($q) => $q->expiring())
            ->orderBy('end_date', 'asc');

        return $q->paginate($request->per_page ?? 15);
    }

    // ── POST /contracts ───────────────────────────────────────────────────
    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id'   => 'required|exists:employees,id',
            'contract_type' => 'required|in:fixed,unlimited,part_time,freelance',
            'position'      => 'nullable|string|max:255',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date|after:start_date',
            'probation_end' => 'nullable|date|after:start_date|before:end_date',
            'salary'        => 'nullable|numeric|min:0',
            'notes'         => 'nullable|string',
            'is_active'     => 'boolean',
        ]);

        $contract = Contract::create($data);

        return response()->json([
            'message'  => 'Contract created successfully.',
            'contract' => $contract->load('employee.department'),
        ], 201);
    }

    // ── GET /contracts/{id} ───────────────────────────────────────────────
    public function show(Contract $contract)
    {
        return response()->json([
            'contract' => $contract->load(['employee.department', 'renewals.requester']),
        ]);
    }

    // ── PUT /contracts/{id} ───────────────────────────────────────────────
    public function update(Request $request, Contract $contract)
    {
        $data = $request->validate([
            'contract_type' => 'sometimes|in:fixed,unlimited,part_time,freelance',
            'position'      => 'nullable|string|max:255',
            'start_date'    => 'sometimes|date',
            'end_date'      => 'sometimes|date|after:start_date',
            'probation_end' => 'nullable|date',
            'salary'        => 'nullable|numeric|min:0',
            'notes'         => 'nullable|string',
            'is_active'     => 'boolean',
        ]);

        $contract->update($data);

        return response()->json([
            'message'  => 'Contract updated.',
            'contract' => $contract->fresh('employee.department'),
        ]);
    }

    // ── GET /contracts/stats ──────────────────────────────────────────────
    public function stats()
    {
        return response()->json([
            'active_count'    => Contract::active()->where('end_date', '>=', now())->count(),
            'expiring_count'  => Contract::expiring(60)->count(),
            'expired_count'   => Contract::expired()->count(),
            'pending_renewals'=> ContractRenewal::whereNotIn('status', ['approved','rejected'])->count(),
        ]);
    }

    // ── POST /contracts/{id}/trigger-renewal ─────────────────────────────
    // Manually create a renewal request
    public function triggerRenewal(Contract $contract)
    {
        // Prevent duplicate open renewals
        $existing = $contract->renewals()
            ->whereNotIn('status', ['approved', 'rejected'])
            ->first();

        if ($existing) {
            return response()->json(['message' => 'A renewal request is already in progress.'], 422);
        }

        $renewal = ContractRenewal::create([
            'contract_id'         => $contract->id,
            'status'              => 'pending_manager',
            'requested_by'        => Auth::id(),
            'auto_created'        => false,
            // Proposed dates: next 1-year cycle
            'proposed_start_date' => Carbon::parse($contract->end_date)->addDay(),
            'proposed_end_date'   => Carbon::parse($contract->end_date)->addYear(),
        ]);

        $contract->update(['renewal_requested' => true]);

        return response()->json([
            'message' => 'Renewal request created and sent to manager.',
            'renewal' => $renewal,
        ], 201);
    }

    // ══════════════════════════════════════════════════════════════════════
    // RENEWALS
    // ══════════════════════════════════════════════════════════════════════

    // ── GET /contracts/renewals ───────────────────────────────────────────
    public function renewals(Request $request)
    {
        $q = ContractRenewal::with(['contract.employee.department', 'requester',
                'managerApprover', 'hrApprover', 'ceoApprover'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->search, fn($q, $s) =>
                $q->whereHas('contract.employee', fn($e) =>
                    $e->where('first_name', 'like', "%$s%")
                      ->orWhere('last_name',  'like', "%$s%")
                )
            )
            ->orderBy('created_at', 'desc');

        return $q->paginate($request->per_page ?? 15);
    }

    // ── GET /contracts/renewals/{id} ──────────────────────────────────────
    public function showRenewal(ContractRenewal $renewal)
    {
        return response()->json([
            'renewal' => $renewal->load([
                'contract.employee.department',
                'requester', 'managerApprover', 'hrApprover', 'ceoApprover',
            ]),
        ]);
    }

    // ── POST /contracts/renewals/{id}/approve ─────────────────────────────
    public function approveRenewal(ContractRenewal $renewal)
    {
        if (!$renewal->isActionable()) {
            return response()->json(['message' => 'This renewal is already finalised.'], 422);
        }

        $user     = Auth::user();
        $nextStage = $renewal->nextStage();

        // Record the approver for the current stage
        $updates = [];
        switch ($renewal->status) {
            case 'pending_manager':
                $updates['manager_approver_id'] = $user->id;
                $updates['manager_approved_at']  = now();
                break;
            case 'pending_hr':
                $updates['hr_approver_id']  = $user->id;
                $updates['hr_approved_at']   = now();
                break;
            case 'pending_ceo':
                $updates['ceo_approver_id'] = $user->id;
                $updates['ceo_approved_at']  = now();
                break;
        }

        $updates['status'] = $nextStage ?? 'approved';

        // When fully approved, create the new contract
        if ($updates['status'] === 'approved') {
            $old = $renewal->contract;
            Contract::create([
                'employee_id'   => $old->employee_id,
                'contract_type' => $old->contract_type,
                'position'      => $old->position,
                'start_date'    => $renewal->proposed_start_date,
                'end_date'      => $renewal->proposed_end_date,
                'salary'        => $old->salary,
                'notes'         => 'Auto-renewed from contract #' . $old->id,
                'is_active'     => true,
            ]);
            // Deactivate old contract
            $old->update(['is_active' => false]);
        }

        $renewal->update($updates);

        $stageLabels = [
            'pending_hr'  => 'forwarded to HR',
            'pending_ceo' => 'forwarded to CEO',
            'approved'    => 'fully approved — new contract created',
        ];

        return response()->json([
            'message' => 'Renewal ' . ($stageLabels[$updates['status']] ?? 'updated') . '.',
            'renewal' => $renewal->fresh(),
        ]);
    }

    // ── POST /contracts/renewals/{id}/reject ──────────────────────────────
    public function rejectRenewal(Request $request, ContractRenewal $renewal)
    {
        $request->validate(['reason' => 'required|string|min:5']);

        if (!$renewal->isActionable()) {
            return response()->json(['message' => 'This renewal is already finalised.'], 422);
        }

        $renewal->update([
            'status'             => 'rejected',
            'rejected_at_stage'  => $renewal->status,
            'rejection_reason'   => $request->reason,
            'rejected_at'        => now(),
        ]);

        // Allow a new renewal to be triggered
        $renewal->contract->update(['renewal_requested' => false]);

        return response()->json(['message' => 'Renewal rejected.']);
    }
}
