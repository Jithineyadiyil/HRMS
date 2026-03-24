<?php
namespace App\Services;

use App\Mail\LeaveStatusMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

use App\Models\LeaveRequest;
use App\Models\LeaveAllocation;
use App\Models\LeaveType;
use App\Models\Employee;
use App\Models\DepartmentExcuseLimit;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class LeaveService
{
    private const WORKING_DAYS       = [0, 1, 2, 3, 4]; // Sun–Thu
    const BUSINESS_EXCUSE_CODE       = 'BE';
    const WORK_START                 = '08:00';
    const WORK_END                   = '16:00';
    const DEFAULT_MONTHLY_CAP_HOURS  = 12.0;

    // ── Working days count ────────────────────────────────────────────────
    public function calculateWorkingDays(string $start, string $end): float
    {
        $days   = 0;
        $period = CarbonPeriod::create($start, $end);
        foreach ($period as $date) {
            if (in_array($date->dayOfWeek, self::WORKING_DAYS)) $days++;
        }
        return $days;
    }

    // ── Calculate hours for a Business Excuse ────────────────────────────
    public function calculateExcuseHours(string $date, string $startTime, string $endTime): float
    {
        $start     = Carbon::parse("$date $startTime");
        $end       = Carbon::parse("$date $endTime");
        $workStart = Carbon::parse("$date " . self::WORK_START);
        $workEnd   = Carbon::parse("$date " . self::WORK_END);

        $start = $start->max($workStart);
        $end   = $end->min($workEnd);

        if ($end <= $start) return 0;
        return round($end->diffInMinutes($start) / 60, 2);
    }

    // ── Resolve the limit for a department + leave type from DB ──────────
    /**
     * Returns: ['is_limited' => bool, 'limit_hours' => float|null]
     * is_limited  = false  → unlimited
     * limit_hours = null   → unlimited (when is_limited=false)
     * limit_hours = X      → cap at X hours/month
     */
    public function getDepartmentLimit(int $departmentId, int $leaveTypeId): array
    {
        $row = DepartmentExcuseLimit::where('department_id', $departmentId)
            ->where('leave_type_id', $leaveTypeId)
            ->first();

        if (!$row) {
            // No config saved yet → apply default 12h cap
            return ['is_limited' => true, 'limit_hours' => self::DEFAULT_MONTHLY_CAP_HOURS];
        }

        if (!$row->is_limited) {
            return ['is_limited' => false, 'limit_hours' => null];
        }

        return ['is_limited' => true, 'limit_hours' => $row->monthly_hours_limit ?? self::DEFAULT_MONTHLY_CAP_HOURS];
    }

    // ── Validate Business Excuse ──────────────────────────────────────────
    public function validateBusinessExcuse(
        Employee $employee,
        string $date,
        string $startTime,
        string $endTime,
        float $hours,
        ?int $excludeRequestId = null
    ): ?string {
        $dow = Carbon::parse($date)->dayOfWeek;
        if (!in_array($dow, self::WORKING_DAYS)) {
            return 'Business excuses can only be submitted for working days (Sun–Thu).';
        }
        if ($startTime < self::WORK_START || $endTime > self::WORK_END) {
            return 'Times must be within working hours (08:00 – 16:00).';
        }
        if ($startTime >= $endTime) {
            return 'End time must be after start time.';
        }
        if ($hours <= 0) {
            return 'Calculated hours must be greater than zero.';
        }

        // Overlap check
        $leaveType = LeaveType::where('code', self::BUSINESS_EXCUSE_CODE)->first();
        $overlap   = LeaveRequest::where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType?->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where('start_date', $date)
            ->when($excludeRequestId, fn($q) => $q->where('id', '!=', $excludeRequestId))
            ->exists();

        if ($overlap) {
            return 'You already have a business excuse on this date.';
        }

        // Resolve department limit from DB
        $deptId    = $employee->department_id;
        $limitConf = $this->getDepartmentLimit($deptId, $leaveType?->id ?? 0);

        if (!$limitConf['is_limited']) {
            return null; // unlimited
        }

        $capHours = $limitConf['limit_hours'];

        // Monthly usage check
        $monthStart = Carbon::parse($date)->startOfMonth()->toDateString();
        $monthEnd   = Carbon::parse($date)->endOfMonth()->toDateString();

        $usedThisMonth = LeaveRequest::where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType?->id)
            ->whereIn('status', ['pending', 'approved'])
            ->whereBetween('start_date', [$monthStart, $monthEnd])
            ->when($excludeRequestId, fn($q) => $q->where('id', '!=', $excludeRequestId))
            ->sum('total_hours');

        $remaining = $capHours - $usedThisMonth;

        if ($hours > $remaining) {
            $usedFmt      = number_format($usedThisMonth, 1);
            $capFmt       = number_format($capHours, 1);
            $remainingFmt = number_format(max(0, $remaining), 1);
            return "Monthly limit exceeded. Used: {$usedFmt}h / {$capFmt}h. Remaining: {$remainingFmt}h.";
        }

        return null;
    }

    // ── Monthly usage summary ─────────────────────────────────────────────
    public function monthlyExcuseUsage(int $empId, int $year, int $month): array
    {
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth()->toDateString();
        $monthEnd   = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        $leaveType = LeaveType::where('code', self::BUSINESS_EXCUSE_CODE)->first();

        $used = LeaveRequest::where('employee_id', $empId)
            ->where('leave_type_id', $leaveType?->id)
            ->whereIn('status', ['pending', 'approved'])
            ->whereBetween('start_date', [$monthStart, $monthEnd])
            ->sum('total_hours');

        $employee  = Employee::with('department')->find($empId);
        $deptId    = $employee?->department_id;
        $limitConf = $this->getDepartmentLimit($deptId ?? 0, $leaveType?->id ?? 0);

        $isUnlimited = !$limitConf['is_limited'];
        $capHours    = $limitConf['limit_hours'];

        return [
            'used_hours'      => round($used, 2),
            'limit_hours'     => $isUnlimited ? null : $capHours,
            'remaining_hours' => $isUnlimited ? null : max(0, $capHours - $used),
            'is_unlimited'    => $isUnlimited,
            'month'           => Carbon::create($year, $month)->format('F Y'),
            'department'      => $employee?->department?->name,
        ];
    }

    // ── Leave balance update ──────────────────────────────────────────────
    public function updateLeaveBalance(LeaveRequest $leave, string $action): void
    {
        $allocation = LeaveAllocation::where([
            'employee_id'   => $leave->employee_id,
            'leave_type_id' => $leave->leave_type_id,
            'year'          => Carbon::parse($leave->start_date)->year,
        ])->first();

        if (!$allocation) return;

        if ($action === 'approve') {
            $allocation->decrement('pending_days', $leave->total_days ?? 0);
            $allocation->increment('used_days',    $leave->total_days ?? 0);
            $allocation->decrement('remaining_days', $leave->total_days ?? 0);
            if ($leave->total_hours) {
                $allocation->decrement('pending_hours', $leave->total_hours);
                $allocation->increment('used_hours',    $leave->total_hours);
            }
        } elseif ($action === 'submit') {
            $allocation->increment('pending_days',   $leave->total_days ?? 0);
            $allocation->decrement('remaining_days', $leave->total_days ?? 0);
            if ($leave->total_hours) $allocation->increment('pending_hours', $leave->total_hours);
        } elseif ($action === 'cancel') {
            if ($leave->status === 'approved') {
                $allocation->decrement('used_days', $leave->total_days ?? 0);
                if ($leave->total_hours) $allocation->decrement('used_hours', $leave->total_hours);
            } else {
                $allocation->decrement('pending_days', $leave->total_days ?? 0);
                if ($leave->total_hours) $allocation->decrement('pending_hours', $leave->total_hours);
            }
            $allocation->increment('remaining_days', $leave->total_days ?? 0);
        }
    }

    public function notifyManager(LeaveRequest $leave): void
    {
        try {
            // Notify the employee's manager (if set) and all HR managers
            $recipients = User::whereHas('roles', fn($q) => $q->whereIn('name', ['hr_manager','hr_staff','super_admin']))->get();
            // Also add direct manager if employee has one
            if ($leave->employee?->manager_id) {
                $manager = User::find($leave->employee->manager_id);
                if ($manager) $recipients->push($manager);
            }
            foreach ($recipients->unique('id') as $user) {
                if ($user->email) {
                    Mail::to($user->email)->queue(new LeaveStatusMail($leave, 'submitted'));
                }
            }
        } catch (\Throwable $e) {
            Log::warning('LeaveService::notifyManager failed: ' . $e->getMessage());
        }
    }
    public function notifyEmployee(LeaveRequest $leave, string $status): void
    {
        try {
            $email = $leave->employee?->email;
            if ($email) {
                Mail::to($email)->queue(new LeaveStatusMail($leave, $status));
            }
        } catch (\Throwable $e) {
            Log::warning('LeaveService::notifyEmployee failed: ' . $e->getMessage());
        }
    }
}
