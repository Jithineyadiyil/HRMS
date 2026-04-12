<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use App\Models\PayrollComponent;
use App\Models\Payslip;
use App\Services\PayrollService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PayrollController extends Controller {

    protected $service;

    public function __construct(PayrollService $service) {
        $this->service = $service;
    }

    public function index(Request $request) {
        $payrolls = Payroll::with(['creator', 'approver'])
            ->withCount('payslips')
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->year,   fn($q) => $q->where('month', 'like', $request->year . '%'))
            ->orderBy('created_at', 'desc')
            ->paginate(12);
        return response()->json($payrolls);
    }

    public function stats(): \Illuminate\Http\JsonResponse
    {
        $safe = fn($fn) => rescue($fn, 0, false);
        $latest = Payroll::orderBy('created_at','desc')->first();
        return response()->json([
            'total_runs'       => $safe(fn() => Payroll::count()),
            'pending_approval' => $safe(fn() => Payroll::where('status','pending_approval')->count()),
            'approved'         => $safe(fn() => Payroll::where('status','approved')->count()),
            'paid'             => $safe(fn() => Payroll::where('status','paid')->count()),
            'latest_net'       => $latest?->total_net ?? 0,
            'latest_gross'     => $latest?->total_gross ?? 0,
            'latest_month'     => $latest?->month ?? null,
        ]);
    }

    public function run(Request $request) {
        $request->validate([
            'month'        => 'required|date_format:Y-m',
            'period_start' => 'required|date',
            'period_end'   => 'required|date|after_or_equal:period_start',
        ]);

        // Block if an active (non-rejected) payroll exists for this month
        $existing = Payroll::where('month', $request->month)
            ->whereIn('status', ['pending_approval', 'approved', 'paid'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Payroll for ' . $request->month . ' already exists (status: ' . $existing->status . '). Reopen or reject it first.',
                'payroll_id' => $existing->id,
            ], 422);
        }

        // Delete any rejected payroll for this month before re-running
        Payroll::where('month', $request->month)->where('status', 'rejected')->delete();

        try {
            $payroll = $this->service->runPayroll($request->all(), auth()->id());
            return response()->json(['message' => 'Payroll run successfully', 'payroll' => $payroll], 201);
        } catch (\Exception $e) {
            // Clean up failed payroll attempt
            Payroll::where('month', $request->month)->where('status', 'pending_approval')
                   ->where('created_by', auth()->id())
                   ->whereDate('created_at', today())
                   ->delete();
            return response()->json(['message' => 'Payroll run failed: ' . $e->getMessage()], 500);
        }
    }

    public function show($id) {
        $payroll = Payroll::with(['payslips.employee.department', 'creator', 'approver'])->findOrFail($id);
        return response()->json(['payroll' => $payroll]);
    }

    public function approve($id) {
        // Role guard via raw DB
        $roles = rescue(fn() => DB::table('model_has_roles')
            ->join('roles','roles.id','=','model_has_roles.role_id')
            ->where('model_has_roles.model_id', auth()->id())
            ->pluck('roles.name')->toArray(), [], false);

        if (!array_intersect($roles, ['super_admin','hr_manager','finance_manager'])) {
            return response()->json(['message' => 'Only Finance or HR managers can approve payroll.'], 403);
        }

        $payroll = Payroll::findOrFail($id);
        if ($payroll->status !== 'pending_approval') {
            return response()->json(['message' => 'Payroll is not pending approval'], 422);
        }
        $payroll->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
        $this->service->dispatchPayslipEmails($payroll);
        return response()->json([
            'message' => 'Payroll approved.',
            'payroll' => $payroll->fresh(),
        ]);
    }

    public function markPaid(Request $request, $id)
    {
        $roles = rescue(fn() => DB::table('model_has_roles')
            ->join('roles','roles.id','=','model_has_roles.role_id')
            ->where('model_has_roles.model_id', auth()->id())
            ->pluck('roles.name')->toArray(), [], false);

        if (!array_intersect($roles, ['super_admin','hr_manager','finance_manager'])) {
            return response()->json(['message' => 'Only Finance or HR managers can mark payroll as paid.'], 403);
        }

        $payroll = Payroll::findOrFail($id);
        if ($payroll->status !== 'approved') {
            return response()->json(['message' => 'Only approved payrolls can be marked as paid.'], 422);
        }
        $payroll->update([
            'status'   => 'paid',
            'paid_at'  => now(),
            'paid_by'  => auth()->id(),
            'notes'    => ($payroll->notes ? $payroll->notes . ' | ' : '') .
                          'Marked paid by ' . auth()->user()->name . ' on ' . now()->toDateTimeString(),
        ]);
        return response()->json(['message' => 'Payroll marked as paid.', 'payroll' => $payroll->fresh()]);
    }

    public function reject(Request $request, $id) {
        $request->validate(['reason' => 'required|string']);
        $payroll = Payroll::findOrFail($id);
        $payroll->update(['status' => 'rejected', 'notes' => $request->reason]);
        return response()->json(['message' => 'Payroll rejected']);
    }

    public function payslips($id) {
        $payslips = Payslip::with(['employee.department'])
            ->where('payroll_id', $id)
            ->paginate(20);
        return response()->json($payslips);
    }

    public function employeeHistory($empId) {
        $payslips = Payslip::with('payroll')
            ->where('employee_id', $empId)
            ->orderBy('created_at', 'desc')
            ->paginate(12);
        return response()->json($payslips);
    }

    public function downloadPayslip($payslipId)
    {
        // Return full payslip data — frontend handles print/PDF via browser print API
        $payslip = Payslip::with(['employee.department', 'payroll'])->findOrFail($payslipId);
        return response()->json(['payslip' => $payslip]);
    }

    public function export($id) {
        return $this->service->exportBankTransfer($id);
    }

    public function components() {
        return response()->json(['components' => PayrollComponent::where('is_active', true)->get()]);
    }

    public function storeComponent(Request $request) {
        $request->validate([
            'name'        => 'required|string|max:100',
            'code'        => 'required|string|max:20|unique:payroll_components',
            'type'        => 'required|in:earning,deduction',
            'calculation' => 'required|in:fixed,percentage',
            'value'       => 'required|numeric|min:0',
        ]);
        $comp = PayrollComponent::create($request->all());
        return response()->json(['component' => $comp], 201);
    }

    public function updatePayslip(Request $request, $payrollId, $payslipId)
    {
        $payroll = Payroll::findOrFail($payrollId);

        if (!in_array($payroll->status, ['draft', 'pending_approval'])) {
            return response()->json(['message' => 'Cannot edit payslip after approval'], 422);
        }

        $payslip = Payslip::where('payroll_id', $payrollId)->findOrFail($payslipId);

        $request->validate([
            'basic_salary'        => 'sometimes|numeric|min:0',
            'housing_allowance'   => 'sometimes|numeric|min:0',
            'transport_allowance' => 'sometimes|numeric|min:0',
            'other_allowances'    => 'sometimes|numeric|min:0',
            'gosi_employee'       => 'sometimes|numeric|min:0',
            'other_deductions'    => 'sometimes|numeric|min:0',
            'absent_days'         => 'sometimes|integer|min:0',
            'notes'               => 'sometimes|string|nullable',
        ]);

        $data = $request->only([
            'basic_salary','housing_allowance','transport_allowance',
            'other_allowances','gosi_employee','other_deductions','absent_days',
        ]);

        // Recalculate totals
        $basic     = $data['basic_salary']        ?? $payslip->basic_salary;
        $housing   = $data['housing_allowance']   ?? $payslip->housing_allowance;
        $transport = $data['transport_allowance'] ?? $payslip->transport_allowance;
        $otherEarn = $data['other_allowances']    ?? $payslip->other_allowances;
        $gosiEmp   = $data['gosi_employee']       ?? $payslip->gosi_employee;
        $otherDed  = $data['other_deductions']    ?? $payslip->other_deductions;

        $totalEarnings   = round($basic + $housing + $transport + $otherEarn, 2);
        $totalDeductions = round($gosiEmp + $otherDed, 2);
        $netSalary       = round(max(0, $totalEarnings - $totalDeductions), 2);

        $payslip->update(array_merge($data, [
            'total_earnings'   => $totalEarnings,
            'gross_salary'     => $totalEarnings,
            'total_deductions' => $totalDeductions,
            'net_salary'       => $netSalary,
        ]));

        // Recalculate payroll totals
        $payroll->update([
            'total_gross'      => $payroll->payslips()->sum('gross_salary'),
            'total_deductions' => $payroll->payslips()->sum('total_deductions'),
            'total_net'        => $payroll->payslips()->sum('net_salary'),
        ]);

        return response()->json([
            'message' => 'Payslip updated successfully',
            'payslip' => $payslip->fresh(),
        ]);
    }

    /**
     * Reopen an approved/paid payroll — resets to pending_approval so payslips can be edited.
     */
    public function reopen(Request $request, $id)
    {
        $payroll = Payroll::findOrFail($id);

        if (!in_array($payroll->status, ['approved', 'paid'])) {
            return response()->json(['message' => 'Only approved or paid payrolls can be reopened'], 422);
        }

        $payroll->update([
            'status'      => 'pending_approval',
            'approved_by' => null,
            'approved_at' => null,
            'notes'       => ($payroll->notes ? $payroll->notes . ' | ' : '') .
                             'Reopened by ' . auth()->user()->name . ' on ' . now()->toDateTimeString(),
        ]);

        return response()->json([
            'message' => 'Payroll reopened successfully. You can now edit payslips and re-approve.',
            'payroll' => $payroll->fresh(),
        ]);
    }

    /**
     * Recalculate all payslips for a payroll (re-run without changing the period).
     */
    public function recalculate($id)
    {
        $payroll = Payroll::with('payslips.employee')->findOrFail($id);

        if (!in_array($payroll->status, ['draft', 'pending_approval'])) {
            return response()->json(['message' => 'Reopen the payroll before recalculating'], 422);
        }

        try {
            // Delete existing payslips and recalculate fresh
            $payroll->payslips()->delete();

            $data = [
                'month'        => $payroll->month,
                'period_start' => $payroll->period_start,
                'period_end'   => $payroll->period_end,
            ];

            $employees   = \App\Models\Employee::where('status', 'active')->get();
            $totalGross  = 0; $totalDeduct = 0; $totalNet = 0;

            $hasNewCols = \Illuminate\Support\Facades\Schema::hasColumn('payslips', 'housing_allowance');

            foreach ($employees as $emp) {
                $slip = $this->service->calculatePayslipPublic($emp, $data, $hasNewCols);
                $payroll->payslips()->create($slip);
                $totalGross  += $slip['gross_salary'];
                $totalDeduct += $slip['total_deductions'];
                $totalNet    += $slip['net_salary'];
            }

            $payroll->update([
                'total_gross'      => round($totalGross, 2),
                'total_deductions' => round($totalDeduct, 2),
                'total_net'        => round($totalNet, 2),
            ]);

            return response()->json([
                'message' => 'Payroll recalculated for ' . $employees->count() . ' employees.',
                'payroll' => $payroll->fresh()->loadCount('payslips'),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Recalculation failed: ' . $e->getMessage()], 500);
        }
    }
}
