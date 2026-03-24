<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\Designation;
use Illuminate\Http\Request;

class DesignationController extends Controller {

    public function index() {
        return response()->json(Designation::with('department')->orderBy('title')->get());
    }

    public function store(Request $request) {
        $request->validate([
            'title'         => 'required|string|max:150',
            'level'         => 'nullable|in:junior,mid,senior,lead,manager,director,executive,management,staff',
            'department_id' => 'nullable|exists:departments,id',
            'min_salary'    => 'nullable|numeric|min:0',
            'max_salary'    => 'nullable|numeric|min:0|gte:min_salary',
            'is_active'     => 'boolean',
        ]);
        $data = $request->all();
        if (empty($data['department_id']))  $data['department_id'] = null;
        if ($data['min_salary'] === '')     $data['min_salary'] = null;
        if ($data['max_salary'] === '')     $data['max_salary'] = null;
        return response()->json(['designation' => Designation::create($data)], 201);
    }

    public function show($id) {
        return response()->json(['designation' => Designation::with('department')->findOrFail($id)]);
    }

    public function update(Request $request, $id) {
        $d = Designation::findOrFail($id);
        $request->validate([
            'title'         => 'sometimes|required|string|max:150',
            'level'         => 'nullable|in:junior,mid,senior,lead,manager,director,executive,management,staff',
            'department_id' => 'nullable|exists:departments,id',
            'min_salary'    => 'nullable|numeric|min:0',
            'max_salary'    => 'nullable|numeric|min:0',
            'is_active'     => 'boolean',
        ]);
        $data = $request->all();
        if (array_key_exists('department_id', $data) && empty($data['department_id'])) $data['department_id'] = null;
        if (isset($data['min_salary']) && $data['min_salary'] === '') $data['min_salary'] = null;
        if (isset($data['max_salary']) && $data['max_salary'] === '') $data['max_salary'] = null;
        $d->update($data);
        return response()->json(['designation' => $d->fresh('department')]);
    }

    public function destroy($id) {
        Designation::findOrFail($id)->delete();
        return response()->json(['message' => 'Position deleted']);
    }
}
