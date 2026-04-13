<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\OnboardingTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manages employee onboarding tasks.
 *
 * All write operations validate and whitelist input fields to prevent
 * mass-assignment vulnerabilities.
 */
class OnboardingController extends Controller
{
    /**
     * Return all onboarding tasks for a given employee, ordered by sort_order.
     *
     * @param  int          $empId
     * @return JsonResponse
     */
    public function tasks(int $empId): JsonResponse
    {
        return response()->json([
            'tasks' => OnboardingTask::where('employee_id', $empId)
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    /**
     * Create a new onboarding task for an employee.
     *
     * @param  Request      $request
     * @param  int          $empId
     * @return JsonResponse           201 with created task
     */
    public function createTask(Request $request, int $empId): JsonResponse
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:200',
            'category'    => 'required|string|max:100',
            'description' => 'nullable|string|max:1000',
            'due_date'    => 'nullable|date',
            'assigned_to' => 'nullable|exists:users,id',
            'sort_order'  => 'nullable|integer|min:0',
            'status'      => 'nullable|in:pending,in_progress,completed,skipped',
        ]);

        $task = OnboardingTask::create(array_merge($validated, [
            'employee_id' => $empId,
            'status'      => $validated['status'] ?? 'pending',
        ]));

        return response()->json(['task' => $task], 201);
    }

    /**
     * Update an existing onboarding task.
     *
     * @param  Request      $request
     * @param  int          $taskId
     * @return JsonResponse
     */
    public function updateTask(Request $request, int $taskId): JsonResponse
    {
        $task = OnboardingTask::findOrFail($taskId);

        $validated = $request->validate([
            'title'       => 'sometimes|string|max:200',
            'category'    => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:1000',
            'due_date'    => 'nullable|date',
            'assigned_to' => 'nullable|exists:users,id',
            'sort_order'  => 'nullable|integer|min:0',
            'status'      => 'nullable|in:pending,in_progress,completed,skipped',
            'notes'       => 'nullable|string|max:500',
        ]);

        $task->update($validated);

        return response()->json(['task' => $task]);
    }

    /**
     * Mark a task as completed and record the completion timestamp.
     *
     * @param  int          $taskId
     * @return JsonResponse
     */
    public function completeTask(int $taskId): JsonResponse
    {
        OnboardingTask::findOrFail($taskId)->update([
            'status'         => 'completed',
            'completed_date' => now(),
        ]);

        return response()->json(['message' => 'Task completed']);
    }

    /**
     * Delete an onboarding task.
     *
     * @param  int          $taskId
     * @return JsonResponse
     */
    public function deleteTask(int $taskId): JsonResponse
    {
        OnboardingTask::findOrFail($taskId)->delete();

        return response()->json(['message' => 'Task deleted']);
    }
}
