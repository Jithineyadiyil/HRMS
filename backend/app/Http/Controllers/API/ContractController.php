<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContractResource;
use App\Models\Contract;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Manages employee contract CRUD operations.
 *
 * Endpoints:
 *   GET    /api/v1/contracts           — paginated list with filters
 *   POST   /api/v1/contracts           — create new contract
 *   GET    /api/v1/contracts/{id}      — single contract
 *   PUT    /api/v1/contracts/{id}      — update contract
 *   DELETE /api/v1/contracts/{id}      — soft-delete
 *   POST   /api/v1/contracts/{id}/approve  — approve a draft
 *   GET    /api/v1/contracts/stats     — summary counts
 *   GET    /api/v1/employees/{id}/contracts — all contracts for one employee
 */
class ContractController extends Controller
{
    // ── List ──────────────────────────────────────────────────────────────

    /**
     * Return a paginated, filterable list of contracts.
     *
     * @param  Request      $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = Contract::with(['employee.department', 'department', 'createdBy'])
            ->when($request->status,        fn ($q) => $q->where('status', $request->status))
            ->when($request->type,          fn ($q) => $q->where('type', $request->type))
            ->when($request->employee_id,   fn ($q) => $q->where('employee_id', $request->employee_id))
            ->when($request->department_id, fn ($q) => $q->where('department_id', $request->department_id))
            ->when($request->expiring_soon, fn ($q) => $q->expiringSoon(30))
            ->when($request->search, fn ($q) => $q->where(function ($sub) use ($request) {
                $sub->where('reference', 'like', "%{$request->search}%")
                    ->orWhere('position', 'like', "%{$request->search}%")
                    ->orWhereHas('employee', fn ($e) => $e->where(
                        DB::raw("CONCAT(first_name,' ',last_name)"),
                        'like', "%{$request->search}%"
                    ));
            }))
            ->orderBy($request->sort_by ?? 'created_at', $request->sort_dir ?? 'desc');

        return response()->json(
            ContractResource::collection($query->paginate((int) ($request->per_page ?? 15)))
                ->response()->getData(true)
        );
    }

    // ── Stats ─────────────────────────────────────────────────────────────

    /**
     * Return contract summary counts for the stat strip.
     *
     * @return JsonResponse
     */
    public function stats(): JsonResponse
    {
        $safe = fn (callable $fn) => rescue($fn, 0, false);

        return response()->json([
            'total'         => $safe(fn () => Contract::count()),
            'active'        => $safe(fn () => Contract::where('status', 'active')->count()),
            'draft'         => $safe(fn () => Contract::where('status', 'draft')->count()),
            'expiring_soon' => $safe(fn () => Contract::expiringSoon(30)->count()),
            'expired'       => $safe(fn () => Contract::where('status', 'expired')->count()),
            'terminated'    => $safe(fn () => Contract::where('status', 'terminated')->count()),
        ]);
    }

    // ── Create ────────────────────────────────────────────────────────────

    /**
     * Store a new contract.
     *
     * @param  Request      $request
     * @return JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id'   => 'required|exists:employees,id',
            'type'          => 'required|in:full_time,part_time,contract,intern,probation,fixed_term,unlimited',
            'status'        => 'sometimes|in:draft,active',
            'start_date'    => 'required|date',
            'end_date'      => 'nullable|date|after:start_date',
            'salary'        => 'nullable|numeric|min:0',
            'currency'      => 'sometimes|string|size:3',
            'position'      => 'nullable|string|max:150',
            'department_id' => 'nullable|exists:departments,id',
            'terms'         => 'nullable|string',
        ]);

        $contract = Contract::create([
            ...$request->only([
                'employee_id', 'type', 'status', 'start_date', 'end_date',
                'salary', 'currency', 'position', 'department_id', 'terms',
            ]),
            'reference'  => Contract::generateReference(),
            'created_by' => auth()->id(),
            'status'     => $request->status ?? 'draft',
        ]);

        return response()->json([
            'message'  => 'Contract created successfully.',
            'contract' => new ContractResource($contract->load(['employee.department', 'department', 'createdBy'])),
        ], 201);
    }

    // ── Read ──────────────────────────────────────────────────────────────

    /**
     * Return a single contract.
     *
     * @param  int          $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $contract = Contract::with(['employee.department', 'department', 'createdBy', 'approvedBy'])
            ->findOrFail($id);

        return response()->json(['contract' => new ContractResource($contract)]);
    }

    // ── Update ────────────────────────────────────────────────────────────

    /**
     * Update an existing contract.
     *
     * @param  Request      $request
     * @param  int          $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $contract = Contract::findOrFail($id);

        $request->validate([
            'type'          => 'sometimes|in:full_time,part_time,contract,intern,probation,fixed_term,unlimited',
            'status'        => 'sometimes|in:draft,active,expired,terminated,cancelled',
            'start_date'    => 'sometimes|date',
            'end_date'      => 'nullable|date|after:start_date',
            'salary'        => 'nullable|numeric|min:0',
            'currency'      => 'sometimes|string|size:3',
            'position'      => 'nullable|string|max:150',
            'department_id' => 'nullable|exists:departments,id',
            'terms'         => 'nullable|string',
        ]);

        $contract->update($request->only([
            'type', 'status', 'start_date', 'end_date',
            'salary', 'currency', 'position', 'department_id', 'terms',
        ]));

        return response()->json([
            'message'  => 'Contract updated.',
            'contract' => new ContractResource($contract->fresh(['employee.department', 'department'])),
        ]);
    }

    // ── Approve ───────────────────────────────────────────────────────────

    /**
     * Approve a draft contract (sets status → active).
     *
     * @param  int          $id
     * @return JsonResponse
     */
    public function approve(int $id): JsonResponse
    {
        $contract = Contract::findOrFail($id);

        if ($contract->status !== 'draft') {
            return response()->json(['message' => 'Only draft contracts can be approved.'], 422);
        }

        $contract->update([
            'status'      => 'active',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'message'  => 'Contract approved and activated.',
            'contract' => new ContractResource($contract->fresh(['employee', 'approvedBy'])),
        ]);
    }

    // ── Delete ────────────────────────────────────────────────────────────

    /**
     * Soft-delete a contract.
     *
     * @param  int          $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $contract = Contract::findOrFail($id);
        $contract->delete();
        return response()->json(['message' => 'Contract deleted.']);
    }

    // ── Employee contracts ────────────────────────────────────────────────

    /**
     * List all contracts for a specific employee.
     *
     * @param  int          $empId
     * @return JsonResponse
     */
    public function forEmployee(int $empId): JsonResponse
    {
        $contracts = Contract::with(['department', 'createdBy', 'approvedBy'])
            ->where('employee_id', $empId)
            ->orderBy('start_date', 'desc')
            ->get();

        return response()->json([
            'contracts' => ContractResource::collection($contracts),
        ]);
    }
}
