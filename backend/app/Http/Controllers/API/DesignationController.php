<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\Designation;
use Illuminate\Http\Request;

class DesignationController extends Controller {
    public function index() { return response()->json(Designation::with('department')->get()); }
    public function store(Request $request) {
        $request->validate(['title'=>'required']);
        return response()->json(['designation' => Designation::create($request->all())], 201);
    }
    public function show($id) { return response()->json(['designation' => Designation::findOrFail($id)]); }
    public function update(Request $request, $id) {
        $d = Designation::findOrFail($id); $d->update($request->all());
        return response()->json(['designation' => $d]);
    }
    public function destroy($id) { Designation::findOrFail($id)->delete(); return response()->json(['message' => 'Deleted']); }
}
