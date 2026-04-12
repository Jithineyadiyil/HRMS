<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractRenewalRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Manages the 3-level contract renewal approval workflow.
 *
 * Approval chain: Manager (level 1) → HR Manager (level 2) → CEO/Super Admin (level 3)
 *
 * Endpoints:
 *   GET  /api/v1/contracts/renewals              — paginated list with filters
 *   GET  /api/v1/contracts/renewals/stats        — summary counts
 *   POST /api/v1/contracts/renewals              — manually create a renewal request
 *   GET  /api/v1/contracts/renewals/{id}         — single request
 *   POST /api/v1/contracts/renewals/{id}/approve — approve at current stage
 *   POST /api/v1/contracts/renewals/{id}/reject  — reject at current stage
 */
class ContractRenewalController extends Controller
{
    // ── List ──────────────────────────────────────────────────────────────

    /**
     * Return a paginated list of renewal requests.
     *
     * @param  Request      $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        $query = ContractRenewalRequest::with([
            'employee.department',
            'contract',
            'manager',
            'managerApprovedBy',
            'hrApprovedBy',
            'ceoApprovedBy',
            'rejectedBy',
        ])
        ->when($request->status, fn ($q) => $q->where('status', $request->status))
        ->when($request->employee_id, fn ($q) => $q->where('employee_id', $request->employee_id))
        ->when($request->search, fn ($q) => $q->whereHas('employee', fn ($e) =>
            $e->where(DB::raw("CONCAT(first_name,' ',last_name)"), 'like', "%{$request->search}%")
        ));

        // Department managers only see their team's renewals
        if ($user->hasRole('department_manager')) {
            $empId = $user->employee?->id;
            $query->whereHas('employee', fn ($q) => $q->where('manager_id', $empId));
        }

        return response()->json(
            $query->orderBy('created_at', 'desc')
                  ->paginate((int) ($request->per_page ?? 15))
                  ->through(fn ($r) => $this->formatRenewal($r))
        );
    }

    // ── Stats ─────────────────────────────────────────────────────────────

    /**
     * Return renewal request summary counts.
     *
     * @return JsonResponse
     */
    public function stats(): JsonResponse
    {
        $safe = fn (callable $fn) => rescue($fn, 0, false);

        return response()->json([
            'total'            => $safe(fn () => ContractRenewalRequest::count()),
            'pending'          => $safe(fn () => ContractRenewalRequest::where('status', 'pending')->count()),
            'manager_approved' => $safe(fn () => ContractRenewalRequest::where('status', 'manager_approved')->count()),
            'hr_approved'      => $safe(fn () => ContractRenewalRequest::where('status', 'hr_approved')->count()),
            'approved'         => $safe(fn () => ContractRenewalRequest::where('status', 'approved')->count()),
            'rejected'         => $safe(fn () => ContractRenewalRequest::where('status', 'rejected')->count()),
        ]);
    }

    // ── Create (manual) ───────────────────────────────────────────────────

