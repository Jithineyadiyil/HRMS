<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\LeaveType;
use App\Models\DepartmentExcuseLimit;
use Illuminate\Http\Request;

class ExcuseLimitController extends Controller
{
    /**
     * GET /api/v1/excuse-limits?leave_type_id=X
     * Returns all departments with their current limit config for a given leave type.
     */
    public function index(Request $request)
    {
        $leaveTypeId = $request->leave_type_id;

        $departments = Department::where('is_active', true)
            ->orderBy('name')
            ->get(['id','name','code']);

        // Load existing limits for this leave type
        $limits = DepartmentExcuseLimit::where('leave_type_id', $leaveTypeId)
            ->get()
            ->keyBy('department_id');

        $result = $departments->map(function ($dept) use ($limits, $leaveTypeId) {
            $limit = $limits->get($dept->id);
            return [
                'department_id'       => $dept->id,
                'department_name'     => $dept->name,
                'department_code'     => $dept->code,
                'leave_type_id'       => (int) $leaveTypeId,
                'limit_id'            => $limit?->id,
                'is_limited'          => $limit ? $limit->is_limited : true,   // default: limited
                'monthly_hours_limit' => $limit ? $limit->monthly_hours_limit : 12.0, // default: 12h
            ];
        });

        return response()->json(['limits' => $result]);
    }

    /**
     * POST /api/v1/excuse-limits/bulk
     * Upsert limits for all departments at once.
     * Body: { leave_type_id, limits: [{ department_id, is_limited, monthly_hours_limit }] }
     */
    public function bulkUpsert(Request $request)
    {
        $request->validate([
            'leave_type_id'                    => 'required|exists:leave_types,id',
            'limits'                           => 'required|array',
            'limits.*.department_id'           => 'required|exists:departments,id',
            'limits.*.is_limited'              => 'required|boolean',
            'limits.*.monthly_hours_limit'     => 'nullable|numeric|min:0.5|max:200',
        ]);

        $leaveTypeId = $request->leave_type_id;

        foreach ($request->limits as $row) {
            DepartmentExcuseLimit::updateOrCreate(
                [
                    'department_id' => $row['department_id'],
                    'leave_type_id' => $leaveTypeId,
                ],
                [
                    'is_limited'          => $row['is_limited'],
                    'monthly_hours_limit' => $row['is_limited'] ? ($row['monthly_hours_limit'] ?? 12.0) : null,
                ]
            );
        }

        return response()->json(['message' => 'Department limits saved successfully.']);
    }

    /**
     * PUT /api/v1/excuse-limits/{id}
     * Update a single department limit.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'is_limited'          => 'required|boolean',
            'monthly_hours_limit' => 'nullable|numeric|min:0.5|max:200',
        ]);

        $limit = DepartmentExcuseLimit::findOrFail($id);
        $limit->update([
            'is_limited'          => $request->is_limited,
            'monthly_hours_limit' => $request->is_limited ? ($request->monthly_hours_limit ?? 12.0) : null,
        ]);

        return response()->json(['message' => 'Limit updated', 'limit' => $limit]);
    }
}
