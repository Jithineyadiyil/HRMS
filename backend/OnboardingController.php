<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\OnboardingTask;
use Illuminate\Http\Request;

class OnboardingController extends Controller {
    public function tasks($empId) {
        return response()->json(['tasks' => OnboardingTask::where('employee_id',$empId)->orderBy('sort_order')->get()]);
    }
    public function createTask(Request $request, $empId) {
        $request->validate(['title'=>'required','category'=>'required']);
        return response()->json(['task' => OnboardingTask::create(array_merge($request->all(),['employee_id'=>$empId]))], 201);
    }
    public function updateTask(Request $request, $taskId) {
        $task = OnboardingTask::findOrFail($taskId); $task->update($request->all());
        return response()->json(['task' => $task]);
    }
    public function completeTask($taskId) {
        OnboardingTask::findOrFail($taskId)->update(['status'=>'completed','completed_date'=>now()]);
        return response()->json(['message' => 'Task completed']);
    }
    public function deleteTask($taskId) {
        OnboardingTask::findOrFail($taskId)->delete();
        return response()->json(['message' => 'Task deleted']);
    }
}
