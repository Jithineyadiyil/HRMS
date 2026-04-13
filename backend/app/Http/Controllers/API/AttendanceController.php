<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Handles attendance: check-in, check-out, daily status, reports, and dashboard.
 *
 * Performance note: the weekly-trend section previously issued one DB query per
 * day (7 queries).  It now runs a single date-ranged query and groups in PHP.
 */
class AttendanceController extends Controller
{
    // ── Check-in ──────────────────────────────────────────────────────────

    /**
     * Record a check-in for the authenticated employee.
     *
     * @param  Request      $request
     * @return JsonResponse           422 if already checked in today
     */
    public function checkIn(Request $request): JsonResponse
    {
        $employee = auth()->user()->employee;
        $today    = now()->toDateString();

        $existing = AttendanceLog::where('employee_id', $employee->id)
            ->whereDate('date', $today)
            ->first();

        if ($existing?->check_in) {
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

    /**
     * Record a check-out for the authenticated employee.
     *
     * @param  Request      $request
     * @return JsonResponse
     */
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

    /**
     * Return today's attendance log for the authenticated employee.
     *
     * @return JsonResponse
     */
    public function today(): JsonResponse
    {
        $employee = auth()->user()->employee;

        if (!$employee) {
            return response()->json(['log' => null]);
        }

        $log = AttendanceLog::where('employee_id', $employee->id)
            ->whereDate('date', now()->toDateString())
            ->first();

        return response()->json(['log' => $log]);
    }

    // ── Employee log ──────────────────────────────────────────────────────

    /**
     * Return paginated attendance history for a specific employee.
     *
     * @param  Request      $request  Supports month, year filters
     * @param  int          $empId
     * @return JsonResponse
     */
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

    /**
     * Return a paginated attendance report with optional filters.
     *
     * @param  Request      $request  Supports department_id, date_from, date_to
     * @return JsonResponse
     */
    public function report(Request $request): JsonResponse
    {
        $data = AttendanceLog::with('employee.department')
            ->when($request->department_id, fn ($q) =>
                $q->whereHas('employee', fn ($e) => $e->where('department_id', $request->department_id))
            )
            ->when($request->date_from, fn ($q) => $q->whereDate('date', '>=', $request->date_from))
            ->when($request->date_to,   fn ($q) => $q->whereDate('date', '<=', $request->date_to))
            ->orderBy('date', 'desc')
            ->paginate(50);

        return response()->json($data);
    }

    // ── Manual entry ──────────────────────────────────────────────────────

    /**
     * Create or update an attendance log record manually (HR/admin).
     *
     * @param  Request      $request
     * @return JsonResponse
     */
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
            array_merge(
                $request->only(['check_in', 'check_out', 'status', 'notes']),
                ['source' => 'manual']
            )
        );

        return response()->json(['log' => $log]);
    }

    // ── Dashboard ─────────────────────────────────────────────────────────

    /**
     * Return role-based dashboard payload.
     *
     * HR / admin roles receive org-wide stats; employees receive personal stats.
     *
     * @param  Request      $request
     * @return JsonResponse
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user  = auth()->user();
        $roles = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->id)
            ->pluck('roles.name')
            ->toArray();

        $isHRAdmin = (bool) array_intersect($roles, ['super_admin', 'hr_manager', 'hr_staff', 'department_manager']);

        if ($isHRAdmin) {
            return response()->json($this->adminDashboard());
        }

        return response()->json($this->employeeDashboard($user->employee));
    }

    // ── Update ────────────────────────────────────────────────────────────

    /**
     * Update an existing attendance log (HR/admin only).
     *
     * @param  Request      $request
     * @param  int          $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $log = AttendanceLog::findOrFail($id);

        $request->validate([
            'check_in'  => 'nullable|date_format:H:i:s',
            'check_out' => 'nullable|date_format:H:i:s',
            'status'    => 'nullable|in:present,absent,late,half_day,on_leave,holiday',
            'notes'     => 'nullable|string|max:500',
        ]);

        $data = $request->only(['check_in', 'check_out', 'status', 'notes']);

        if (!empty($data['check_in'] ?? $log->check_in) && !empty($data['check_out'] ?? $log->check_out)) {
            $cin  = Carbon::parse($log->date->toDateString() . ' ' . ($data['check_in']  ?? $log->check_in));
            $cout = Carbon::parse($log->date->toDateString() . ' ' . ($data['check_out'] ?? $log->check_out));
            $data['total_minutes'] = (int) $cin->diffInMinutes($cout);
        }

        $log->update(array_merge($data, ['source' => 'manual']));

        return response()->json(['message' => 'Attendance record updated.', 'log' => $log->fresh()]);
    }

    // ── Settings ──────────────────────────────────────────────────────────

    /**
     * Return attendance policy settings.
     *
     * @return JsonResponse
     */
    public function getSettings(): JsonResponse
    {
        $defaults = [
            'work_start'         => '08:00',
            'late_after_minutes' => 15,
            'half_day_hours'     => 4,
            'full_day_hours'     => 8,
            'grace_minutes'      => 5,
            'weekend_days'       => [5, 6],
        ];

        $stored = rescue(fn () => json_decode(
            file_get_contents(storage_path('app/attendance_settings.json')), true
        ) ?? [], [], false);

        return response()->json(array_merge($defaults, $stored ?: []));
    }

