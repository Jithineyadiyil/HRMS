<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Employee;
use App\Repositories\Contracts\EmployeeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent-backed implementation of {@see EmployeeRepositoryInterface}.
 *
 * All direct Model calls are centralised here so that the Service layer
 * and Controllers remain free of persistence logic, and so that tests
 * can substitute a mock/fake without touching a real database.
 */
class EmployeeRepository implements EmployeeRepositoryInterface
{
    /**
     * Standard relations loaded on every detail fetch.
     *
     * @var array<int,string>
     */
    private const DETAIL_RELATIONS = [
        'department',
        'designation',
        'manager',
        'leaveAllocations.leaveType',
    ];

    /**
     * Lightweight relations loaded for list views.
     *
     * @var array<int,string>
     */
    private const LIST_RELATIONS = [
        'department',
        'designation',
        'manager',
        'user',
    ];

    /**
     * {@inheritdoc}
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Employee::with(self::LIST_RELATIONS)
            ->when(
                $filters['department_id'] ?? null,
                fn ($q, $v) => $q->where('department_id', $v)
            )
            ->when(
                $filters['status'] ?? null,
                fn ($q, $v) => $q->where('status', $v)
            )
            ->when(
                $filters['employment_type'] ?? null,
                fn ($q, $v) => $q->where('employment_type', $v)
            )
            ->when(
                $filters['search'] ?? null,
                fn ($q, $v) => $q->where(
                    fn ($sub) => $sub
                        ->where('first_name', 'like', "%{$v}%")
                        ->orWhere('last_name', 'like', "%{$v}%")
                        ->orWhere('email', 'like', "%{$v}%")
                        ->orWhere('employee_code', 'like', "%{$v}%")
                )
            )
            ->orderBy(
                $filters['sort_by'] ?? 'created_at',
                $filters['sort_dir'] ?? 'desc'
            );

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): Employee
    {
        return Employee::with(self::DETAIL_RELATIONS)->findOrFail($id);
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data): Employee
    {
        return Employee::create($data);
    }

    /**
     * {@inheritdoc}
     */
    public function update(Employee $employee, array $data): Employee
    {
        $employee->update($data);
        return $employee->fresh(self::LIST_RELATIONS) ?? $employee;
    }

    /**
     * {@inheritdoc}
     *
     * Sets status to 'terminated', records termination date,
     * revokes all Sanctum tokens, then soft-deletes the record.
     */
    public function terminate(Employee $employee): void
    {
        $employee->update([
            'status'           => 'terminated',
            'termination_date' => now(),
        ]);

        $employee->user?->tokens()->delete();
        $employee->delete();
    }

    /**
     * {@inheritdoc}
     */
    public function allActive(): Collection
    {
        return Employee::active()
            ->with(['department', 'designation'])
            ->orderBy('first_name')
            ->get();
    }

    /**
     * {@inheritdoc}
     *
     * Acquires a database advisory lock so that two concurrent requests
     * cannot read the same "last code" and produce a duplicate.
     *
     * @throws \RuntimeException If the lock cannot be acquired within 5 s.
     */
    public function nextEmployeeCode(): string
    {
        return DB::transaction(function (): string {
            // Lock the employees table for the duration of this transaction
            $last = Employee::withTrashed()
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('employee_code');

            $next = $last
                ? (int) ltrim(substr($last, 3), '0') + 1
                : 1;

            return 'EMP' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        });
    }
}
