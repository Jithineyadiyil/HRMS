<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\User;
use App\Repositories\Contracts\EmployeeRepositoryInterface;
use App\Services\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Handles HTTP actions for the Employee resource.
 *
 * Business logic is delegated to {@see EmployeeService}.
 * Data access is delegated to {@see EmployeeRepositoryInterface}.
 * All responses are transformed through {@see EmployeeResource}.
 */
class EmployeeController extends Controller
{
    /**
     * @param  EmployeeRepositoryInterface $repository
     * @param  EmployeeService             $service
     */
    public function __construct(
        private readonly EmployeeRepositoryInterface $repository,
        private readonly EmployeeService $service,
    ) {}

    /**
     * Return a paginated, filtered list of employees.
     *
     * @param  Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $paginator = $this->repository->paginate($request->all());

        return EmployeeResource::collection($paginator);
    }

    /**
     * Create a new employee, user account, leave allocations, and onboarding tasks.
     *
     * The temporary password is generated randomly and returned once in the
     * response body so that HR can share it securely. It is never hardcoded.
     *
     * @param  StoreEmployeeRequest $request
     * @return JsonResponse
     */
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request): JsonResponse {
            $validated = $request->validated();

            // SECURITY: generate a cryptographically random temporary password
            $tempPassword = Str::password(12, true, true, false);

            $user = User::create([
                'name'     => trim($validated['first_name'] . ' ' . $validated['last_name']),
                'email'    => $validated['email'],
                'password' => Hash::make($tempPassword),
            ]);
            $user->assignRole('employee');

            $employee = $this->repository->create(array_merge(
                $validated,
                [
                    'user_id'       => $user->id,
                    'employee_code' => $this->repository->nextEmployeeCode(),
                ]
            ));

            $this->service->createDefaultLeaveAllocations($employee);
            $this->service->createOnboardingTasks($employee);

            return response()->json([
                'message'       => 'Employee created successfully.',
                'employee'      => new EmployeeResource($employee->load(['department', 'designation'])),
                // NOTE: temp_password is shown once; HR must communicate it to the employee
                // and the employee must change it on first login.
                'temp_password' => $tempPassword,
            ], 201);
        });
    }

    /**
     * Return a single employee with all detail relations loaded.
     *
     * @param  int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $employee = $this->repository->findById($id);

        return response()->json([
            'employee' => new EmployeeResource($employee),
        ]);
    }

    /**
     * Update an existing employee record.
     *
     * Immutable fields (employee_code, user_id) are stripped from the
     * validated payload before persistence.
     *
     * @param  UpdateEmployeeRequest $request
     * @param  int                   $id
     * @return JsonResponse
     */
    public function update(UpdateEmployeeRequest $request, int $id): JsonResponse
    {
        $employee = $this->repository->findById($id);

        $data = collect($request->validated())
            ->except(['employee_code', 'user_id'])
            ->toArray();

        $updated = $this->repository->update($employee, $data);

        // Keep User account email/name in sync
        if (isset($data['first_name']) || isset($data['last_name']) || isset($data['email'])) {
            $updated->user?->update([
                'name'  => $updated->full_name,
                'email' => $updated->email,
            ]);
        }

        return response()->json([
            'message'  => 'Employee updated successfully.',
            'employee' => new EmployeeResource($updated),
        ]);
    }

    /**
     * Terminate and soft-delete an employee.
     *
     * @param  int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $employee = $this->repository->findById($id);
        $this->repository->terminate($employee);

        return response()->json(['message' => 'Employee terminated and archived.']);
    }

    /**
     * Upload or replace an employee's avatar image.
     *
     * @param  Request $request
     * @param  int     $id
     * @return JsonResponse
     */
    public function uploadAvatar(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
        ]);

        $employee = $this->repository->findById($id);

        if ($employee->avatar) {
            Storage::disk('public')->delete($employee->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $this->repository->update($employee, ['avatar' => $path]);

        return response()->json([
            'avatar_url' => asset('storage/' . $path),
        ]);
    }

    /**
     * Upload a document to an employee's record.
     *
     * @param  Request $request
     * @param  int     $id
     * @return JsonResponse
     */
    public function uploadDocument(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'title'       => ['required', 'string', 'max:100'],
            'type'        => ['required', 'in:contract,id,certificate,other'],
            'file'        => ['required', 'file', 'max:10240'],
            'expiry_date' => ['nullable', 'date', 'after:today'],
        ]);

        $employee = $this->repository->findById($id);
        $file     = $request->file('file');
        $path     = $file->store("employees/{$id}/documents");

        $doc = $employee->documents()->create([
            'title'       => $request->title,
            'type'        => $request->type,
            'file_path'   => $path,
            'file_name'   => $file->getClientOriginalName(),
            'mime_type'   => $file->getMimeType(),
            'file_size'   => $file->getSize(),
            'expiry_date' => $request->expiry_date,
        ]);

        return response()->json(['document' => $doc], 201);
    }

    /**
     * List all documents for an employee.
     *
     * @param  int $id
     * @return JsonResponse
     */
    public function listDocuments(int $id): JsonResponse
    {
        $employee = $this->repository->findById($id);

        return response()->json(['documents' => $employee->documents]);
    }

    /**
     * Delete an employee document and remove the file from storage.
     *
     * @param  int $id
     * @param  int $docId
     * @return JsonResponse
     */
    public function deleteDocument(int $id, int $docId): JsonResponse
    {
        $doc = $this->repository->findById($id)->documents()->findOrFail($docId);
        Storage::delete($doc->file_path);
        $doc->delete();

        return response()->json(['message' => 'Document deleted.']);
    }

    /**
     * Stream a document file as a download response.
     *
     * @param  int $id
     * @param  int $docId
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|JsonResponse
     */
    public function downloadDocument(int $id, int $docId): mixed
    {
        $doc = $this->repository->findById($id)->documents()->findOrFail($docId);

        if (! Storage::exists($doc->file_path)) {
            return response()->json(['message' => 'File not found on storage.'], 404);
        }

        return Storage::download($doc->file_path, $doc->file_name);
    }

    /**
     * Export the employee list as a CSV download.
     *
     * @param  Request $request
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function export(Request $request): mixed
    {
        return $this->service->export($request->all());
    }
}
