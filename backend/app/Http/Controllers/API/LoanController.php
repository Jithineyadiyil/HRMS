<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanType;
use App\Services\LoanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Manages the full loan lifecycle: application, 3-stage approval,
 * disbursement, installment tracking, and closure.
 *
 * IMPORTANT: All role checks use raw DB queries to bypass the Spatie
 * Permission guard mismatch that occurs with Sanctum authentication.
 * Never use `$user->hasRole()` or `$user->hasAnyRole()` in this codebase.
 */
class LoanController extends Controller
{
    /**
     * @param  LoanService $service  Injected by the service container
     */
    public function __construct(protected LoanService $service) {}

    // ── Role helper ───────────────────────────────────────────────────────

    /**
     * Fetch the authenticated user's role names directly from the database,
     * bypassing Spatie's guard resolution which silently returns false when
     * the Sanctum guard does not match Spatie's 'web' guard.
     *
     * @return string[]
     */
    private function userRoles(): array
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', auth()->id())
            ->where('model_has_roles.model_type', get_class(auth()->user()))
            ->pluck('roles.name')
            ->toArray();
    }

    /**
     * Return true if the user has at least one of the given roles.
     *
     * @param  string[] $roles
     * @return bool
     */
    private function hasAnyRoleDB(array $roles): bool
    {
        return (bool) array_intersect($this->userRoles(), $roles);
    }

    // ── Loan Types ────────────────────────────────────────────────────────

    /**
     * Return active loan types available for new applications.
     *
     * @return JsonResponse
     */
    public function types(): JsonResponse
    {
        return response()->json(['types' => LoanType::where('is_active', true)->get()]);
    }

    /**
     * Return all loan types including inactive (for admin management).
     *
     * @return JsonResponse
     */
    public function allTypes(): JsonResponse
    {
        return response()->json(['types' => LoanType::orderBy('name')->get()]);
    }

    /**
     * Create a new loan type.
     *
     * @param  Request      $request
     * @return JsonResponse           201 with created type
     */
    public function storeType(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:100',
            'code'             => 'required|string|max:20|unique:loan_types',
            'max_amount'       => 'required|numeric|min:0',
            'max_installments' => 'required|integer|min:1|max:120',
            'interest_rate'    => 'nullable|numeric|min:0|max:100',
            'is_active'        => 'boolean',
        ]);

        return response()->json(['type' => LoanType::create($validated)], 201);
    }

    /**
     * Update an existing loan type.
     *
     * @param  Request      $request
     * @param  int          $id
     * @return JsonResponse
     */
    public function updateType(Request $request, int $id): JsonResponse
    {
        $type = LoanType::findOrFail($id);

        $validated = $request->validate([
            'name'             => 'sometimes|string|max:100',
            'max_amount'       => 'sometimes|numeric|min:0',
            'max_installments' => 'sometimes|integer|min:1|max:120',
            'interest_rate'    => 'nullable|numeric|min:0|max:100',
            'is_active'        => 'boolean',
        ]);

        $type->update($validated);

        return response()->json(['type' => $type]);
    }

    // ── Stats ─────────────────────────────────────────────────────────────

    /**
     * Return loan summary statistics.
     *
     * @return JsonResponse
     */
    public function stats(): JsonResponse
    {
        return response()->json($this->service->stats());
    }

    // ── List Loans ────────────────────────────────────────────────────────

    /**
     * Return a paginated list of loans, scoped by the caller's role.
     *
     * FIX: Previously used `$user->hasRole()` which silently returns false
     * under Sanctum.  Now uses raw DB role lookup via `userRoles()`.
     *
     * @param  Request      $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user    = auth()->user();
        // FIX: Use raw DB query instead of Spatie hasRole() to avoid guard mismatch
        $isAdmin = $this->hasAnyRoleDB(['super_admin', 'hr_manager', 'finance_manager']);
        $isMgr   = $this->hasAnyRoleDB(['department_manager']);

        $query = Loan::with(['employee.department', 'loanType'])
            ->when(!$isAdmin, function ($q) use ($user, $isMgr) {
                if ($isMgr && $user->employee) {
                    $teamIds = $user->employee->subordinates()->pluck('id');
                    $q->whereIn('employee_id', $teamIds->push($user->employee->id));
                } elseif ($user->employee) {
                    $q->where('employee_id', $user->employee->id);
                }
            })
            ->when($request->status,       fn ($q) => $q->where('status', $request->status))
            ->when($request->loan_type_id, fn ($q) => $q->where('loan_type_id', $request->loan_type_id))
            ->when($request->search, fn ($q) =>
                $q->whereHas('employee', fn ($eq) =>
                    $eq->where('first_name', 'like', "%{$request->search}%")
                       ->orWhere('last_name',  'like', "%{$request->search}%")
                       ->orWhere('employee_code', 'like', "%{$request->search}%")
                )
            )
            ->orderBy('created_at', 'desc');

        return response()->json($query->paginate(15));
    }

    // ── Create Loan Request ───────────────────────────────────────────────

    /**
     * Submit a new loan application.
     *
     * @param  Request      $request
     * @return JsonResponse           201 with created loan
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'loan_type_id'     => 'required|exists:loan_types,id',
            'requested_amount' => 'required|numeric|min:100',
            'installments'     => 'required|integer|min:1|max:120',
            'purpose'          => 'required|string|min:10',
            'notes'            => 'nullable|string|max:1000',
        ]);

        $employee = auth()->user()->employee;
        $loanType = LoanType::findOrFail($request->loan_type_id);

        if ($loanType->max_amount > 0 && $request->requested_amount > $loanType->max_amount) {
            return response()->json([
                'message' => "Amount exceeds maximum allowed ({$loanType->max_amount} SAR) for this loan type.",
            ], 422);
        }

        if ($request->installments > $loanType->max_installments) {
            return response()->json([
                'message' => "Maximum installments for this loan type is {$loanType->max_installments}.",
            ], 422);
        }

        $active = Loan::where('employee_id', $employee->id)
            ->where('loan_type_id', $request->loan_type_id)
            ->whereIn('status', ['pending_manager', 'pending_hr', 'pending_finance', 'approved', 'disbursed'])
            ->exists();

        if ($active) {
            return response()->json(['message' => 'You already have an active loan of this type.'], 422);
        }

        $monthly = $this->service->calculateMonthlyInstallment(
            $request->requested_amount,
            $request->installments,
            $loanType->interest_rate ?? 0
        );

        $loan = Loan::create([
            'reference'           => $this->service->generateReference(),
            'employee_id'         => $employee->id,
            'loan_type_id'        => $request->loan_type_id,
            'requested_amount'    => $request->requested_amount,
            'installments'        => $request->installments,
            'monthly_installment' => $monthly,
            'purpose'             => $request->purpose,
            'notes'               => $request->notes,
            'status'              => 'pending_manager',
        ]);

        return response()->json(['message' => 'Loan request submitted.', 'loan' => $loan->load('loanType')], 201);
    }

    // ── Show single loan ──────────────────────────────────────────────────

    /**
     * Return a single loan with full detail including installment schedule.
     *
     * @param  int          $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $loan = Loan::with([
            'employee.department', 'loanType',
            'installments.processedBy',
            'managerApprover', 'hrApprover', 'financeApprover', 'rejectedBy',
        ])->findOrFail($id);

        $data = $loan->toArray();
        $data['installment_schedule'] = $data['installments'];
        $data['installments']         = $loan->getRawOriginal('installments');

        return response()->json(['loan' => $data]);
    }

    // ── Approve ───────────────────────────────────────────────────────────

    /**
     * Advance a loan through its approval stages.
     *
     * Stage flow: pending_manager → pending_hr → pending_finance → approved
     * Finance approval triggers installment schedule generation.
     *
     * @param  Request      $request
     * @param  int          $id
     * @return JsonResponse
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $loan = Loan::findOrFail($id);
        $user = auth()->user();

        switch ($loan->status) {
            case 'pending_manager':
                $loan->update([
                    'status'              => 'pending_hr',
                    'manager_approved_by' => $user->id,
                    'manager_approved_at' => now(),
                ]);
                break;

            case 'pending_hr':
                $loan->update([
                    'status'         => 'pending_finance',
                    'hr_approved_by' => $user->id,
                    'hr_approved_at' => now(),
                ]);
                break;

            case 'pending_finance':
                $request->validate([
                    'approved_amount'        => 'nullable|numeric|min:1',
                    'disbursed_date'         => 'nullable|date',
                    'first_installment_date' => 'nullable|date',
                ]);

                $approvedAmt = $request->approved_amount ?? $loan->requested_amount;
                $monthly     = $this->service->calculateMonthlyInstallment(
                    $approvedAmt,
                    $loan->installments,
                    $loan->loanType->interest_rate ?? 0
                );

                $loan->update([
                    'status'                 => 'approved',
                    'finance_approved_by'    => $user->id,
                    'finance_approved_at'    => now(),
                    'approved_amount'        => $approvedAmt,
                    'monthly_installment'    => $monthly,
                    'balance_remaining'      => $approvedAmt,
                    'disbursed_date'         => $request->disbursed_date ?? now()->toDateString(),
                    'first_installment_date' => $request->first_installment_date
                                             ?? now()->addMonth()->startOfMonth()->toDateString(),
                ]);

                $loan->refresh();
                $this->service->generateInstallments($loan);
                break;

            default:
                return response()->json(['message' => 'Loan is not in an approvable state.'], 422);
        }

        return response()->json(['message' => 'Loan approved.', 'loan' => $loan->fresh('loanType')]);
    }

    // ── Reject ────────────────────────────────────────────────────────────

    /**
     * Reject a loan at the current stage.
     *
     * @param  Request      $request  Requires reason
     * @param  int          $id
     * @return JsonResponse
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $request->validate(['reason' => 'required|string|min:5']);

        $loan  = Loan::findOrFail($id);
        $stage = match ($loan->status) {
            'pending_manager' => 'manager',
            'pending_hr'      => 'hr',
            'pending_finance' => 'finance',
            default           => null,
        };

        if (!$stage) {
            return response()->json(['message' => 'Loan cannot be rejected at this stage.'], 422);
        }

        $loan->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->reason,
            'rejected_by'      => auth()->id(),
            'rejected_at'      => now(),
            'rejected_stage'   => $stage,
        ]);

        return response()->json(['message' => 'Loan rejected.']);
    }

    // ── Cancel ────────────────────────────────────────────────────────────

    /**
     * Allow an employee to cancel their own pending loan application.
     *
     * @param  int          $id
     * @return JsonResponse
     */
    public function cancel(int $id): JsonResponse
    {
        $loan = Loan::findOrFail($id);

        if (!in_array($loan->status, ['pending_manager', 'pending_hr', 'pending_finance'])) {
            return response()->json(['message' => 'Loan cannot be cancelled at this stage.'], 422);
        }

        $loan->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Loan request cancelled.']);
    }

    // ── Disburse ──────────────────────────────────────────────────────────

    /**
     * Mark a loan as disbursed.
     *
     * @param  Request      $request  Optional disbursed_date
     * @param  int          $id
     * @return JsonResponse
     */
    public function disburse(Request $request, int $id): JsonResponse
    {
        $loan = Loan::findOrFail($id);

        if ($loan->status !== 'approved') {
            return response()->json(['message' => 'Loan must be approved before disbursement.'], 422);
        }

        $loan->update([
            'status'        => 'disbursed',
            'disbursed_date'=> $request->disbursed_date ?? now()->toDateString(),
        ]);

        return response()->json(['message' => 'Loan marked as disbursed.']);
    }

    // ── Installments ──────────────────────────────────────────────────────

    /**
     * Mark an installment as paid.
     *
     * @param  Request $request   Optional paid_date, notes
     * @param  int     $loanId
     * @param  int     $instId
     * @return JsonResponse
     */
    public function payInstallment(Request $request, int $loanId, int $instId): JsonResponse
    {
        $inst = LoanInstallment::where('loan_id', $loanId)->findOrFail($instId);

        if (!in_array($inst->status, ['pending', 'overdue'])) {
            return response()->json(['message' => 'Installment is not payable.'], 422);
        }

        $this->service->payInstallment($inst, $request->paid_date, $request->notes);

        return response()->json(['message' => 'Installment marked as paid.']);
    }

    /**
     * Skip an installment and reschedule it to the end of the loan.
     *
     * @param  Request $request  Optional notes
     * @param  int     $loanId
     * @param  int     $instId
     * @return JsonResponse
     */
    public function skipInstallment(Request $request, int $loanId, int $instId): JsonResponse
    {
        $inst = LoanInstallment::where('loan_id', $loanId)->findOrFail($instId);

        if (!in_array($inst->status, ['pending', 'overdue'])) {
            return response()->json(['message' => 'Installment cannot be skipped.'], 422);
        }

        $this->service->skipInstallment($inst, $request->notes);

        return response()->json(['message' => 'Installment skipped — rescheduled to end of loan.']);
    }

    /**
     * Mark all overdue installments (for cron job use).
     *
     * @return JsonResponse
     */
    public function markOverdue(): JsonResponse
    {
        $count = $this->service->markOverdue();

        return response()->json(['message' => "{$count} installments marked as overdue."]);
    }

    // ── My Loans ──────────────────────────────────────────────────────────

    /**
     * Return the authenticated employee's loan list.
     *
     * @return JsonResponse
     */
    public function myLoans(): JsonResponse
    {
        $employee = auth()->user()->employee;

        if (!$employee) {
            return response()->json(['loans' => []]);
        }

        $loans = Loan::with(['loanType'])
            ->where('employee_id', $employee->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (Loan $loan) {
                $data = $loan->toArray();
                $data['total_installments'] = $loan->getRawOriginal('installments');
                return $data;
            });

        return response()->json(['loans' => $loans]);
    }
}