    /**
     * Save attendance policy settings.
     *
     * @param  Request      $request
     * @return JsonResponse
     */
    public function saveSettings(Request $request): JsonResponse
    {
        $request->validate([
            'work_start'         => 'required|date_format:H:i',
            'late_after_minutes' => 'required|integer|min:0|max:120',
            'half_day_hours'     => 'required|numeric|min:1|max:12',
            'full_day_hours'     => 'required|numeric|min:4|max:24',
            'grace_minutes'      => 'required|integer|min:0|max:60',
            'weekend_days'       => 'required|array',
        ]);

        $settings = $request->only([
            'work_start', 'late_after_minutes', 'half_day_hours',
            'full_day_hours', 'grace_minutes', 'weekend_days',
        ]);

        file_put_contents(
            storage_path('app/attendance_settings.json'),
            json_encode($settings, JSON_PRETTY_PRINT)
        );

        return response()->json(['message' => 'Settings saved.', 'settings' => $settings]);
    }

    // ── Admin dashboard payload ───────────────────────────────────────────

    /**
     * Build the admin/HR dashboard payload.
     *
     * FIX: Weekly trend previously ran 7 individual DB queries (N+1).
     * Now runs a single query for the 7-day window and groups in PHP.
     *
     * @return array<string, mixed>
     */
    private function adminDashboard(): array
    {
        $today      = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $weekStart  = now()->subDays(6)->toDateString(); // last 7 days

        // Today's totals
        $todayLogs    = AttendanceLog::whereDate('date', $today)->get();
        $presentToday = $todayLogs->where('status', 'present')->count();
        $lateToday    = $todayLogs->where('status', 'late')->count();
        $absentToday  = $todayLogs->where('status', 'absent')->count();

        $totalActive = Employee::where('status', 'active')->count();
        $notRecorded = max(0, $totalActive - $todayLogs->count());

        // Attendance rate this month
        $monthLogs    = AttendanceLog::whereDate('date', '>=', $monthStart)->whereDate('date', '<=', $today)->get();
        $monthPresent = $monthLogs->whereIn('status', ['present', 'late'])->count();
        $monthTotal   = $monthLogs->count();
        $monthRate    = $monthTotal > 0 ? round(($monthPresent / $monthTotal) * 100, 1) : 0;
        $avgMinutes   = $monthLogs->where('total_minutes', '>', 0)->avg('total_minutes') ?? 0;
        $avgHours     = round($avgMinutes / 60, 1);

        // FIX: Weekly trend — one query for last 7 days, grouped in PHP
        $weeklyLogs = AttendanceLog::whereDate('date', '>=', $weekStart)
            ->whereDate('date', '<=', $today)
            ->get()
            ->groupBy(fn ($l) => $l->date instanceof Carbon
                ? $l->date->toDateString()
                : (is_string($l->date) ? substr($l->date, 0, 10) : (string) $l->date)
            );

        $weeklyTrend = [];
        for ($i = 6; $i >= 0; $i--) {
            $day    = now()->subDays($i);
            $dayStr = $day->toDateString();
            $logs   = $weeklyLogs->get($dayStr, collect());

            $weeklyTrend[] = [
                'day'     => $day->format('D'),
                'date'    => $dayStr,
                'present' => $logs->whereIn('status', ['present', 'late'])->count(),
                'absent'  => $logs->where('status', 'absent')->count(),
                'late'    => $logs->where('status', 'late')->count(),
                'total'   => $logs->count(),
            ];
        }

        // Department breakdown today
        $deptBreakdown = AttendanceLog::with('employee.department')
            ->whereDate('date', $today)
            ->get()
            ->groupBy(fn ($log) => $log->employee?->department?->name ?? 'Unknown')
            ->map(fn (Collection $logs, string $dept) => [
                'department' => $dept,
                'present'    => $logs->whereIn('status', ['present', 'late'])->count(),
                'absent'     => $logs->where('status', 'absent')->count(),
                'total'      => $logs->count(),
            ])
            ->values()
            ->take(8);

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

        $checkedInNow = AttendanceLog::whereDate('date', $today)
            ->whereNotNull('check_in')
            ->whereNull('check_out')
            ->count();

        return [
            'type'    => 'admin',
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
            'weekly_trend'   => $weeklyTrend,
            'dept_breakdown' => $deptBreakdown,
            'alerts'         => $alerts,
        ];
    }

