<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller {
    public function index() {
        // Return all departments (active and inactive) so Admin module can manage them.
        // Employees module filters active-only on its own via request params where needed.
        return response()->json(
            Department::with(['manager', 'parent'])->orderBy('name')->get()
        );
    }
    public function store(Request $request) {
        $request->validate(['name'=>'required|string|max:100','code'=>'required|string|max:20|unique:departments']);
        return response()->json(['department' => Department::create($request->all())], 201);
    }
    public function show($id) {
        return response()->json(['department' => Department::with(['manager','employees.designation','children'])->findOrFail($id)]);
    }
    public function update(Request $request, $id) {
        $dept = Department::findOrFail($id);
        $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'code' => "sometimes|required|string|max:20|unique:departments,code,{$id}",
        ]);
        $dept->update($request->all());
        return response()->json(['department' => $dept->fresh(['manager', 'parent'])]);
    }
    public function destroy($id) {
        Department::findOrFail($id)->delete();
        return response()->json(['message' => 'Department deleted']);
    }
    public function headcount($id) {
        $dept = Department::withCount('employees')->findOrFail($id);
        return response()->json(['department_id'=>$id,'budget'=>$dept->headcount_budget,'actual'=>$dept->employees_count,'variance'=>$dept->headcount_budget - $dept->employees_count]);
    }
}
