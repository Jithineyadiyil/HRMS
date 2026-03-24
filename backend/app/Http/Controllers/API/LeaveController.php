<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use App\Models\LeaveRequest;
use App\Models\LeaveAllocation;
use App\Services\LeaveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class LeaveController extends Controller {

    protected $service;

    public function __construct(LeaveService $service) {
        $this->service = $service;
    }

    public function types() {
        return response()->json(['types' => LeaveType::where('is_active', true)->get()]);
    }

    public function storeType(Request $request) {
        $request->validate([
            'name'         => 'required|string|max:100',
            'code'         => 'required|string|max:20|unique:leave_types',
            'days_allowed' => 'required|integer|min:0',
        ]);
        return response()->json(['type' => LeaveType::create($request->all())], 201);
    }

    public function updateType(Request $request, $id) {
        $type = LeaveType::findOrFail($id);
        $type->update($request->all());
        return response()->json(['type' => $type]);
    }

    public function index(Request $request) {
        $user = auth()->user();
        $query = LeaveRequest::with(['employee.department', 'leaveType', 'approver'])
            ->when(!$user->hasRole(['super_admin','hr_manager']), function($q) use ($user) {
                // Managers see their team; employees see only own
                if ($user->hasRole('manager') && $user->employee) {
                    $teamIds = $user->employee->subordinates()->pluck('id');
                    $q->whereIn('employee_id', $teamIds->push($user->employee->id));
                } else if ($user->employee) {
                    $q->where('employee_id', $user->employee->id);
                }
            })
            ->when($request->status,      fn($q) => $q->where('status', $request->status))
            ->when($request->employee_id, fn($q) => $q->where('employee_id', $request->employee_id))
            ->orderBy('created_at', 'desc');

        return response()->json($query->paginate(15));
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

            $leaveRequest = LeaveRequest::create([
                'employee_id'   => $employee->id,
                'leave_type_id' => $request->leave_type_id,
                'start_date'    => $request->start_date,
                'end_date'      => $request->start_date,
                'start_time'    => $request->start_time,
                'end_time'      => $request->end_time,
                'total_days'    => 0,
                'total_hours'   => $hours,
                'status'        => 'pending',
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

        $leaveRequest = LeaveRequest::create(array_merge($request->only(['leave_type_id','start_date','end_date','reason']), [
            'employee_id' => $employee->id,
            'total_days'  => $totalDays,
            'status'      => 'pending',
        ]));

        $this->service->notifyManager($leaveRequest);
        return response()->json(['message' => 'Leave request submitted', 'request' => $leaveRequest->load('leaveType')], 201);
    }

    public function show($id) {
        $request = LeaveRequest::with(['employee', 'leaveType', 'approver'])->findOrFail($id);
        return response()->json(['request' => $request]);
    }

    public function approve($id) {
        $leave = LeaveRequest::findOrFail($id);
        if ($leave->status !== 'pending') return response()->json(['message' => 'Leave is not pending'], 422);

        $leave->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
        $this->service->updateLeaveBalance($leave, 'approve');
        $this->service->notifyEmployee($leave, 'approved');

        return response()->json(['message' => 'Leave approved successfully']);
    }

    public function reject(Request $request, $id) {
        $request->validate(['reason' => 'required|string']);
        $leave = LeaveRequest::findOrFail($id);
        $leave->update(['status' => 'rejected', 'rejection_reason' => $request->reason, 'approved_by' => auth()->id()]);
        $this->service->notifyEmployee($leave, 'rejected');
        return response()->json(['message' => 'Leave rejected']);
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
        // Current year — all leave types (used for balance sidebar cards)
        $allocations = LeaveAllocation::with('leaveType')
            ->where('employee_id', $empId)
            ->where('year', now()->year)
            ->get();

        // For Annual Leave, compute carry-forward from previous year so the
        // balance card matches the Annual Balance tab
        $annualType = \App\Models\LeaveType::where('code','AL')
            ->orWhere('name','like','%Annual%')
            ->orderBy('id')->first();

        if ($annualType) {
            $maxCF = (float) ($annualType->max_carry_forward ?? 0);
            $prevYear = LeaveAllocation::where('employee_id', $empId)
                ->where('leave_type_id', $annualType->id)
                ->where('year', now()->year - 1)
                ->first();

            if ($prevYear) {
                $prevRemaining = max(0, (float) $prevYear->remaining_days);
                $carriedIn     = $maxCF > 0 ? min($prevRemaining, $maxCF) : $prevRemaining;

                // Attach carried_in to the current annual allocation so frontend can display it
                $allocations->each(function ($alloc) use ($annualType, $carriedIn) {
                    if ($alloc->leave_type_id === $annualType->id) {
                        $alloc->carried_in_days     = round($carriedIn, 1);
                        $alloc->total_available_days = round($alloc->allocated_days + $carriedIn, 1);
                        // Adjust remaining to include carry-forward
                        $alloc->display_remaining    = round(max(0, $alloc->total_available_days - $alloc->used_days - $alloc->pending_days), 1);
                    }
                });
            }
        }

        return response()->json(['balances' => $allocations]);
    }

    /**
     * Annual Leave full history for an employee — all years, with carry-forward chain.
     * Returns one row per year showing: entitlement, carried_in, total_allocated,
     * used, remaining, carried_out (to next year).
     */
    public function annualLeaveHistory($empId) {
        $annualType = \App\Models\LeaveType::where('code', 'AL')
            ->orWhere('name', 'like', '%Annual%')
            ->orderBy('id')->first();

        if (!$annualType) {
            return response()->json(['error' => 'Annual Leave type not configured.'], 404);
        }

        // All allocations for this employee for Annual Leave, ordered by year ascending
        $allocations = LeaveAllocation::where('employee_id', $empId)
            ->where('leave_type_id', $annualType->id)
            ->orderBy('year', 'asc')
            ->get();

        $maxCarryForward = (float) ($annualType->max_carry_forward ?? 0);
        $history         = [];
        $carriedIn       = 0.0; // balance carried from prior year

        $currentYear = (int) now()->year;

        foreach ($allocations as $alloc) {
            $isCurrentYear = (int) $alloc->year === $currentYear;

            // Actual leave taken (approved requests)
            $used = (float) $alloc->used_days;

            // Entitlement:
            // - Current year: use allocated_days (accrued so far by the daily command)
            //   so this view matches the Balance cards which also read from allocated_days
            // - Past years: use annual_entitlement (full year entitlement — 22 or 30 days)
            if ($isCurrentYear) {
                $entitlement = (float) $alloc->allocated_days;
            } else {
                $entitlement = (float) ($alloc->annual_entitlement ?? $alloc->allocated_days ?? $annualType->days_allowed);
            }

            // Total available = entitlement + whatever was carried in
            $totalAvailable = round($entitlement + $carriedIn, 1);

            // Remaining:
            // - Current year: read directly from DB remaining_days (matches balance card exactly)
            // - Past years: compute from total_available - used - pending
            if ($isCurrentYear) {
                $remaining = max(0, (float) $alloc->remaining_days);
            } else {
                $remaining = max(0, round($totalAvailable - $used - (float)$alloc->pending_days, 1));
            }

            // How much carries to next year (capped by max_carry_forward)
            $carriedOut = $maxCarryForward > 0 ? min($remaining, $maxCarryForward) : $remaining;

            $history[] = [
                'year'            => $alloc->year,
                'entitlement'     => $entitlement,
                'carried_in'      => round($carriedIn, 1),
                'total_available' => $totalAvailable,
                'used'            => $used,
                'pending'         => (float) $alloc->pending_days,
                'remaining'       => $remaining,
                'carried_out'     => round($carriedOut, 1),
                'last_accrual'    => $alloc->last_accrual_date?->toDateString(),
                'is_current_year' => $alloc->year === (int) now()->year,
            ];

            // Update the carried_in for the next iteration
            $carriedIn = $carriedOut;

            // Persist carry-forward to DB if column exists
            if ($alloc->year < now()->year) {
                $alloc->update(['carried_forward_days' => $carriedOut]);
            }
        }

        // If no history at all, return empty with metadata
        $employee = \App\Models\Employee::select('id','first_name','last_name','hire_date','employee_code')
            ->find($empId);

        return response()->json([
            'employee'         => $employee,
            'annual_type'      => $annualType,
            'max_carry_forward'=> $maxCarryForward,
            'history'          => array_reverse($history), // newest first for display
            'total_years'      => count($history),
        ]);
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
        $isAdmin = $user->hasRole(['super_admin','hr_manager']);
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

        $query = LeaveAllocation::with(['employee.department','leaveType'])
            ->where('year', $year);

        // Filter annual leave only when requested (default for balance tab)
        if ($request->boolean('annual_only')) {
            $annualType = \App\Models\LeaveType::where('code', 'AL')
                ->orWhere('name', 'like', '%Annual%')
                ->orderBy('id')->first();
            if ($annualType) {
                $query->where('leave_type_id', $annualType->id);
            }
        }

        $query->when($request->department_id, fn($q) =>
                $q->whereHas('employee', fn($eq) => $eq->where('department_id', $request->department_id))
            )
            ->when($request->search, fn($q) =>
                $q->whereHas('employee', fn($eq) =>
                    $eq->where('first_name','like',"%{$request->search}%")
                      ->orWhere('last_name','like',"%{$request->search}%")
                )
            )
            ->orderBy('employee_id');

        $allocations = $query->paginate(25);
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