    // ── Employee dashboard payload ─────────────────────────────────────────

    /**
     * Build the personal attendance dashboard for an employee.
     *
     * @param  Employee|null $employee
     * @return array<string, mixed>
     */
    private function employeeDashboard(?Employee $employee): array
    {
        if (!$employee) {
            return ['type' => 'employee', 'summary' => [], 'recent' => [], 'weekly' => []];
        }

        $today      = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        $todayLog  = AttendanceLog::where('employee_id', $employee->id)
            ->whereDate('date', $today)
            ->first();

        $monthLogs   = AttendanceLog::where('employee_id', $employee->id)
            ->whereDate('date', '>=', $monthStart)
            ->whereDate('date', '<=', $today)
            ->get();

        $presentDays = $monthLogs->whereIn('status', ['present', 'late'])->count();
        $absentDays  = $monthLogs->where('status', 'absent')->count();
        $lateDays    = $monthLogs->where('status', 'late')->count();
        $totalWorked = $monthLogs->sum('total_minutes');
        $avgMinutes  = $monthLogs->where('total_minutes', '>', 0)->avg('total_minutes') ?? 0;
        $workingDays = max(1, now()->startOfMonth()->diffInWeekdays(now()) + 1);
        $rate        = round(($presentDays / $workingDays) * 100, 1);

        // Streak: consecutive attended weekdays (up to 30)
        $streak = 0;
        $cursor = now()->copy();
        while ($streak < 30) {
            $dayStr = $cursor->toDateString();
            if (!AttendanceLog::where('employee_id', $employee->id)
                ->whereDate('date', $dayStr)
                ->whereIn('status', ['present', 'late'])
                ->exists()) {
                break;
            }
            $streak++;
            $cursor->subWeekday();
        }

        // FIX: personal weekly trend — one query, grouped in PHP
        $weekStart  = now()->subDays(6)->toDateString();
        $weeklyLogs = AttendanceLog::where('employee_id', $employee->id)
            ->whereDate('date', '>=', $weekStart)
            ->whereDate('date', '<=', $today)
            ->get()
            ->keyBy(fn ($l) => is_string($l->date) ? substr($l->date, 0, 10) : $l->date->toDateString());

        $weekly = [];
        for ($i = 6; $i >= 0; $i--) {
            $day    = now()->subDays($i);
            $dayStr = $day->toDateString();
            $dayLog = $weeklyLogs->get($dayStr);

            $weekly[] = [
                'day'           => $day->format('D'),
                'date'          => $dayStr,
                'status'        => $dayLog?->status ?? 'no_record',
                'check_in'      => $dayLog?->check_in,
                'check_out'     => $dayLog?->check_out,
                'total_minutes' => $dayLog?->total_minutes,
            ];
        }

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
