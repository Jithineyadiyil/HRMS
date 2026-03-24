<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles attendance: check-in, check-out, daily status, reports, and dashboard.
 */
class AttendanceController extends Controller
{
    // ── Check-in ──────────────────────────────────────────────────────────

    public function checkIn(Request $request): JsonResponse
    {
        $employee = auth()->user()->employee;
        $today    = now()->toDateString();

        $existing = AttendanceLog::where('employee_id', $employee->id)
            ->whereDate('date', $today)
            ->first();

        if ($existing && $existing->check_in) {
            return response()->json([
                'message' => 'Already checked in today at ' . $existing->check_in,
                'log'     => $existing,
            ], 422);
        }

        $log = AttendanceLog::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => $today],
            [
                'check_in'   => now()->format('H:i:s'),
                'source'     => 'api',
                'ip_address' => $request->ip(),
                'status'     => 'present',
            ]
        );

        return response()->json(['message' => 'Check-in recorded', 'log' => $log]);
    }

    // ── Check-out ─────────────────────────────────────────────────────────

    public function checkOut(Request $request): JsonResponse
    {
        $employee = auth()->user()->employee;
        $today    = now()->toDateString();

        $log           = AttendanceLog::where('employee_id', $employee->id)
            ->whereDate('date', $today)
            ->firstOrFail();

        $checkOutCarbon = now();
        $checkInCarbon  = Carbon::parse($today . ' ' . $log->check_in);

        $log->update([
            'check_out'     => $checkOutCarbon->format('H:i:s'),
            'total_minutes' => (int) $checkInCarbon->diffInMinutes($checkOutCarbon),
        ]);

        return response()->json(['message' => 'Check-out recorded', 'log' => $log->fresh()]);
    }

    // ── Today ─────────────────────────────────────────────────────────────

    public function today(): JsonResponse
    {
        $employee = auth()->user()->employee;

        if (! $employee) {
            return response()->json(['log' => null]);
        }

        $log = AttendanceLog::where('employee_id', $employee->id)
            ->whereDate('date', now()->toDateString())
            ->first();

        return response()->json(['log' => $log]);
    }

    // ── Employee log ──────────────────────────────────────────────────────

    public function employeeLog(Request $request, int $empId): JsonResponse
    {
        $logs = AttendanceLog::where('employee_id', $empId)
            ->when($request->month, fn ($q) => $q->whereMonth('date', $request->month))
            ->when($request->year,  fn ($q) => $q->whereYear('date', $request->year))
            ->orderBy('date', 'desc')
            ->paginate(31);

        return response()->json($logs);
    }

    // ── Report ────────────────────────────────────────────────────────────

    public function report(Request $request): JsonResponse
    {
        $data = AttendanceLog::with('employee.department')
            ->when(
                $request->department_id,
                fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $request->department_id))
            )
            ->when($request->date_from, fn ($q) => $q->whereDate('date', '>=', $request->date_from))
            ->when($request->date_to,   fn ($q) => $q->whereDate('date', '<=', $request->date_to))
            ->orderBy('date', 'desc')
            ->paginate(50);

        return response()->json($data);
    }

    // ── Manual entry ──────────────────────────────────────────────────────

    public function manualEntry(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date'        => 'required|date',
            'check_in'    => 'nullable|date_format:H:i',
            'check_out'   => 'nullable|date_format:H:i',
            'status'      => 'nullable|in:present,absent,late,half_day',
            'notes'       => 'nullable|string|max:500',
        ]);

        $log = AttendanceLog::updateOrCreate(
            ['employee_id' => $request->employee_id, 'date' => $request->date],
            array_merge($request->except(['employee_id', 'date']), ['source' => 'manual'])
        );

        return response()->json(['log' => $log]);
    }

    // ── Dashboard stats ───────────────────────────────────────────────────

    /**
     * GET /api/v1/attendance/dashboard
     *
     * Returns different payloads depending on the caller's role:
     *  - HR / admin  → organisation-wide stats (today's presence, weekly trend,
     *                  department breakdown, late/absent alerts)
     *  - Employee    → personal stats (this month's summary, daily streak,
     *                  recent log, avg hours)
     *
     * @param  Request $request
     * @return JsonResponse
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = auth()->user();

        if ($user->hasAnyRole(['super_admin', 'hr_manager', 'hr_staff', 'department_manager'])) {
            return response()->json($this->adminDashboard());
        }

        return response()->json($this->employeeDashboard($user->employee));
    }

    // ── Admin dashboard payload ───────────────────────────────────────────

    private function adminDashboard(): array
    {
        $today     = now()->toDateString();
        $weekStart = now()->startOfWeek(Carbon::SUNDAY)->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        // Today's totals
        $todayLogs    = AttendanceLog::whereDate('date', $today)->get();
        $presentToday = $todayLogs->where('status', 'present')->count();
        $lateToday    = $todayLogs->where('status', 'late')->count();
        $absentToday  = $todayLogs->where('status', 'absent')->count();

        // Total active employees
        $totalActive  = \App\Models\Employee::where('status', 'active')->count();
        $notRecorded  = max(0, $totalActive - $todayLogs->count());

        // Attendance rate this month
        $monthLogs     = AttendanceLog::whereDate('date', '>=', $monthStart)
            ->whereDate('date', '<=', $today)
            ->get();
        $monthPresent  = $monthLogs->whereIn('status', ['present', 'late'])->count();
        $monthTotal    = $monthLogs->count();
        $monthRate     = $monthTotal > 0 ? round(($monthPresent / $monthTotal) * 100, 1) : 0;

        // Avg working hours this month
        $avgMinutes    = $monthLogs->where('total_minutes', '>', 0)->avg('total_minutes') ?? 0;
        $avgHours      = round($avgMinutes / 60, 1);

        // Weekly trend (last 7 days)
        $weeklyTrend = [];
        for ($i = 6; $i >= 0; $i--) {
            $day   = now()->subDays($i);
            $dayLogs = AttendanceLog::whereDate('date', $day->toDateString())->get();
            $weeklyTrend[] = [
                'day'     => $day->format('D'),
                'date'    => $day->toDateString(),
                'present' => $dayLogs->whereIn('status', ['present', 'late'])->count(),
                'absent'  => $dayLogs->where('status', 'absent')->count(),
                'late'    => $dayLogs->where('status', 'late')->count(),
                'total'   => $dayLogs->count(),
            ];
        }

        // Department breakdown today
        $deptBreakdown = AttendanceLog::with('employee.department')
            ->whereDate('date', $today)
            ->get()
            ->groupBy(fn ($log) => $log->employee?->department?->name ?? 'Unknown')
            ->map(fn ($logs, $dept) => [
                'department' => $dept,
                'present'    => $logs->whereIn('status', ['present', 'late'])->count(),
                'absent'     => $logs->where('status', 'absent')->count(),
                'total'      => $logs->count(),
            ])
            ->values()
            ->take(8);

        // Late / absent employees today
        $alerts = AttendanceLog::with('employee.department')
            ->whereDate('date', $today)
            ->whereIn('status', ['absent', 'late'])
            ->orderBy('status')
            ->limit(10)
            ->get()
            ->map(fn ($log) => [
                'name'       => $log->employee?->full_name,
                'department' => $log->employee?->department?->name,
                'status'     => $log->status,
                'check_in'   => $log->check_in,
            ]);

        // Employees currently checked in (no checkout yet)
        $checkedInNow = AttendanceLog::whereDate('date', $today)
            ->whereNotNull('check_in')
            ->whereNull('check_out')
            ->count();

        return [
            'type' => 'admin',
            'summary' => [
                'total_active'    => $totalActive,
                'present_today'   => $presentToday,
                'late_today'      => $lateToday,
                'absent_today'    => $absentToday,
                'not_recorded'    => $notRecorded,
                'checked_in_now'  => $checkedInNow,
                'attendance_rate' => $monthRate,
                'avg_hours'       => $avgHours,
            ],
            'weekly_trend'    => $weeklyTrend,
            'dept_breakdown'  => $deptBreakdown,
            'alerts'          => $alerts,
        ];
    }

    // ── Employee dashboard payload ─────────────────────────────────────────

    private function employeeDashboard(?\App\Models\Employee $employee): array
    {
        if (! $employee) {
            return ['type' => 'employee', 'summary' => [], 'recent' => [], 'weekly' => []];
        }

        $today      = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        // Today's log
        $todayLog = AttendanceLog::where('employee_id', $employee->id)
            ->whereDate('date', $today)
            ->first();

        // This month's logs
        $monthLogs = AttendanceLog::where('employee_id', $employee->id)
            ->whereDate('date', '>=', $monthStart)
            ->whereDate('date', '<=', $today)
            ->get();

        $presentDays = $monthLogs->whereIn('status', ['present', 'late'])->count();
        $absentDays  = $monthLogs->where('status', 'absent')->count();
        $lateDays    = $monthLogs->where('status', 'late')->count();
        $totalWorked = $monthLogs->sum('total_minutes');
        $avgMinutes  = $monthLogs->where('total_minutes', '>', 0)->avg('total_minutes') ?? 0;

        // Attendance rate this month
        $workingDays = now()->startOfMonth()->diffInWeekdays(now()) + 1;
        $rate = $workingDays > 0 ? round(($presentDays / $workingDays) * 100, 1) : 0;

        // Current streak (consecutive present days)
        $streak = 0;
        $cursor = now();
        while ($streak < 30) {
            $dayStr = $cursor->toDateString();
            $log    = AttendanceLog::where('employee_id', $employee->id)
                ->whereDate('date', $dayStr)
                ->whereIn('status', ['present', 'late'])
                ->exists();
            if (! $log) break;
            $streak++;
            $cursor->subWeekday();
        }

        // Last 7 days personal log
        $weekly = [];
        for ($i = 6; $i >= 0; $i--) {
            $day    = now()->subDays($i);
            $dayLog = AttendanceLog::where('employee_id', $employee->id)
                ->whereDate('date', $day->toDateString())
                ->first();
            $weekly[] = [
                'day'           => $day->format('D'),
                'date'          => $day->toDateString(),
                'status'        => $dayLog?->status ?? 'no_record',
                'check_in'      => $dayLog?->check_in,
                'check_out'     => $dayLog?->check_out,
                'total_minutes' => $dayLog?->total_minutes,
            ];
        }

        // Recent 5 logs
        $recent = AttendanceLog::where('employee_id', $employee->id)
            ->orderBy('date', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($l) => [
                'date'          => $l->date->toDateString(),
                'check_in'      => $l->check_in,
                'check_out'     => $l->check_out,
                'total_minutes' => $l->total_minutes,
                'status'        => $l->status,
            ]);

        return [
            'type'      => 'employee',
            'today_log' => $todayLog,
            'summary'   => [
                'present_days'    => $presentDays,
                'absent_days'     => $absentDays,
                'late_days'       => $lateDays,
                'total_hours'     => round($totalWorked / 60, 1),
                'avg_hours'       => round($avgMinutes / 60, 1),
                'attendance_rate' => $rate,
                'streak'          => $streak,
            ],
            'weekly' => $weekly,
            'recent' => $recent,
        ];
    }
}
