<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Contract for Employee data-access operations.
 *
 * All data retrieval and persistence for the Employee domain must go
 * through this interface, keeping controllers and services free from
 * direct Eloquent calls and making the layer unit-testable via mocks.
 */
interface EmployeeRepositoryInterface
{
    /**
     * Return a paginated, filtered list of employees.
     *
     * @param  array<string,mixed> $filters  Supported keys: search, status,
     *                                        department_id, employment_type,
     *                                        sort_by, sort_dir, per_page, page
     * @return LengthAwarePaginator<Employee>
     */
    public function paginate(array $filters): LengthAwarePaginator;

    /**
     * Find an employee by primary key, eager-loading standard relations.
     *
     * @param  int $id
     * @return Employee
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findById(int $id): Employee;

    /**
     * Persist a new employee record.
     *
     * @param  array<string,mixed> $data
     * @return Employee
     */
    public function create(array $data): Employee;

    /**
     * Update an existing employee record.
     *
     * @param  Employee            $employee
     * @param  array<string,mixed> $data
     * @return Employee
     */
    public function update(Employee $employee, array $data): Employee;

    /**
     * Soft-delete and terminate an employee.
     *
     * @param  Employee $employee
     * @return void
     */
    public function terminate(Employee $employee): void;

    /**
     * Return all active employees (for dropdowns / selects).
     *
     * @return Collection<int,Employee>
     */
    public function allActive(): Collection;

    /**
     * Generate a collision-safe, sequential employee code (e.g. EMP0042).
     *
     * Uses a database-level lock to prevent race conditions under concurrent
     * employee creation requests.
     *
     * @return string
     */
    public function nextEmployeeCode(): string;
}