    /**
     * Manually create a renewal request for a contract.
     *
     * @param  Request      $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'contract_id'         => 'required|exists:employee_contracts,id',
            'proposed_start_date' => 'required|date',
            'proposed_end_date'   => 'nullable|date|after:proposed_start_date',
            'proposed_salary'     => 'nullable|numeric|min:0',
            'proposed_type'       => 'nullable|in:full_time,part_time,contract,intern,probation,fixed_term,unlimited',
            'notes'               => 'nullable|string|max:1000',
        ]);

        $contract = Contract::with('employee.manager')->findOrFail($request->contract_id);

        // Prevent duplicate open requests
        $existing = ContractRenewalRequest::where('contract_id', $contract->id)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->exists();

        if ($existing) {
            return response()->json(['message' => 'An open renewal request already exists for this contract.'], 422);
        }

        $renewal = ContractRenewalRequest::create([
            'contract_id'         => $contract->id,
            'employee_id'         => $contract->employee_id,
            'reference'           => ContractRenewalRequest::generateReference(),
            'status'              => 'pending',
            'proposed_start_date' => $request->proposed_start_date,
            'proposed_end_date'   => $request->proposed_end_date,
            'proposed_salary'     => $request->proposed_salary ?? $contract->salary,
            'proposed_type'       => $request->proposed_type  ?? $contract->type,
            'manager_id'          => $contract->employee?->manager_id,
            'auto_generated'      => false,
            'notified_at'         => now(),
            'notes'               => $request->notes,
        ]);

        return response()->json([
            'message' => 'Renewal request created.',
            'renewal' => $this->formatRenewal($renewal->load(['employee', 'contract'])),
        ], 201);
    }

    // ── Show ──────────────────────────────────────────────────────────────

    /**
     * Return a single renewal request.
     *
     * @param  int          $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $renewal = ContractRenewalRequest::with([
            'employee.department', 'contract', 'manager',
            'managerApprovedBy', 'hrApprovedBy', 'ceoApprovedBy', 'rejectedBy', 'newContract',
        ])->findOrFail($id);

        return response()->json(['renewal' => $this->formatRenewal($renewal)]);
    }

    // ── Approve ───────────────────────────────────────────────────────────

    /**
     * Approve the renewal request at the current stage.
     *
     * Stage routing:
     *   pending          → manager_approved  (requires: department_manager or hr_manager or super_admin)
     *   manager_approved → hr_approved       (requires: hr_manager or hr_staff or super_admin)
     *   hr_approved      → approved          (requires: super_admin — CEO)
     *
     * When fully approved, a new contract is automatically created.
     *
     * @param  Request      $request
     * @param  int          $id
     * @return JsonResponse
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $renewal = ContractRenewalRequest::with('contract.employee')->findOrFail($id);
        $user    = auth()->user();

        $request->validate(['notes' => 'nullable|string|max:1000']);
        $notes = $request->notes ?? null;

        switch ($renewal->status) {
            case 'pending':
                if (! $user->hasAnyRole(['department_manager', 'hr_manager', 'hr_staff', 'super_admin'])) {
                    return response()->json(['message' => 'Only managers can approve at this stage.'], 403);
                }
                $renewal->update([
                    'status'               => 'manager_approved',
                    'manager_approved_by'  => $user->id,
                    'manager_approved_at'  => now(),
                    'manager_notes'        => $notes,
                ]);
                $message = 'Approved at manager level. Awaiting HR approval.';
                break;

            case 'manager_approved':
                if (! $user->hasAnyRole(['hr_manager', 'hr_staff', 'super_admin'])) {
                    return response()->json(['message' => 'Only HR can approve at this stage.'], 403);
                }
                $renewal->update([
                    'status'          => 'hr_approved',
                    'hr_approved_by'  => $user->id,
                    'hr_approved_at'  => now(),
                    'hr_notes'        => $notes,
                ]);
                $message = 'Approved by HR. Awaiting CEO approval.';
                break;

            case 'hr_approved':
                if (! $user->hasRole('super_admin')) {
                    return response()->json(['message' => 'Only the CEO (Super Admin) can give final approval.'], 403);
                }
                // Final approval — create the new contract
                $newContract = $this->createRenewedContract($renewal);
                $renewal->update([
                    'status'          => 'approved',
                    'ceo_approved_by' => $user->id,
                    'ceo_approved_at' => now(),
                    'ceo_notes'       => $notes,
                    'new_contract_id' => $newContract->id,
                ]);
                $message = 'Final approval granted by CEO. New contract has been created automatically.';
                break;

            default:
                return response()->json(['message' => "Cannot approve a request with status '{$renewal->status}'."], 422);
        }

        return response()->json([
            'message' => $message,
            'renewal' => $this->formatRenewal($renewal->fresh([
                'employee', 'contract', 'managerApprovedBy', 'hrApprovedBy', 'ceoApprovedBy', 'newContract',
            ])),
        ]);
    }

    // ── Reject ────────────────────────────────────────────────────────────

    /**
     * Reject the renewal request at the current stage.
     *
     * @param  Request      $request
     * @param  int          $id
     * @return JsonResponse
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $renewal = ContractRenewalRequest::findOrFail($id);
        $user    = auth()->user();

        $request->validate(['reason' => 'required|string|max:1000']);

        if (! in_array($renewal->status, ['pending', 'manager_approved', 'hr_approved'])) {
            return response()->json(['message' => "Cannot reject a request with status '{$renewal->status}'."], 422);
        }

        $stage = $renewal->current_stage;

        $renewal->update([
            'status'           => 'rejected',
            'rejected_by'      => $user->id,
            'rejected_at'      => now(),
            'rejected_stage'   => $stage,
            'rejection_reason' => $request->reason,
        ]);

        return response()->json([
            'message' => "Renewal request rejected at {$stage} stage.",
            'renewal' => $this->formatRenewal($renewal->fresh(['employee', 'contract', 'rejectedBy'])),
        ]);
    }

    // ── Internal helpers ──────────────────────────────────────────────────

    /**
     * Create a new contract from the approved renewal terms.
     *
     * @param  ContractRenewalRequest $renewal
     * @return Contract
     */
    private function createRenewedContract(ContractRenewalRequest $renewal): Contract
    {
        $original = $renewal->contract;

        return Contract::create([
            'employee_id'   => $renewal->employee_id,
            'reference'     => Contract::generateReference(),
            'type'          => $renewal->proposed_type  ?? $original->type,
            'status'        => 'active',
            'start_date'    => $renewal->proposed_start_date,
            'end_date'      => $renewal->proposed_end_date,
            'salary'        => $renewal->proposed_salary ?? $original->salary,
            'currency'      => $original->currency,
            'position'      => $original->position,
            'department_id' => $original->department_id,
            'terms'         => "Renewed from {$original->reference} via approval {$renewal->reference}.",
            'created_by'    => auth()->id(),
            'approved_by'   => auth()->id(),
            'approved_at'   => now(),
        ]);
    }

