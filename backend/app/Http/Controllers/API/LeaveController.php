<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use App\Models\LeaveRequest;
use App\Models\LeaveAllocation;
use App\Services\LeaveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LeaveController extends Controller {

    protected $service;

    public function __construct(LeaveService $service) {
        $this->service = $service;
    }

    /**
     * Get the authenticated user's role names directly from the DB.
     * Bypasses Spatie's guard resolution which fails with Sanctum.
     */
    private function userRoles(): array
    {
        $user = auth()->user();
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.model_type', get_class($user))
            ->pluck('roles.name')
            ->toArray();
    }

    private function hasAnyRoleDB(array $roles): bool
    {
        return count(array_intersect($this->userRoles(), $roles)) > 0;
    }

    public function types() {
        return response()->json(['types' => LeaveType::where('is_active', true)->get()]);
    }

    public function storeType(Request $request) {
        $request->validate([
            'name'                  => 'required|string|max:100',
            'code'                  => 'required|string|max:20|unique:leave_types',
            'days_allowed'          => 'required|integer|min:0',
            'is_paid'               => 'boolean',
            'carry_forward'         => 'boolean',
            'requires_document'     => 'boolean',
            'is_active'             => 'boolean',
            'skip_manager_approval' => 'boolean',
        ]);
        return response()->json(['type' => LeaveType::create($request->all())], 201);
    }

    public function updateType(Request $request, $id) {
        $type = LeaveType::findOrFail($id);
        $request->validate([
            'name'                  => 'sometimes|string|max:100',
            'days_allowed'          => 'sometimes|integer|min:0',
            'is_paid'               => 'boolean',
            'carry_forward'         => 'boolean',
            'requires_document'     => 'boolean',
            'is_active'             => 'boolean',
            'skip_manager_approval' => 'boolean',  // sick leave policy
        ]);
        $type->update($request->all());
        return response()->json(['type' => $type->fresh()]);
    }

    public function index(Request $request) {
        $user = auth()->user();

        // ── Role check via raw DB (no Spatie, no guard issues) ────────────────
        $userRoles  = rescue(fn () => DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->id)
            ->pluck('roles.name')->toArray(), [], false);

        $isHRAdmin  = (bool) array_intersect($userRoles, ['super_admin','hr_manager','hr_staff']);
        $isMgr      = in_array('department_manager', $userRoles);

        $query = LeaveRequest::with(['employee.department', 'leaveType'])
            ->when(!$isHRAdmin, function($q) use ($user, $isMgr) {
                if ($isMgr && $user->employee) {
                    $teamIds = $user->employee->subordinates()->pluck('id');
                    $q->whereIn('employee_id', $teamIds->push($user->employee->id));
                } elseif ($user->employee) {
                    $q->where('employee_id', $user->employee->id);
                }
            })
            ->when($request->needs_action, fn($q) => $q->whereIn('status',
                $isHRAdmin ? ['pending', 'manager_approved'] : ['pending']
            ))
            ->when(!$request->needs_action && $request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->employee_id, fn($q) => $q->where('employee_id', $request->employee_id))
            ->orderBy('created_at', 'desc');

        return response()->json($query->paginate((int)($request->per_page ?? 15)));
    }

    public function store(Request $request) {
        $leaveType = LeaveType::findOrFail($request->leave_type_id);

        // ── Business Excuse (hourly) ──────────────────────────────────────
        if ($leaveType->is_hourly) {
            $request->validate([
                'leave_type_id' => 'required|exists:leave_types,id',
                'start_date'    => 'required|date|after_or_equal:today',
                'start_time'    => 'required|date_format:H:i',
                'end_time'      => 'required|date_format:H:i|after:start_time',
                'reason'        => 'required|string|min:5',
            ]);

            $employee = auth()->user()->employee->load('department');
            $hours    = $this->service->calculateExcuseHours(
                $request->start_date,
                $request->start_time,
                $request->end_time
            );

            $error = $this->service->validateBusinessExcuse(
                $employee, $request->start_date,
                $request->start_time, $request->end_time, $hours
            );

            if ($error) return response()->json(['message' => $error], 422);

            // Document upload for hourly types
            $documentPath = null;
            if ($request->hasFile('document')) {
                $request->validate(['document' => 'file|mimes:pdf,jpg,jpeg,png|max:5120']);
                $documentPath = $request->file('document')->store(
                    "leave-documents/{$employee->id}", 'public'
                );
            }

            $leaveRequest = LeaveRequest::create([
                'employee_id'   => $employee->id,
                'leave_type_id' => $request->leave_type_id,
                'start_date'    => $request->start_date,
                'end_date'      => $request->start_date,
                'start_time'    => $request->start_time,
                'end_time'      => $request->end_time,
                'total_days'    => 0,
                'total_hours'   => $hours,
                'document_path' => $documentPath,
                'status'        => $leaveType->skip_manager_approval ? 'manager_approved' : 'pending',
                'reason'        => $request->reason,
            ]);

            $this->service->notifyManager($leaveRequest);
            return response()->json(['message' => "Business excuse of {$hours}h submitted", 'request' => $leaveRequest->load('leaveType')], 201);
        }

        // ── Standard (daily) leave ────────────────────────────────────────
        $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date'    => 'required|date|after_or_equal:today',
            'end_date'      => 'required|date|after_or_equal:start_date',
            'reason'        => 'required|string|min:10',
        ]);

        $employee   = auth()->user()->employee;
        $totalDays  = $this->service->calculateWorkingDays($request->start_date, $request->end_date);
        $allocation = LeaveAllocation::where([
            'employee_id'   => $employee->id,
            'leave_type_id' => $request->leave_type_id,
            'year'          => now()->year,
        ])->first();

        if ($allocation && $allocation->remaining_days < $totalDays) {
            return response()->json(['message' => "Insufficient leave balance. Available: {$allocation->remaining_days} days"], 422);
        }

        // ── Document upload (required if leave type has requires_document=true) ──
        if ($leaveType->requires_document && !$request->hasFile('document')) {
            return response()->json(['message' => "A supporting document is required for '{$leaveType->name}' leave."], 422);
        }

        $documentPath = null;
        if ($request->hasFile('document')) {
            $request->validate(['document' => 'file|mimes:pdf,jpg,jpeg,png|max:5120']);
            $documentPath = $request->file('document')->store(
                "leave-documents/{$employee->id}", 'public'
            );
        }

        $leaveRequest = LeaveRequest::create(array_merge($request->only(['leave_type_id','start_date','end_date','reason']), [
            'employee_id'   => $employee->id,
            'total_days'    => $totalDays,
            'document_path' => $documentPath,
            'status'        => $leaveType->skip_manager_approval ? 'manager_approved' : 'pending',
        ]));

        $this->service->notifyManager($leaveRequest);
        return response()->json(['message' => 'Leave request submitted', 'request' => $leaveRequest->load('leaveType')], 201);
    }

    public function show($id) {
        $request = LeaveRequest::with(['employee', 'leaveType', 'approver', 'managerApprover'])->findOrFail($id);
        return response()->json(['request' => $request]);
    }

    public function approve(Request $request, $id) {
        $leave = LeaveRequest::with('leaveType')->findOrFail($id);
        $user  = auth()->user();

        // ── Stage 1: Manager approval ──────────────────────────────────
        if ($leave->status === 'pending') {
            // Only managers / HR / super_admin can approve at this stage
            if (!$this->hasAnyRoleDB(['department_manager','hr_manager','hr_staff','super_admin'])) {
                return response()->json(['message' => 'Only a manager can approve at this stage.'], 403);
            }

            $leave->update([
                'status'               => 'manager_approved',
                'manager_approved_by'  => $user->id,
                'manager_approved_at'  => now(),
                'manager_notes'        => $request->input('notes'),
            ]);

            return response()->json([
                'message' => 'Approved at manager level. Awaiting HR approval.',
                'leave'   => $leave->fresh(['leaveType', 'employee', 'managerApprover']),
            ]);
        }

        // ── Stage 2: HR final approval ─────────────────────────────────
        if ($leave->status === 'manager_approved') {
            if (!$this->hasAnyRoleDB(['hr_manager','hr_staff','super_admin'])) {
                return response()->json(['message' => 'Only HR can give final approval.'], 403);
            }

            $leave->update([
                'status'      => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            $this->service->updateLeaveBalance($leave, 'approve');
            $this->service->notifyEmployee($leave, 'approved');

            return response()->json([
                'message' => 'Leave fully approved by HR.',
                'leave'   => $leave->fresh(['leaveType', 'employee', 'approver', 'managerApprover']),
            ]);
        }

        return response()->json(['message' => "Cannot approve a leave with status '{$leave->status}'."], 422);
    }

    public function reject(Request $request, $id) {
        $request->validate(['reason' => 'required|string']);
        $leave = LeaveRequest::with('leaveType')->findOrFail($id);
        $user  = auth()->user();

        // Track which stage the rejection occurred at
        $stage = match ($leave->status) {
            'pending'          => 'manager',
            'manager_approved' => 'hr',
            default            => 'unknown',
        };

        $leave->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->reason,
            'rejected_stage'   => $stage,
            'approved_by'      => $user->id,
        ]);
        $this->service->notifyEmployee($leave, 'rejected');
        return response()->json(['message' => "Leave rejected at {$stage} stage."]);
    }

    public function cancel($id) {
        $leave = LeaveRequest::findOrFail($id);
        if (!in_array($leave->status, ['pending', 'approved'])) {
            return response()->json(['message' => 'Cannot cancel this leave'], 422);
        }
        if ($leave->status === 'approved') {
            $this->service->updateLeaveBalance($leave, 'cancel');
        }
        $leave->update(['status' => 'cancelled']);
        return response()->json(['message' => 'Leave cancelled']);
    }

    public function balance($empId) {
        $allocations = LeaveAllocation::with('leaveType')
            ->where('employee_id', $empId)
            ->where('year', now()->year)
            ->get();
        return response()->json(['balances' => $allocations]);
    }

    public function calendar(Request $request) {
        $approved = LeaveRequest::with(['employee', 'leaveType'])
            ->where('status', 'approved')
            ->when($request->month, fn($q) => $q->whereMonth('start_date', $request->month))
            ->when($request->year,  fn($q) => $q->whereYear('start_date', $request->year))
            ->get();
        return response()->json(['leaves' => $approved]);
    }

    public function update(Request $request, $id) {
        $leave = LeaveRequest::findOrFail($id);
        if ($leave->status !== 'pending') return response()->json(['message' => 'Cannot edit non-pending leave'], 422);
        $leave->update($request->only(['start_date','end_date','reason']));
        return response()->json(['message' => 'Leave updated', 'request' => $leave]);
    }

    public function runAccrual()
    {
        try {
            Artisan::call('leave:accrue');
            $output = Artisan::output();
            return response()->json([
                'message' => 'Leave accrual completed successfully.',
                'output'  => $output,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Accrual failed: ' . $e->getMessage()], 500);
        }
    }

    public function stats() {
        $user    = auth()->user();
        $isAdmin = rescue(fn() => $this->hasAnyRoleDB(['super_admin','hr_manager','hr_staff']), true, false);
        $today   = now()->toDateString();

        $baseQ = LeaveRequest::query();
        if (!$isAdmin && $user->employee) {
            $baseQ->where('employee_id', $user->employee->id);
        }

        $pendingCount   = (clone $baseQ)->where('status','pending')->count();
        $approvedMonth  = (clone $baseQ)->where('status','approved')
            ->whereMonth('start_date', now()->month)->whereYear('start_date', now()->year)->count();
        $onLeaveToday   = LeaveRequest::where('status','approved')
            ->where('start_date','<=',$today)->where('end_date','>=',$today)->count();
        $cancelledCount = (clone $baseQ)->where('status','cancelled')->count();

        return response()->json([
            'pending_count'   => $pendingCount,
            'approved_month'  => $approvedMonth,
            'on_leave_today'  => $onLeaveToday,
            'cancelled_count' => $cancelledCount,
        ]);
    }

    public function allBalances(Request $request) {
        $year = $request->year ?? now()->year;
        $allocations = LeaveAllocation::with(['employee.department','leaveType'])
            ->where('year', $year)
            ->when($request->department_id, fn($q) =>
                $q->whereHas('employee', fn($eq) => $eq->where('department_id', $request->department_id))
            )
            ->when($request->search, fn($q) =>
                $q->whereHas('employee', fn($eq) =>
                    $eq->where('first_name','like',"%{$request->search}%")
                      ->orWhere('last_name','like',"%{$request->search}%")
                )
            )
            ->orderBy('employee_id')
            ->paginate(25);
        return response()->json($allocations);
    }

    public function holidays(Request $request) {
        $year = $request->year ?? now()->year;
        $holidays = \App\Models\Holiday::whereYear('date', $year)
            ->orderBy('date')->get();
        return response()->json(['holidays' => $holidays]);
    }

    public function storeHoliday(Request $request) {
        $request->validate([
            'name' => 'required|string|max:100',
            'date' => 'required|date',
        ]);
        $holiday = \App\Models\Holiday::create($request->only(['name','date','is_recurring']));
        return response()->json(['holiday' => $holiday], 201);
    }

    public function deleteHoliday($id) {
        \App\Models\Holiday::findOrFail($id)->delete();
        return response()->json(['message' => 'Holiday deleted']);
    }

    public function excuseUsage(Request $request) {
        $user   = auth()->user();
        $empId  = $request->employee_id ?? $user->employee?->id;
        $year   = $request->year  ?? now()->year;
        $month  = $request->month ?? now()->month;

        if (!$empId) return response()->json(['message' => 'Employee not found'], 404);

        return response()->json($this->service->monthlyExcuseUsage($empId, $year, $month));
    }
}
