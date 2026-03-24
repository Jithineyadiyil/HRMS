<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanType;
use App\Models\LoanInstallment;
use App\Services\LoanService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class LoanController extends Controller
{
    public function __construct(protected LoanService $service) {}

    // ── Loan Types CRUD ───────────────────────────────────────────────────
    public function types()
    {
        return response()->json(['types' => LoanType::where('is_active', true)->get()]);
    }

    public function allTypes()
    {
        return response()->json(['types' => LoanType::orderBy('name')->get()]);
    }

    public function storeType(Request $request)
    {
        $request->validate([
            'name'             => 'required|string|max:100',
            'code'             => 'required|string|max:20|unique:loan_types',
            'max_amount'       => 'required|numeric|min:0',
            'max_installments' => 'required|integer|min:1|max:120',
            'interest_rate'    => 'nullable|numeric|min:0|max:100',
        ]);
        return response()->json(['type' => LoanType::create($request->all())], 201);
    }

    public function updateType(Request $request, $id)
    {
        $type = LoanType::findOrFail($id);
        $type->update($request->all());
        return response()->json(['type' => $type]);
    }

    // ── Stats ─────────────────────────────────────────────────────────────
    public function stats()
    {
        return response()->json($this->service->stats());
    }

    // ── List Loans ────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $user    = auth()->user();
        $isAdmin = $user->hasRole(['super_admin','hr_manager','finance_manager']);

        $query = Loan::with(['employee.department','loanType'])
            ->when(!$isAdmin, function ($q) use ($user) {
                if ($user->hasRole('manager') && $user->employee) {
                    $teamIds = $user->employee->subordinates()->pluck('id');
                    $q->whereIn('employee_id', $teamIds->push($user->employee->id));
                } elseif ($user->employee) {
                    $q->where('employee_id', $user->employee->id);
                }
            })
            ->when($request->status,        fn($q) => $q->where('status', $request->status))
            ->when($request->loan_type_id,  fn($q) => $q->where('loan_type_id', $request->loan_type_id))
            ->when($request->search, fn($q) =>
                $q->whereHas('employee', fn($eq) =>
                    $eq->where('first_name','like',"%{$request->search}%")
                      ->orWhere('last_name','like',"%{$request->search}%")
                      ->orWhere('employee_code','like',"%{$request->search}%")
                )
            )
            ->orderBy('created_at','desc');

        return response()->json($query->paginate(15));
    }

    // ── Create Loan Request ───────────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'loan_type_id'    => 'required|exists:loan_types,id',
            'requested_amount'=> 'required|numeric|min:100',
            'installments'    => 'required|integer|min:1|max:12',
            'purpose'         => 'required|string|min:10',
            'notes'           => 'nullable|string',
        ]);

        $employee  = auth()->user()->employee;
        $loanType  = LoanType::findOrFail($request->loan_type_id);

        if ($loanType->max_amount > 0 && $request->requested_amount > $loanType->max_amount) {
            return response()->json(['message' => "Amount exceeds maximum allowed ({$loanType->max_amount} SAR) for this loan type."], 422);
        }
        if ($request->installments > $loanType->max_installments) {
            return response()->json(['message' => "Maximum installments for this loan type is {$loanType->max_installments}."], 422);
        }

        // Block if employee has an active loan of same type
        $active = Loan::where('employee_id', $employee->id)
            ->where('loan_type_id', $request->loan_type_id)
            ->whereIn('status',['pending_manager','pending_hr','pending_finance','approved','disbursed'])
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
            'reference'          => $this->service->generateReference(),
            'employee_id'        => $employee->id,
            'loan_type_id'       => $request->loan_type_id,
            'requested_amount'   => $request->requested_amount,
            'installments'       => $request->installments,
            'monthly_installment'=> $monthly,
            'purpose'            => $request->purpose,
            'notes'              => $request->notes,
            'status'             => 'pending_manager',
        ]);

        return response()->json(['message' => 'Loan request submitted.', 'loan' => $loan->load('loanType')], 201);
    }

    // ── Show single loan ──────────────────────────────────────────────────
    public function show($id)
    {
        $loan = Loan::with([
            'employee.department','loanType',
            'installments.processedBy',
            'managerApprover','hrApprover','financeApprover','rejectedBy',
        ])->findOrFail($id);
        return response()->json(['loan' => $loan]);
    }

    // ── Approve ───────────────────────────────────────────────────────────
    public function approve(Request $request, $id)
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
                    'approved_amount'         => 'nullable|numeric|min:1',
                    'disbursed_date'          => 'nullable|date',
                    'first_installment_date'  => 'nullable|date',
                ]);
                $approvedAmt = $request->approved_amount ?? $loan->requested_amount;
                $monthly     = $this->service->calculateMonthlyInstallment(
                    $approvedAmt, $loan->installments, $loan->loanType->interest_rate ?? 0
                );
                $loan->update([
                    'status'              => 'approved',
                    'finance_approved_by' => $user->id,
                    'finance_approved_at' => now(),
                    'approved_amount'     => $approvedAmt,
                    'monthly_installment' => $monthly,
                    'balance_remaining'   => $approvedAmt,
                    'disbursed_date'      => $request->disbursed_date ?? now()->toDateString(),
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
    public function reject(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string|min:5']);
        $loan = Loan::findOrFail($id);

        $stage = match($loan->status) {
            'pending_manager' => 'manager',
            'pending_hr'      => 'hr',
            'pending_finance' => 'finance',
            default           => null,
        };
        if (!$stage) return response()->json(['message' => 'Loan cannot be rejected at this stage.'], 422);

        $loan->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->reason,
            'rejected_by'      => auth()->id(),
            'rejected_at'      => now(),
            'rejected_stage'   => $stage,
        ]);
        return response()->json(['message' => 'Loan rejected.']);
    }

    // ── Cancel (by employee) ──────────────────────────────────────────────
    public function cancel($id)
    {
        $loan = Loan::findOrFail($id);
        if (!in_array($loan->status, ['pending_manager','pending_hr','pending_finance'])) {
            return response()->json(['message' => 'Loan cannot be cancelled at this stage.'], 422);
        }
        $loan->update(['status' => 'cancelled']);
        return response()->json(['message' => 'Loan request cancelled.']);
    }

    // ── Disburse ──────────────────────────────────────────────────────────
    public function disburse(Request $request, $id)
    {
        $loan = Loan::findOrFail($id);
        if ($loan->status !== 'approved') {
            return response()->json(['message' => 'Loan must be approved before disbursement.'], 422);
        }
        $loan->update(['status' => 'disbursed', 'disbursed_date' => $request->disbursed_date ?? now()->toDateString()]);
        return response()->json(['message' => 'Loan marked as disbursed.']);
    }

    // ── Installments: Pay ─────────────────────────────────────────────────
    public function payInstallment(Request $request, $loanId, $instId)
    {
        $inst = LoanInstallment::where('loan_id', $loanId)->findOrFail($instId);
        if (!in_array($inst->status, ['pending','overdue'])) {
            return response()->json(['message' => 'Installment is not payable.'], 422);
        }
        $this->service->payInstallment($inst, $request->paid_date, $request->notes);
        return response()->json(['message' => 'Installment marked as paid.']);
    }

    // ── Installments: Skip ────────────────────────────────────────────────
    public function skipInstallment(Request $request, $loanId, $instId)
    {
        $inst = LoanInstallment::where('loan_id', $loanId)->findOrFail($instId);
        if (!in_array($inst->status, ['pending','overdue'])) {
            return response()->json(['message' => 'Installment cannot be skipped.'], 422);
        }
        $this->service->skipInstallment($inst, $request->notes);
        return response()->json(['message' => 'Installment skipped — rescheduled to end of loan.']);
    }

    // ── Installments: Mark overdue ────────────────────────────────────────
    public function markOverdue()
    {
        $count = $this->service->markOverdue();
        return response()->json(['message' => "{$count} installments marked as overdue."]);
    }

    // ── My loans (current employee) ───────────────────────────────────────
    public function myLoans()
    {
        $employee = auth()->user()->employee;
        if (!$employee) return response()->json(['loans' => []]);

        $loans = Loan::with(['loanType'])
            ->where('employee_id', $employee->id)
            ->orderBy('created_at','desc')
            ->get()
            ->map(function ($loan) {
                $data = $loan->toArray();
                // getRawOriginal returns the integer column, not the relationship
                $data['total_installments'] = $loan->getRawOriginal('installments');
                return $data;
            });
        return response()->json(['loans' => $loans]);
    }
}
