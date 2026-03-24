<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles attendance: check-in, check-out, daily status, and reports.
 *
 * All times use Carbon so the APP_TIMEZONE setting is always respected.
 * Never use strtotime() or time() — those ignore Laravel's timezone config.
 */
class AttendanceController extends Controller
{
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

    public function checkOut(Request $request): JsonResponse
    {
        $employee = auth()->user()->employee;
        $today    = now()->toDateString();

        $log = AttendanceLog::where('employee_id', $employee->id)
            ->whereDate('date', $today)
            ->firstOrFail();

        $checkOutCarbon = now();
        // Parse check_in as a full datetime in app timezone — never a bare time string
        $checkInCarbon  = Carbon::parse($today . ' ' . $log->check_in);

        $log->update([
            'check_out'     => $checkOutCarbon->format('H:i:s'),
            'total_minutes' => (int) $checkInCarbon->diffInMinutes($checkOutCarbon),
        ]);

        return response()->json(['message' => 'Check-out recorded', 'log' => $log->fresh()]);
    }

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

    public function employeeLog(Request $request, int $empId): JsonResponse
    {
        $logs = AttendanceLog::where('employee_id', $empId)
            ->when($request->month, fn ($q) => $q->whereMonth('date', $request->month))
            ->when($request->year,  fn ($q) => $q->whereYear('date', $request->year))
            ->orderBy('date', 'desc')
            ->paginate(31);

        return response()->json($logs);
    }

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
}
