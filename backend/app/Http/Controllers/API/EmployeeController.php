<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use App\Services\EmployeeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class EmployeeController extends Controller {

    protected $service;

    public function __construct(EmployeeService $service) {
        $this->service = $service;
    }

    public function index(Request $request) {
        $query = Employee::with(['department', 'designation', 'manager', 'user'])
            ->when($request->department_id, fn($q) => $q->where('department_id', $request->department_id))
            ->when($request->status,        fn($q) => $q->where('status', $request->status))
            ->when($request->employment_type, fn($q) => $q->where('employment_type', $request->employment_type))
            ->when($request->search, fn($q) => $q->where(function($sub) use ($request) {
                $sub->where('first_name', 'like', "%{$request->search}%")
                    ->orWhere('last_name', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%")
                    ->orWhere('employee_code', 'like', "%{$request->search}%");
            }))
            ->orderBy($request->sort_by ?? 'created_at', $request->sort_dir ?? 'desc');

        return response()->json($query->paginate($request->per_page ?? 15));
    }

    public function store(Request $request) {
        $request->validate([
            'first_name'      => 'required|string|max:100',
            'last_name'       => 'required|string|max:100',
            'email'           => 'required|email|unique:employees,email',
            'hire_date'       => 'required|date',
            'department_id'   => 'nullable|exists:departments,id',
            'designation_id'  => 'nullable|exists:designations,id',
            'manager_id'      => 'nullable|exists:employees,id',
            'employment_type' => 'required|in:full_time,part_time,contract,intern',
            'status'          => 'sometimes|in:active,inactive,terminated,on_leave,probation',
            'salary'          => 'required|numeric|min:0',
            'confirmation_date'  => 'nullable|date',
            'termination_date'   => 'nullable|date',
            'probation_period'   => 'nullable|integer|min:0',
            'years_of_experience'=> 'nullable|integer|min:0',
            'dob'                => 'nullable|date',
        ]);

        return DB::transaction(function () use ($request) {
            // Create user account
            $user = User::create([
                'name'     => $request->first_name . ' ' . $request->last_name,
                'email'    => $request->email,
                'password' => Hash::make('Password@123'), // temp password
            ]);
            $user->assignRole('employee');

            // Generate employee code
            $code = $this->service->generateCode();

            $employee = Employee::create(array_merge(
                $request->except(['password']),
                ['user_id' => $user->id, 'employee_code' => $code]
            ));

            // Create default leave allocations
            $this->service->createDefaultLeaveAllocations($employee);

            // Create onboarding tasks
            $this->service->createOnboardingTasks($employee);

            return response()->json([
                'message'  => 'Employee created successfully',
                'employee' => $employee->load(['department', 'designation']),
                'temp_password' => 'Password@123',
            ], 201);
        });
    }

    public function show($id) {
        try {
            $employee = Employee::with([
                'department', 'designation', 'manager',
                'leaveAllocations.leaveType',
            ])->findOrFail($id);

            return response()->json(['employee' => $employee]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Employee not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id) {
        $employee = Employee::findOrFail($id);

        $request->validate([
            'email' => 'sometimes|email|unique:employees,email,' . $id,
        ]);

        $employee->update($request->except(['employee_code', 'user_id']));

        // Sync user name/email
        if ($request->has('first_name') || $request->has('last_name') || $request->has('email')) {
            $employee->user->update([
                'name'  => $employee->full_name,
                'email' => $employee->email,
            ]);
        }

        return response()->json([
            'message'  => 'Employee updated successfully',
            'employee' => $employee->load(['department', 'designation']),
        ]);
    }

    public function destroy($id) {
        $employee = Employee::findOrFail($id);
        $employee->update(['status' => 'terminated', 'termination_date' => now()]);
        $employee->delete();
        $employee->user->tokens()->delete();
        return response()->json(['message' => 'Employee terminated and archived']);
    }

    public function uploadAvatar(Request $request, $id) {
        $request->validate(['avatar' => 'required|image|max:2048']);
        $employee = Employee::findOrFail($id);
        if ($employee->avatar) Storage::delete($employee->avatar);
        $path = $request->file('avatar')->store('avatars', 'public');
        $employee->update(['avatar' => $path]);
        return response()->json(['avatar_url' => asset('storage/' . $path)]);
    }

    public function uploadDocument(Request $request, $id) {
        $request->validate([
            'title' => 'required|string|max:100',
            'type'  => 'required|in:contract,id,certificate,other',
            'file'  => 'required|file|max:10240',
        ]);
        $employee = Employee::findOrFail($id);
        $path = $request->file('file')->store("employees/{$id}/documents");
        $doc  = $employee->documents()->create([
            'title'     => $request->title,
            'type'      => $request->type,
            'file_path' => $path,
            'file_name' => $request->file('file')->getClientOriginalName(),
            'mime_type' => $request->file('file')->getMimeType(),
            'file_size' => $request->file('file')->getSize(),
            'expiry_date' => $request->expiry_date,
        ]);
        return response()->json(['document' => $doc], 201);
    }

    public function listDocuments($id) {
        $employee = Employee::findOrFail($id);
        return response()->json(['documents' => $employee->documents]);
    }

    public function deleteDocument($id, $docId) {
        $doc = Employee::findOrFail($id)->documents()->findOrFail($docId);
        Storage::delete($doc->file_path);
        $doc->delete();
        return response()->json(['message' => 'Document deleted']);
    }

    public function downloadDocument($id, $docId) {
        $doc = Employee::findOrFail($id)->documents()->findOrFail($docId);
        if (!Storage::exists($doc->file_path)) {
            return response()->json(['message' => 'File not found'], 404);
        }
        return Storage::download($doc->file_path, $doc->file_name);
    }

    public function export(Request $request) {
        return $this->service->export($request->all());
    }
}
