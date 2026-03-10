<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Employee;
use Illuminate\Http\Request;

class AttendanceController extends Controller {
    public function checkIn(Request $request) {
        $employee = auth()->user()->employee;
        $today    = now()->toDateString();
        $existing = AttendanceLog::where('employee_id', $employee->id)->where('date', $today)->first();
        if ($existing && $existing->check_in) return response()->json(['message' => 'Already checked in today'], 422);
        $log = AttendanceLog::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => $today],
            ['check_in' => now()->toTimeString(), 'source' => 'api', 'ip_address' => $request->ip(), 'status' => 'present']
        );
        return response()->json(['message' => 'Check-in recorded', 'log' => $log]);
    }

    public function checkOut(Request $request) {
        $employee = auth()->user()->employee;
        $today    = now()->toDateString();
        $log      = AttendanceLog::where('employee_id', $employee->id)->where('date', $today)->firstOrFail();
        $checkIn  = strtotime($log->check_in);
        $checkOut = time();
        $log->update(['check_out' => now()->toTimeString(), 'total_minutes' => round(($checkOut - $checkIn) / 60)]);
        return response()->json(['message' => 'Check-out recorded', 'log' => $log]);
    }

    public function today() {
        $employee = auth()->user()->employee;
        $log = AttendanceLog::where('employee_id', $employee->id)->where('date', now()->toDateString())->first();
        return response()->json(['log' => $log]);
    }

    public function employeeLog(Request $request, $empId) {
        $logs = AttendanceLog::where('employee_id', $empId)
            ->when($request->month, fn($q) => $q->whereMonth('date', $request->month))
            ->when($request->year,  fn($q) => $q->whereYear('date', $request->year))
            ->orderBy('date', 'desc')->paginate(31);
        return response()->json($logs);
    }

    public function report(Request $request) {
        $data = AttendanceLog::with('employee.department')
            ->when($request->department_id, fn($q) => $q->whereHas('employee', fn($e) => $e->where('department_id', $request->department_id)))
            ->when($request->date_from, fn($q) => $q->where('date', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->where('date', '<=', $request->date_to))
            ->paginate(50);
        return response()->json($data);
    }

    public function manualEntry(Request $request) {
        $request->validate(['employee_id'=>'required|exists:employees,id','date'=>'required|date','check_in'=>'nullable|date_format:H:i','check_out'=>'nullable|date_format:H:i']);
        $log = AttendanceLog::updateOrCreate(
            ['employee_id' => $request->employee_id, 'date' => $request->date],
            array_merge($request->except(['employee_id','date']), ['source'=>'manual'])
        );
        return response()->json(['log' => $log]);
    }

    public function import(Request $request) {
        $request->validate(['file' => 'required|file|mimes:csv,xlsx']);
        // Handled by AttendanceImport (maatwebsite/excel)
        return response()->json(['message' => 'Import queued for processing']);
    }
}
