<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Separation;
use App\Models\OffboardingTemplate;
use App\Models\OffboardingItem;
use App\Models\Employee;
use App\Services\SeparationService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SeparationController extends Controller
{
    public function __construct(protected SeparationService $service) {}

    // ── Stats ──────────────────────────────────────────────────────────────
    public function stats()
    {
        return response()->json($this->service->stats());
    }

    // ── List ───────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $query = Separation::with(['employee.department','employee.designation','initiatedBy'])
            ->when($request->status,   fn($q) => $q->where('status', $request->status))
            ->when($request->type,     fn($q) => $q->where('type',   $request->type))
            ->when($request->search,   fn($q) =>
                $q->whereHas('employee', fn($eq) =>
                    $eq->where('first_name','like',"%{$request->search}%")
                      ->orWhere('last_name','like',"%{$request->search}%")
                      ->orWhere('employee_code','like',"%{$request->search}%")
                )
            )
            ->orderBy('created_at','desc');

        return response()->json($query->paginate(15));
    }

    // ── Show ───────────────────────────────────────────────────────────────
    public function show($id)
    {
        $sep = Separation::with([
            'employee.department','employee.designation','employee.leaveAllocations.leaveType',
            'initiatedBy','managerApprover','hrApprover','rejectedBy',
            'checklistItems.completedBy',
        ])->findOrFail($id);
        return response()->json(['separation' => $sep]);
    }

    // ── Create ─────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'employee_id'     => 'required|exists:employees,id',
            'type'            => 'required|in:resignation,termination,end_of_contract,retirement,abandonment,mutual_agreement',
            'reason'          => 'required|string|min:10',
            'last_working_day'=> 'required|date|after:today',
            'reason_category' => 'nullable|in:personal,better_opportunity,relocation,health,misconduct,performance,restructuring,contract_end,other',
            'notice_waived'   => 'boolean',
        ]);

        $emp     = Employee::findOrFail($request->employee_id);
        $lwDay   = Carbon::parse($request->last_working_day);
        $today   = now()->startOfDay();
        $noticeDays = (int)$lwDay->diffInDays($today);

        $sep = Separation::create([
            'reference'          => $this->service->generateReference(),
            'employee_id'        => $request->employee_id,
            'type'               => $request->type,
            'status'             => in_array($request->type, ['termination','abandonment']) ? 'pending_hr' : 'pending_manager',
            'request_date'       => now()->toDateString(),
            'last_working_day'   => $request->last_working_day,
            'notice_period_start'=> now()->toDateString(),
            'notice_period_days' => $noticeDays,
            'notice_waived'      => $request->notice_waived ?? false,
            'notice_waived_reason'=> $request->notice_waived_reason,
            'reason'             => $request->reason,
            'reason_category'    => $request->reason_category,
            'hr_notes'           => $request->hr_notes,
            'initiated_by'       => auth()->id(),
            'exit_interview_required' => !in_array($request->type, ['abandonment']),
        ]);

        return response()->json(['message' => 'Separation request created.', 'separation' => $sep->load('employee')], 201);
    }

    // ── Update (HR can edit before approval) ──────────────────────────────
    public function update(Request $request, $id)
    {
        $sep = Separation::findOrFail($id);
        if (!in_array($sep->status, ['draft','pending_manager','pending_hr'])) {
            return response()->json(['message' => 'Cannot edit at this stage.'], 422);
        }
        $sep->update($request->only([
            'last_working_day','reason','reason_category','hr_notes',
            'notice_waived','notice_waived_reason','exit_interview_required',
        ]));
        return response()->json(['separation' => $sep->fresh()]);
    }

    // ── Approve ────────────────────────────────────────────────────────────
    public function approve(Request $request, $id)
    {
        $sep  = Separation::with('employee')->findOrFail($id);
        $user = auth()->user();

        switch ($sep->status) {
            case 'pending_manager':
                $sep->update([
                    'status'              => 'pending_hr',
                    'manager_approved_by' => $user->id,
                    'manager_approved_at' => now(),
                ]);
                break;

            case 'pending_hr':
                // Calculate settlement
                $gratuity  = $this->service->calculateGratuity($sep->employee, $sep->type, $sep->last_working_day);
                $encash    = $this->service->calculateLeaveEncashment($sep->employee);
                $additions = (float)($request->other_additions ?? 0);
                $deductions= (float)($request->other_deductions ?? 0);
                $settlement= max(0, $gratuity + $encash + $additions - $deductions);

                $sep->update([
                    'status'              => 'approved',
                    'hr_approved_by'      => $user->id,
                    'hr_approved_at'      => now(),
                    'gratuity_amount'     => $gratuity,
                    'leave_encashment'    => $encash,
                    'other_additions'     => $additions,
                    'other_deductions'    => $deductions,
                    'final_settlement_amount' => $settlement,
                    'hr_notes'            => $request->hr_notes ?? $sep->hr_notes,
                ]);
                break;

            case 'approved':
                // Move to offboarding — generate checklist
                $sep->update(['status' => 'offboarding']);
                $this->service->generateChecklist($sep);
                // Mark employee status
                $sep->employee->update(['status' => 'inactive']);
                break;

            default:
                return response()->json(['message' => 'Cannot approve at this stage.'], 422);
        }

        return response()->json(['message' => 'Approved.', 'separation' => $sep->fresh('employee')]);
    }

    // ── Reject ─────────────────────────────────────────────────────────────
    public function reject(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string|min:5']);
        $sep = Separation::findOrFail($id);

        if (!in_array($sep->status, ['pending_manager','pending_hr'])) {
            return response()->json(['message' => 'Cannot reject at this stage.'], 422);
        }
        $sep->update([
            'status'           => 'rejected',
            'rejected_by'      => auth()->id(),
            'rejected_at'      => now(),
            'rejection_reason' => $request->reason,
        ]);
        return response()->json(['message' => 'Separation request rejected.']);
    }

    // ── Cancel ─────────────────────────────────────────────────────────────
    public function cancel($id)
    {
        $sep = Separation::findOrFail($id);
        if (!in_array($sep->status, ['draft','pending_manager','pending_hr'])) {
            return response()->json(['message' => 'Cannot cancel at this stage.'], 422);
        }
        $sep->update(['status' => 'cancelled']);
        return response()->json(['message' => 'Separation cancelled.']);
    }

    // ── Complete (after offboarding done) ──────────────────────────────────
    public function complete(Request $request, $id)
    {
        $sep = Separation::with('employee')->findOrFail($id);
        if ($sep->status !== 'offboarding') {
            return response()->json(['message' => 'Separation is not in offboarding stage.'], 422);
        }
        $sep->update([
            'status'                 => 'completed',
            'settlement_paid'        => $request->settlement_paid ?? false,
            'settlement_paid_date'   => $request->settlement_paid ? now()->toDateString() : null,
            'settlement_notes'       => $request->settlement_notes,
        ]);
        // Fully terminate the employee
        $sep->employee->update([
            'status'           => 'terminated',
            'termination_date' => $sep->last_working_day,
        ]);
        return response()->json(['message' => 'Separation completed. Employee marked as terminated.']);
    }

    // ── Update settlement ──────────────────────────────────────────────────
    public function updateSettlement(Request $request, $id)
    {
        $sep = Separation::findOrFail($id);
        $sep->update($request->only([
            'gratuity_amount','leave_encashment','other_additions','other_deductions',
            'final_settlement_amount','settlement_paid','settlement_paid_date','settlement_notes',
        ]));
        return response()->json(['separation' => $sep->fresh()]);
    }

    // ── Exit interview ─────────────────────────────────────────────────────
    public function updateExitInterview(Request $request, $id)
    {
        $sep = Separation::findOrFail($id);
        $sep->update([
            'exit_interview_done'  => true,
            'exit_interview_date'  => $request->date ?? now()->toDateString(),
            'exit_interview_notes' => $request->notes,
        ]);
        return response()->json(['message' => 'Exit interview recorded.']);
    }

    // ── Checklist item update ─────────────────────────────────────────────
    public function updateChecklistItem(Request $request, $sepId, $itemId)
    {
        $item = OffboardingItem::where('separation_id', $sepId)->findOrFail($itemId);
        $item->update([
            'status'       => $request->status,
            'notes'        => $request->notes,
            'completed_by' => in_array($request->status, ['completed']) ? auth()->id() : null,
            'completed_at' => in_array($request->status, ['completed']) ? now() : null,
        ]);
        return response()->json(['item' => $item->fresh()]);
    }

    // ── Offboarding Templates CRUD ─────────────────────────────────────────
    public function templates()
    {
        return response()->json(['templates' => OffboardingTemplate::orderBy('category')->orderBy('sort_order')->get()]);
    }

    public function storeTemplate(Request $request)
    {
        $request->validate(['title' => 'required|string|max:150']);
        return response()->json(['template' => OffboardingTemplate::create($request->all())], 201);
    }

    public function updateTemplate(Request $request, $id)
    {
        $t = OffboardingTemplate::findOrFail($id);
        $t->update($request->all());
        return response()->json(['template' => $t]);
    }

    public function deleteTemplate($id)
    {
        OffboardingTemplate::findOrFail($id)->delete();
        return response()->json(['message' => 'Template deleted.']);
    }

    // ── Calculate settlement preview ───────────────────────────────────────
    public function settlementPreview(Request $request)
    {
        $emp      = Employee::findOrFail($request->employee_id);
        $gratuity = $this->service->calculateGratuity($emp, $request->type, $request->last_working_day);
        $encash   = $this->service->calculateLeaveEncashment($emp);
        return response()->json([
            'gratuity_amount'  => $gratuity,
            'leave_encashment' => $encash,
            'total'            => $gratuity + $encash,
            'years_of_service' => round($emp->hire_date ? now()->floatDiffInYears($emp->hire_date) : 0, 1),
        ]);
    }
}
