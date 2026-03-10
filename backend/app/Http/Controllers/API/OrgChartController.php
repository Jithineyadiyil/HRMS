<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\Request;

class OrgChartController extends Controller
{
    // ── Full hierarchy tree ───────────────────────────────────────────────
    public function index()
    {
        $roots = Department::with([
            'allChildren.manager.designation',
            'allChildren.manager.user',
            'allChildren.employees.designation',
            'manager.designation',
            'manager.user',
        ])
        ->withCount('employees')
        ->whereNull('parent_id')
        ->where('is_active', true)
        ->get()
        ->map(fn($d) => $this->formatDept($d));

        return response()->json(['chart' => $roots]);
    }

    // ── Single department detail (with full employee list) ────────────────
    public function department($id)
    {
        $dept = Department::with([
            'manager.designation','manager.user','manager.directReports.designation',
            'parent',
            'children' => fn($q) => $q->withCount('employees'),
            'employees' => fn($q) => $q->with('designation','manager')->where('status','active')->orderBy('first_name'),
        ])
        ->withCount('employees')
        ->findOrFail($id);

        return response()->json(['department' => $dept]);
    }

    // ── Company-level stats ───────────────────────────────────────────────
    public function stats()
    {
        return response()->json([
            'total_employees'   => Employee::where('status','active')->count(),
            'total_departments' => Department::where('is_active',true)->count(),
            'departments'       => Department::withCount('employees')
                ->where('is_active',true)
                ->orderByDesc('employees_count')
                ->get()
                ->map(fn($d) => [
                    'id'             => $d->id,
                    'name'           => $d->name,
                    'code'           => $d->code,
                    'employees_count'=> $d->employees_count,
                    'headcount_budget'=> $d->headcount_budget,
                ]),
        ]);
    }

    // ── Search employees across whole org ─────────────────────────────────
    public function search(Request $request)
    {
        $q = $request->q;
        if (!$q) return response()->json(['results' => []]);

        $emps = Employee::with('department','designation')
            ->where('status','active')
            ->where(fn($query) =>
                $query->where('first_name','like',"%$q%")
                      ->orWhere('last_name','like',"%$q%")
                      ->orWhere('employee_code','like',"%$q%")
                      ->orWhereHas('designation', fn($dq) => $dq->where('title','like',"%$q%"))
            )
            ->limit(20)
            ->get()
            ->map(fn($e) => [
                'id'         => $e->id,
                'name'       => trim("{$e->first_name} {$e->last_name}"),
                'code'       => $e->employee_code,
                'avatar_url' => $e->avatar_url,
                'designation'=> $e->designation?->title,
                'department' => $e->department?->name,
                'department_id' => $e->department_id,
            ]);

        return response()->json(['results' => $emps]);
    }

    // ── Department CRUD ───────────────────────────────────────────────────
    public function storeDepartment(Request $request)
    {
        $request->validate([
            'name'      => 'required|string|max:100',
            'code'      => 'required|string|max:20|unique:departments',
            'parent_id' => 'nullable|exists:departments,id',
        ]);
        $dept = Department::create($request->only('name','code','description','parent_id','manager_id','headcount_budget','is_active'));
        return response()->json(['department' => $dept->load('manager','parent')], 201);
    }

    public function updateDepartment(Request $request, $id)
    {
        $dept = Department::findOrFail($id);
        $dept->update($request->only('name','code','description','parent_id','manager_id','headcount_budget','is_active'));
        return response()->json(['department' => $dept->fresh('manager','parent','children')]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────
    private function formatDept($dept): array
    {
        return [
            'id'              => $dept->id,
            'name'            => $dept->name,
            'code'            => $dept->code,
            'description'     => $dept->description,
            'employees_count' => $dept->employees_count,
            'headcount_budget'=> $dept->headcount_budget,
            'manager'         => $dept->manager ? [
                'id'         => $dept->manager->id,
                'name'       => trim("{$dept->manager->first_name} {$dept->manager->last_name}"),
                'avatar_url' => $dept->manager->avatar_url,
                'designation'=> $dept->manager->designation?->title,
            ] : null,
            'children' => ($dept->allChildren ?? collect())->map(fn($c) => $this->formatDept($c))->values(),
        ];
    }
}
