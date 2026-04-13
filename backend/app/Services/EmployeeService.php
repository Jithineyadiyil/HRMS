<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveAllocation;
use App\Models\LeaveType;
use App\Models\OnboardingTask;

/**
 * Handles Employee business logic that spans multiple models or
 * operations that do not fit cleanly into a single Repository method.
 */
class EmployeeService
{
    /** @var array<int,array<string,mixed>> */
    private const ONBOARDING_TASKS = [
        ['title' => 'Issue company laptop and equipment',      'category' => 'it_setup',     'sort_order' => 1],
        ['title' => 'Create email and system accounts',        'category' => 'it_setup',     'sort_order' => 2],
        ['title' => 'Sign employment contract',                'category' => 'hr_documents', 'sort_order' => 3],
        ['title' => 'Sign NDA and confidentiality agreement',  'category' => 'hr_documents', 'sort_order' => 4],
        ['title' => 'Complete mandatory compliance training',  'category' => 'training',     'sort_order' => 5],
        ['title' => 'Introduce to team and department',        'category' => 'introduction', 'sort_order' => 6],
        ['title' => 'Set up buddy / mentor',                   'category' => 'introduction', 'sort_order' => 7],
        ['title' => '30-day probation check-in',               'category' => 'probation',    'sort_order' => 8],
        ['title' => '60-day probation check-in',               'category' => 'probation',    'sort_order' => 9],
        ['title' => '90-day probation review',                 'category' => 'probation',    'sort_order' => 10],
    ];

    public function __construct(
        private readonly ExportService $exportService,
    ) {}

    /**
     * Generate a unique employee code in the format EMP0001.
     *
     * Uses the total count of all employees (including soft-deleted) to ensure
     * the sequence never repeats, even after deletions.
     *
     * @return string  e.g. "EMP0042"
     */
    public function generateCode(): string
    {
        $count = Employee::withTrashed()->count() + 1;
        return 'EMP' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create one LeaveAllocation per active LeaveType for a new employee.
     *
     * @param  Employee $employee
     * @return void
     */
    public function createDefaultLeaveAllocations(Employee $employee): void
    {
        $year  = now()->year;
        $types = LeaveType::where('is_active', true)->get();

        foreach ($types as $type) {
            LeaveAllocation::create([
                'employee_id'    => $employee->id,
                'leave_type_id'  => $type->id,
                'year'           => $year,
                'allocated_days' => $type->days_allowed,
                'remaining_days' => $type->days_allowed,
                'used_days'      => 0,
                'pending_days'   => 0,
            ]);
        }
    }

    /**
     * Create the default set of onboarding tasks for a new employee.
     *
     * @param  Employee $employee
     * @return void
     */
    public function createOnboardingTasks(Employee $employee): void
    {
        foreach (self::ONBOARDING_TASKS as $task) {
            OnboardingTask::create([
                'employee_id' => $employee->id,
                'title'       => $task['title'],
                'category'    => $task['category'],
                'sort_order'  => $task['sort_order'],
                'status'      => 'pending',
                'due_date'    => $employee->hire_date->addDays($task['sort_order'] * 7),
            ]);
        }
    }

    /**
     * Export employee data as a CSV file download.
     *
     * @param  array<string,mixed> $filters
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function export(array $filters): mixed
    {
        $employees = Employee::with(['department', 'designation'])
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->where('department_id', $v))
            ->when($filters['status']         ?? null, fn ($q, $v) => $q->where('status', $v))
            ->get()
            ->map(fn (Employee $e) => [
                'Code'            => $e->employee_code,
                'First Name'      => $e->first_name,
                'Last Name'       => $e->last_name,
                'Email'           => $e->email,
                'Phone'           => $e->phone,
                'Department'      => $e->department?->name,
                'Designation'     => $e->designation?->title,
                'Employment Type' => $e->employment_type,
                'Status'          => $e->status,
                'Hire Date'       => $e->hire_date?->format('Y-m-d'),
                'Salary'          => $e->salary,
            ]);

        return $this->exportService->csvDownload(
            'employees_' . now()->format('Ymd') . '.csv',
            ['Code', 'First Name', 'Last Name', 'Email', 'Phone', 'Department',
             'Designation', 'Employment Type', 'Status', 'Hire Date', 'Salary'],
            $employees
        );
    }
}
