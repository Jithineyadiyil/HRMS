<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

/**
 * AttendanceController
 *
 * Handles all attendance-related operations:
 *   - Employee self check-in / check-out
 *   - Daily status query
 *   - HR manual entry & edit
 *   - HR/Admin reports with filters
 *   - Monthly summary & stats
 *   - Employee monthly log
 *
 * @package App\Http\Controllers\API
 */
class AttendanceController extends Controller
{
    // ════════════════════════════════════════════════════════════════════
    // EMPLOYEE SELF-SERVICE
    // ════════════════════════════════════════════════════════════════════

    /**
     * Record check-in for the authenticated employee.
     *
     * @param  Request $request
     * @return JsonResponse  { message, log }
     *
     * @throws 422  Already checked in today
     * @throws 404  No employee linked to user
     */
    public function checkIn(Request $request): JsonResponse
    {
        $employee = auth()->user()->employee;

        if (!$employee) {
            return response()->json(['message' => 'No employee record linked to your account.'], 404);
        }

        $today = now()->toDateString();

        $existing = AttendanceLog::where('employee_id', $employee->id)
            ->where('date', $today)
            ->first();

        if ($existing && $existing->check_in) {
            return response()->json([
                'message' => 'You have already checked in today at ' . $existing->check_in,
                'log'     => $existing,
            ], 422);
        }

        $now           = Carbon::now();                     // Asia/Riyadh — always correct local time
        $checkInTime   = $now->format('H:i:s');              // store as HH:MM:SS in local tz
        $workLateLimit = Carbon::today()->setTime(9, 30, 0); // 09:30 local
        $status        = $now->gt($workLateLimit) ? 'late' : 'present';

        $log = AttendanceLog::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => $today],
            [
                'check_in'   => $checkInTime,
                'status'     => $status,
                'source'     => 'api',
                'ip_address' => $request->ip(),
            ]
        );

        return response()->json([
            'message' => 'Check-in recorded successfully.',
            'log'     => $log->append(['duration_label']),
        ], 201);
    }

    /**
     * Record check-out for the authenticated employee.
     *
     * @param  Request $request
     * @return JsonResponse  { message, log }
     *
     * @throws 422  Not checked in today / already checked out
     * @throws 404  No employee linked to user
     */
    public function checkOut(Request $request): JsonResponse
    {
        $employee = auth()->user()->employee;

        if (!$employee) {
            return response()->json(['message' => 'No employee record linked to your account.'], 404);
        }

        $today = now()->toDateString();

        $log = AttendanceLog::where('employee_id', $employee->id)
            ->where('date', $today)
            ->first();

        if (!$log || !$log->check_in) {
            return response()->json(['message' => 'You have not checked in today.'], 422);
        }

        if ($log->check_out) {
            return response()->json([
                'message' => 'You have already checked out today at ' . $log->check_out,
                'log'     => $log,
            ], 422);
        }

        // Use Carbon for both sides — timezone-aware, no strtotime/time() ambiguity
        $checkInCarbon = Carbon::createFromFormat('Y-m-d H:i:s', $today . ' ' . $log->check_in);
        $checkOutCarbon = Carbon::now(); // Asia/Riyadh — guaranteed same tz as check_in
        $minutes        = (int) round($checkInCarbon->diffInMinutes($checkOutCarbon));

        // Determine final status:
        //   < 4 hours → half_day (overrides everything)
        //   checked in late but full day → keep 'late'
        //   normal full day → keep existing status (e.g. 'present')
        if ($minutes < 240) {
            $status = 'half_day';
        } else {
            $status = $log->status; // preserves 'late', 'present', etc.
        }

        $log->update([
            'check_out'     => $checkOutCarbon->format('H:i:s'), // consistent HH:MM:SS format
            'total_minutes' => max($minutes, 0),
            'status'        => $status,
        ]);

        return response()->json([
            'message' => 'Check-out recorded successfully.',
            'log'     => $log->fresh()->append(['duration_label', 'total_hours']),
        ]);
    }

    /**
     * Get today's attendance log for the authenticated employee.
     *
     * @return JsonResponse  { log: AttendanceLog|null, server_time }
     */
    public function today(): JsonResponse
    {
        $employee = auth()->user()->employee;

        if (!$employee) {
            return response()->json(['log' => null, 'server_time' => now()->toTimeString()]);
        }

        $log = AttendanceLog::where('employee_id', $employee->id)
            ->where('date', now()->toDateString())
            ->first();

        return response()->json([
            'log'           => $log ? $log->append(['duration_label', 'total_hours']) : null,
            'server_time'   => now()->format('H:i:s'),       // local Asia/Riyadh time
            'server_date'   => now()->toDateString(),
            'server_timezone' => config('app.timezone'),     // 'Asia/Riyadh' — for frontend awareness
        ]);
    }

    /**
     * Get monthly attendance log for the authenticated employee.
     *
     * @param  Request $request  ?month=int&year=int
     * @return JsonResponse  { logs[], summary, month, year }
     */
    public function myLog(Request $request): JsonResponse
    {
        $employee = auth()->user()->employee;

        if (!$employee) {
            return response()->json(['logs' => [], 'summary' => []]);
        }

        $month = (int) ($request->month ?? now()->month);
        $year  = (int) ($request->year  ?? now()->year);

        $logs = AttendanceLog::where('employee_id', $employee->id)
            ->forMonth($month, $year)
            ->orderBy('date')
            ->get()
            ->append(['duration_label', 'total_hours']);

        $summary = $this->buildSummary($logs);

        return response()->json([
            'logs'    => $logs,
            'summary' => $summary,
            'month'   => $month,
            'year'    => $year,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════
    // HR / ADMIN OPERATIONS
    // ════════════════════════════════════════════════════════════════════

    /**
     * HR attendance report with filters.
     *
     * @param  Request $request  ?date_from&date_to&department_id&employee_id&status&per_page
     * @return JsonResponse  Paginated AttendanceLogs with employee data
     */
    public function report(Request $request): JsonResponse
    {
        $request->validate([
            'date_from'     => 'nullable|date',
            'date_to'       => 'nullable|date|after_or_equal:date_from',
            'department_id' => 'nullable|exists:departments,id',
            'employee_id'   => 'nullable|exists:employees,id',
            'status'        => 'nullable|in:present,absent,late,half_day,on_leave,holiday',
            'per_page'      => 'nullable|integer|min:1|max:200',
        ]);

        $from = $request->date_from ?? now()->startOfMonth()->toDateString();
        $to   = $request->date_to   ?? now()->toDateString();

        $query = AttendanceLog::with(['employee.department', 'employee.designation'])
            ->inRange($from, $to)
            ->when($request->employee_id,   fn($q) => $q->where('employee_id', $request->employee_id))
            ->when($request->status,        fn($q) => $q->where('status', $request->status))
            ->when($request->department_id, fn($q) =>
                $q->whereHas('employee', fn($e) => $e->where('department_id', $request->department_id))
            )
            ->orderBy('date', 'desc')
            ->orderBy('employee_id');

        $logs = $query->paginate((int) ($request->per_page ?? 50));

        // Append computed fields
        $logs->getCollection()->transform(fn($l) => $l->append(['duration_label', 'total_hours']));

        return response()->json($logs);
    }

    /**
     * Daily attendance snapshot — who is in/out/absent today.
     *
     * @param  Request $request  ?date&department_id
     * @return JsonResponse  { date, present, late, absent, on_leave, logs[] }
     */
    public function daily(Request $request): JsonResponse
    {
        $date = $request->date ?? now()->toDateString();

        $employees = Employee::with(['department', 'designation'])
            ->where('status', 'active')
            ->when($request->department_id, fn($q) => $q->where('department_id', $request->department_id))
            ->get();

        $logs = AttendanceLog::with('employee')
            ->where('date', $date)
            ->when($request->department_id, fn($q) =>
                $q->whereHas('employee', fn($e) => $e->where('department_id', $request->department_id))
            )
            ->get()
            ->keyBy('employee_id');

        $result = $employees->map(function ($emp) use ($logs) {
            $log = $logs->get($emp->id);
            return [
                'employee'    => $emp,
                'status'      => $log?->status ?? 'absent',
                'check_in'    => $log?->check_in,
                'check_out'   => $log?->check_out,
                'duration'    => $log?->duration_label ?? '—',
                'source'      => $log?->source,
                'log_id'      => $log?->id,
            ];
        });

        return response()->json([
            'date'     => $date,
            'total'    => $employees->count(),
            'present'  => $result->where('status', 'present')->count(),
            'late'     => $result->where('status', 'late')->count(),
            'absent'   => $result->where('status', 'absent')->count(),
            'on_leave' => $result->where('status', 'on_leave')->count(),
            'records'  => $result->values(),
        ]);
    }

    /**
     * Monthly stats summary for HR dashboard widget.
     *
     * @param  Request $request  ?month&year&department_id
     * @return JsonResponse  { present_days, absent_days, late_days, avg_hours, ... }
     */
    public function stats(Request $request): JsonResponse
    {
        $month = (int) ($request->month ?? now()->month);
        $year  = (int) ($request->year  ?? now()->year);

        $query = AttendanceLog::forMonth($month, $year)
            ->when($request->department_id, fn($q) =>
                $q->whereHas('employee', fn($e) => $e->where('department_id', $request->department_id))
            );

        $records    = $query->get();
        $totalDays  = Carbon::createFromDate($year, $month, 1)->daysInMonth;
        $workingDays = $this->countWorkingDays($month, $year);

        return response()->json([
            'month'            => $month,
            'year'             => $year,
            'total_days'       => $totalDays,
            'working_days'     => $workingDays,
            'present_count'    => $records->whereIn('status', ['present', 'late'])->count(),
            'absent_count'     => $records->where('status', 'absent')->count(),
            'late_count'       => $records->where('status', 'late')->count(),
            'half_day_count'   => $records->where('status', 'half_day')->count(),
            'on_leave_count'   => $records->where('status', 'on_leave')->count(),
            'avg_hours'        => $records->avg('total_minutes')
                ? round($records->avg('total_minutes') / 60, 1) : 0,
        ]);
    }

    /**
     * Get monthly attendance log for a specific employee (HR view).
     *
     * @param  Request $request  ?month&year
     * @param  int     $empId
     * @return JsonResponse  { logs[], summary, employee }
     */
    public function employeeLog(Request $request, int $empId): JsonResponse
    {
        $employee = Employee::with(['department', 'designation'])->findOrFail($empId);

        $month = (int) ($request->month ?? now()->month);
        $year  = (int) ($request->year  ?? now()->year);

        $logs = AttendanceLog::where('employee_id', $empId)
            ->forMonth($month, $year)
            ->orderBy('date')
            ->get()
            ->append(['duration_label', 'total_hours']);

        $summary = $this->buildSummary($logs);

        return response()->json([
            'employee' => $employee,
            'logs'     => $logs,
            'summary'  => $summary,
            'month'    => $month,
            'year'     => $year,
        ]);
    }

    /**
     * HR manual entry — create or update an attendance record.
     *
     * @param  Request $request
     * @return JsonResponse  { log }
     *
     * @throws 422  Validation errors
     */
    public function manualEntry(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date'        => 'required|date|before_or_equal:today',
            'check_in'    => 'nullable|date_format:H:i,H:i:s',
            'check_out'   => ['nullable', 'date_format:H:i,H:i:s', 'after:check_in'],
            'status'      => ['nullable', Rule::in(['present','absent','late','half_day','on_leave','holiday'])],
            'notes'       => 'nullable|string|max:500',
        ]);

        // Auto-calculate duration
        $minutes = null;
        if (!empty($validated['check_in']) && !empty($validated['check_out'])) {
            $in  = Carbon::createFromFormat('Y-m-d H:i', $validated['date'] . ' ' . $validated['check_in']);
            $out = Carbon::createFromFormat('Y-m-d H:i', $validated['date'] . ' ' . $validated['check_out']);
            $minutes = max(0, (int) round($in->diffInMinutes($out)));
        }

        $log = AttendanceLog::updateOrCreate(
            ['employee_id' => $validated['employee_id'], 'date' => $validated['date']],
            array_merge($validated, [
                'total_minutes' => $minutes,
                'source'        => 'manual',
            ])
        );

        return response()->json([
            'message' => 'Attendance record saved.',
            'log'     => $log->load('employee')->append(['duration_label', 'total_hours']),
        ], 201);
    }

    /**
     * Update an existing attendance log record.
     *
     * @param  Request $request
     * @param  int     $id
     * @return JsonResponse  { log }
     */
    public function updateLog(Request $request, int $id): JsonResponse
    {
        $log = AttendanceLog::findOrFail($id);

        $validated = $request->validate([
            'check_in'  => 'nullable|date_format:H:i,H:i:s',
            'check_out' => 'nullable|date_format:H:i,H:i:s',
            'status'    => ['nullable', Rule::in(['present','absent','late','half_day','on_leave','holiday'])],
            'notes'     => 'nullable|string|max:500',
        ]);

        $minutes = $log->total_minutes;
        if (!empty($validated['check_in']) && !empty($validated['check_out'])) {
            $dateStr = $log->date instanceof \Carbon\Carbon ? $log->date->toDateString() : $log->date;
            $in      = Carbon::createFromFormat('Y-m-d H:i', $dateStr . ' ' . $validated['check_in']);
            $out     = Carbon::createFromFormat('Y-m-d H:i', $dateStr . ' ' . $validated['check_out']);
            $minutes = max(0, (int) round($in->diffInMinutes($out)));
        }

        $log->update(array_merge($validated, ['total_minutes' => $minutes]));

        return response()->json([
            'message' => 'Record updated.',
            'log'     => $log->fresh()->append(['duration_label', 'total_hours']),
        ]);
    }

    /**
     * Delete an attendance log record (HR/Admin only).
     *
     * @param  int $id
     * @return JsonResponse
     */
    public function deleteLog(int $id): JsonResponse
    {
        $log = AttendanceLog::findOrFail($id);
        $log->delete();

        return response()->json(['message' => 'Attendance record deleted.']);
    }

    // ════════════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ════════════════════════════════════════════════════════════════════

    /**
     * Build a summary array from a collection of AttendanceLogs.
     *
     * @param  \Illuminate\Support\Collection $logs
     * @return array
     */
    private function buildSummary($logs): array
    {
        return [
            'total_days'   => $logs->count(),
            'present'      => $logs->where('status', 'present')->count(),
            'late'         => $logs->where('status', 'late')->count(),
            'absent'       => $logs->where('status', 'absent')->count(),
            'half_day'     => $logs->where('status', 'half_day')->count(),
            'on_leave'     => $logs->where('status', 'on_leave')->count(),
            'total_hours'  => round($logs->sum('total_minutes') / 60, 1),
            'avg_hours'    => $logs->where('total_minutes', '>', 0)->count()
                ? round($logs->sum('total_minutes') / max($logs->where('total_minutes', '>', 0)->count(), 1) / 60, 1)
                : 0,
        ];
    }

    /**
     * Count working days (Mon–Fri) in a given month.
     *
     * @param  int $month
     * @param  int $year
     * @return int
     */
    private function countWorkingDays(int $month, int $year): int
    {
        $start   = Carbon::createFromDate($year, $month, 1);
        $end     = $start->copy()->endOfMonth();
        $working = 0;
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            if (!$d->isWeekend()) $working++;
        }
        return $working;
    }
}
