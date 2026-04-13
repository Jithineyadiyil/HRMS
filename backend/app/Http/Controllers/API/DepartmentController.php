<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manages department CRUD and headcount reporting.
 *
 * All write operations use explicit `only()` instead of `$request->all()`
 * to prevent mass-assignment attacks.
 */
class DepartmentController extends Controller
{
    /**
     * Return all departments (active and inactive) for admin management.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        return response()->json(
            Department::with(['manager', 'parent'])->orderBy('name')->get()
        );
    }

    /**
     * Create a new department.
     *
     * @param  Request      $request
     * @return JsonResponse           201 with the created department
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:100',
            'code'             => 'required|string|max:20|unique:departments',
            'description'      => 'nullable|string|max:500',
            'parent_id'        => 'nullable|exists:departments,id',
            'manager_id'       => 'nullable|exists:employees,id',
            'headcount_budget' => 'nullable|integer|min:0',
            'is_active'        => 'boolean',
        ]);

        return response()->json(
            ['department' => Department::create($validated)],
            201
        );
    }

    /**
     * Return a single department with manager, employees, and child departments.
     *
     * @param  int          $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        return response()->json([
            'department' => Department::with([
                'manager', 'employees.designation', 'children',
            ])->findOrFail($id),
        ]);
    }

    /**
     * Update an existing department.
     *
     * @param  Request      $request
     * @param  int          $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $dept = Department::findOrFail($id);

        $validated = $request->validate([
            'name'             => 'sometimes|required|string|max:100',
            'code'             => "sometimes|required|string|max:20|unique:departments,code,{$id}",
            'description'      => 'nullable|string|max:500',
            'parent_id'        => 'nullable|exists:departments,id',
            'manager_id'       => 'nullable|exists:employees,id',
            'headcount_budget' => 'nullable|integer|min:0',
            'is_active'        => 'boolean',
        ]);

        $dept->update($validated);

        return response()->json(['department' => $dept->fresh(['manager', 'parent'])]);
    }

    /**
     * Soft-delete a department.
     *
     * @param  int          $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        Department::findOrFail($id)->delete();

        return response()->json(['message' => 'Department deleted']);
    }

    /**
     * Return headcount budget vs actual employee count for a department.
     *
     * @param  int          $id
     * @return JsonResponse
     */
    public function headcount(int $id): JsonResponse
    {
        $dept = Department::withCount('employees')->findOrFail($id);

        return response()->json([
            'department_id' => $id,
            'budget'        => $dept->headcount_budget,
            'actual'        => $dept->employees_count,
            'variance'      => ($dept->headcount_budget ?? 0) - $dept->employees_count,
        ]);
    }
}