    /**
     * Format a renewal request for the API response.
     *
     * @param  ContractRenewalRequest $r
     * @return array<string, mixed>
     */
    private function formatRenewal(ContractRenewalRequest $r): array
    {
        return [
            'id'                  => $r->id,
            'reference'           => $r->reference,
            'status'              => $r->status,
            'current_stage'       => $r->current_stage,
            'progress'            => $r->progress,
            'auto_generated'      => $r->auto_generated,
            'notes'               => $r->notes,
            'proposed_start_date' => $r->proposed_start_date?->toDateString(),
            'proposed_end_date'   => $r->proposed_end_date?->toDateString(),
            'proposed_salary'     => $r->proposed_salary,
            'proposed_type'       => $r->proposed_type,
            'created_at'          => $r->created_at?->toDateTimeString(),
            'notified_at'         => $r->notified_at?->toDateTimeString(),

            'employee' => $r->employee ? [
                'id'         => $r->employee->id,
                'full_name'  => $r->employee->full_name,
                'code'       => $r->employee->employee_code,
                'department' => $r->employee->department?->name,
            ] : null,

            'contract' => $r->contract ? [
                'id'        => $r->contract->id,
                'reference' => $r->contract->reference,
                'end_date'  => $r->contract->end_date?->toDateString(),
                'type'      => $r->contract->type,
                'salary'    => $r->contract->salary,
            ] : null,

            'approvals' => [
                'manager' => [
                    'approved'    => (bool) $r->manager_approved_at,
                    'approved_by' => $r->managerApprovedBy?->name,
                    'approved_at' => $r->manager_approved_at?->toDateTimeString(),
                    'notes'       => $r->manager_notes,
                ],
                'hr' => [
                    'approved'    => (bool) $r->hr_approved_at,
                    'approved_by' => $r->hrApprovedBy?->name,
                    'approved_at' => $r->hr_approved_at?->toDateTimeString(),
                    'notes'       => $r->hr_notes,
                ],
                'ceo' => [
                    'approved'    => (bool) $r->ceo_approved_at,
                    'approved_by' => $r->ceoApprovedBy?->name,
                    'approved_at' => $r->ceo_approved_at?->toDateTimeString(),
                    'notes'       => $r->ceo_notes,
                ],
            ],

            'rejection' => $r->status === 'rejected' ? [
                'stage'     => $r->rejected_stage,
                'reason'    => $r->rejection_reason,
                'by'        => $r->rejectedBy?->name,
                'at'        => $r->rejected_at?->toDateTimeString(),
            ] : null,

            'new_contract_id' => $r->new_contract_id,
        ];
    }
}
