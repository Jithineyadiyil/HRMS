<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use App\Services\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class EmployeeController extends Controller
{
    public function __construct(protected EmployeeService $service) {}

    // ── Role helper ───────────────────────────────────────────────────────

    private function userRoles(): array
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', auth()->id())
            ->where('model_has_roles.model_type', get_class(auth()->user()))
            ->pluck('roles.name')
            ->toArray();
    }

    private function hasAnyRoleDB(array $roles): bool
    {
        return (bool) array_intersect($this->userRoles(), $roles);
    }

    // ── Index ─────────────────────────────────────────────────────────────

    /**
     * Return a paginated employee list with a 'meta' envelope.
     *
     * FIX: previously returned Laravel's raw paginator which puts pagination
     * keys at the top level. Now returns { data, meta } structure matching
     * the test contract and API Resources standard.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Employee::with(['department', 'designation', 'manager', 'user'])
            ->when($request->department_id,   fn ($q) => $q->where('department_id', $request->department_id))
            ->when($request->status,          fn ($q) => $q->where('status', $request->status))
            ->when($request->employment_type, fn ($q) => $q->where('employment_type', $request->employment_type))
            ->when($request->search, fn ($q) => $q->where(function ($sub) use ($request) {
                $sub->where('first_name',    'like', "%{$request->search}%")
                    ->orWhere('last_name',   'like', "%{$request->search}%")
                    ->orWhere('email',       'like', "%{$request->search}%")
                    ->orWhere('employee_code', 'like', "%{$request->search}%");
            }))
            ->orderBy($request->sort_by ?? 'created_at', $request->sort_dir ?? 'desc');

        $paginator = $query->paginate((int) ($request->per_page ?? 15));

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
        ]);
    }

    // ── Stats ─────────────────────────────────────────────────────────────

    public function stats(): JsonResponse
    {
        $safe  = fn (callable $fn) => rescue($fn, 0, false);
        $month = now()->month;
        $year  = now()->year;

        return response()->json([
            'total'          => $safe(fn () => DB::table('employees')->whereNull('deleted_at')->count()),
            'active'         => $safe(fn () => DB::table('employees')->whereNull('deleted_at')->where('status', 'active')->count()),
            'probation'      => $safe(fn () => DB::table('employees')->whereNull('deleted_at')->where('status', 'probation')->count()),
            'on_leave'       => $safe(fn () => DB::table('employees')->whereNull('deleted_at')->where('status', 'on_leave')->count()),
            'terminated'     => $safe(fn () => DB::table('employees')->whereNull('deleted_at')->where('status', 'terminated')->count()),
            'new_this_month' => $safe(fn () => DB::table('employees')->whereNull('deleted_at')
                ->whereMonth('hire_date', $month)->whereYear('hire_date', $year)->count()),
        ]);
    }

    // ── Store ─────────────────────────────────────────────────────────────

    /**
     * FIX: Added role guard (returns 403 for non-HR users).
     * FIX: Temp password now includes a random component so it's unique per call.
     * FIX: Messages include trailing period to match test contract.
     */
    public function store(Request $request): JsonResponse
    {
        // Role guard — only HR roles can create employees
        if (!$this->hasAnyRoleDB(['super_admin', 'hr_manager', 'hr_staff'])) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'first_name'         => 'required|string|max:100',
            'last_name'          => 'required|string|max:100',
            'email'              => 'required|email|unique:employees,email',
            'hire_date'          => 'required|date',
            'department_id'      => 'nullable|exists:departments,id',
            'designation_id'     => 'nullable|exists:designations,id',
            'manager_id'         => 'nullable|exists:employees,id',
            'employment_type'    => 'required|in:full_time,part_time,contract,intern',
            'status'             => 'sometimes|in:active,inactive,terminated,on_leave,probation',
            'salary'             => 'required|numeric|min:0',
            'confirmation_date'  => 'nullable|date',
            'termination_date'   => 'nullable|date',
            'probation_period'   => 'nullable|integer|min:0',
            'years_of_experience'=> 'nullable|integer|min:0',
            'dob'                => 'nullable|date',
        ]);

        return DB::transaction(function () use ($request): JsonResponse {
            // Unique temp password: date + random suffix
            $tempPassword = 'Hrms@' . now()->format('dmy') . strtoupper(substr(md5(uniqid('', true)), 0, 6)) . '!';

            $user = User::create([
                'name'     => $request->first_name . ' ' . $request->last_name,
                'email'    => $request->email,
                'password' => Hash::make($tempPassword),
            ]);
            $user->assignRole('employee');

            $code = $this->service->generateCode();

            $employeeData = $request->only([
                'first_name', 'last_name', 'email', 'phone', 'hire_date',
                'department_id', 'designation_id', 'manager_id', 'employment_type',
                'status', 'salary', 'confirmation_date', 'termination_date',
                'probation_period', 'years_of_experience', 'dob',
                'nationality', 'gender', 'marital_status',
                'housing_allowance', 'transport_allowance', 'mobile_allowance',
                'food_allowance', 'other_allowances',
            ]);

            $employee = Employee::create(array_merge($employeeData, [
                'user_id'       => $user->id,
                'employee_code' => $code,
            ]));

            $this->service->createDefaultLeaveAllocations($employee);
            $this->service->createOnboardingTasks($employee);

            return response()->json([
                'message'       => 'Employee created successfully.',
                'employee'      => $employee->load(['department', 'designation']),
                'temp_password' => $tempPassword,
            ], 201);
        });
    }

    // ── Show ──────────────────────────────────────────────────────────────

    public function show(int $id): JsonResponse
    {
        try {
            $employee = Employee::with([
                'department', 'designation', 'manager',
                'leaveAllocations.leaveType',
                'onboardingTasks',
            ])->findOrFail($id);

            return response()->json(['employee' => $employee]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['message' => 'Employee not found'], 404);
        }
    }

    // ── Update ────────────────────────────────────────────────────────────

    public function update(Request $request, int $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);

        $request->validate([
            'email'          => 'sometimes|email|unique:employees,email,' . $id,
            'first_name'     => 'sometimes|string|max:100',
            'last_name'      => 'sometimes|string|max:100',
            'salary'         => 'sometimes|numeric|min:0',
            'status'         => 'sometimes|in:active,inactive,terminated,on_leave,probation',
            'employment_type'=> 'sometimes|in:full_time,part_time,contract,intern',
            'department_id'  => 'nullable|exists:departments,id',
            'designation_id' => 'nullable|exists:designations,id',
            'manager_id'     => 'nullable|exists:employees,id',
        ]);

        $allowed = [
            'first_name', 'last_name', 'email', 'phone', 'hire_date',
            'department_id', 'designation_id', 'manager_id', 'employment_type',
            'status', 'salary', 'confirmation_date', 'termination_date',
            'probation_period', 'years_of_experience', 'dob',
            'nationality', 'gender', 'marital_status',
            'housing_allowance', 'transport_allowance', 'mobile_allowance',
            'food_allowance', 'other_allowances', 'bank_name', 'bank_account',
            'national_id', 'iqama_number', 'iqama_expiry',
        ];

        $employee->update($request->only($allowed));

        if ($employee->user && ($request->has('first_name') || $request->has('last_name') || $request->has('email'))) {
            $employee->user->update([
                'name'  => $employee->full_name,
                'email' => $employee->email,
            ]);
        }

        return response()->json([
            'message'  => 'Employee updated successfully.',
            'employee' => $employee->load(['department', 'designation']),
        ]);
    }

    // ── Destroy ───────────────────────────────────────────────────────────

    /**
     * FIX: message now ends with period to match test contract.
     */
    public function destroy(int $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $employee->update(['status' => 'terminated', 'termination_date' => now()]);
        $employee->delete();
        $employee->user?->tokens()->delete();

        return response()->json(['message' => 'Employee terminated and archived.']);
    }

    // ── Avatar / Documents / Export ───────────────────────────────────────

    public function uploadAvatar(Request $request, int $id): JsonResponse
    {
        $request->validate(['avatar' => 'required|image|max:2048']);
        $employee = Employee::findOrFail($id);
        if ($employee->avatar) Storage::delete($employee->avatar);
        $path = $request->file('avatar')->store('avatars', 'public');
        $employee->update(['avatar' => $path]);
        return response()->json(['avatar_url' => asset('storage/' . $path)]);
    }

    public function uploadDocument(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'title'       => 'required|string|max:100',
            'type'        => 'required|in:contract,id,certificate,other',
            'file'        => 'required|file|max:10240',
            'expiry_date' => 'nullable|date',
        ]);
        $employee = Employee::findOrFail($id);
        $path     = $request->file('file')->store("employees/{$id}/documents");
        $doc      = $employee->documents()->create([
            'title'       => $request->title,
            'type'        => $request->type,
            'file_path'   => $path,
            'file_name'   => $request->file('file')->getClientOriginalName(),
            'mime_type'   => $request->file('file')->getMimeType(),
            'file_size'   => $request->file('file')->getSize(),
            'expiry_date' => $request->expiry_date,
        ]);
        return response()->json(['document' => $doc], 201);
    }

    public function listDocuments(int $id): JsonResponse
    {
        return response()->json(['documents' => Employee::findOrFail($id)->documents]);
    }

    public function deleteDocument(int $id, int $docId): JsonResponse
    {
        $doc = Employee::findOrFail($id)->documents()->findOrFail($docId);
        Storage::delete($doc->file_path);
        $doc->delete();
        return response()->json(['message' => 'Document deleted']);
    }

    public function downloadDocument(int $id, int $docId)
    {
        $doc = Employee::findOrFail($id)->documents()->findOrFail($docId);
        if (!Storage::exists($doc->file_path)) {
            return response()->json(['message' => 'File not found'], 404);
        }
        return Storage::download($doc->file_path, $doc->file_name);
    }

    public function export(Request $request): mixed
    {
        return $this->service->export($request->all());
    }
}
