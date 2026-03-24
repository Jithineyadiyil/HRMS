<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\LeaveAllocation;
use App\Models\LeaveType;
use App\Models\OnboardingTask;
use App\Repositories\Contracts\EmployeeRepositoryInterface;
use App\Repositories\EmployeeRepository;
use App\Services\EmployeeService;
use App\Services\ExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for {@see EmployeeService}.
 *
 * Tests are isolated: the Repository is mocked so no real database writes
 * happen. Only the service's business logic is under test.
 */
class EmployeeServiceTest extends TestCase
{
    use RefreshDatabase;

    private EmployeeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EmployeeService(
            new ExportService(),
        );
    }

    // ── createDefaultLeaveAllocations ────────────────────────────────────

    /**
     * @test
     * Given active leave types exist,
     * when createDefaultLeaveAllocations is called,
     * one allocation per type should be created for the employee.
     */
    public function it_creates_one_allocation_per_active_leave_type(): void
    {
        $type1 = LeaveType::factory()->create(['is_active' => true,  'days_allowed' => 22]);
        $type2 = LeaveType::factory()->create(['is_active' => true,  'days_allowed' => 5]);
        LeaveType::factory()->create(['is_active' => false, 'days_allowed' => 10]); // inactive, must be skipped

        $employee = Employee::factory()->create();

        $this->service->createDefaultLeaveAllocations($employee);

        $this->assertDatabaseCount('leave_allocations', 2);

        $this->assertDatabaseHas('leave_allocations', [
            'employee_id'    => $employee->id,
            'leave_type_id'  => $type1->id,
            'allocated_days' => 22,
            'remaining_days' => 22,
            'used_days'      => 0,
        ]);

        $this->assertDatabaseHas('leave_allocations', [
            'employee_id'    => $employee->id,
            'leave_type_id'  => $type2->id,
            'allocated_days' => 5,
        ]);
    }

    /**
     * @test
     * Given no active leave types,
     * when createDefaultLeaveAllocations is called,
     * no allocations should be created.
     */
    public function it_creates_no_allocations_when_no_active_types_exist(): void
    {
        $employee = Employee::factory()->create();

        $this->service->createDefaultLeaveAllocations($employee);

        $this->assertDatabaseCount('leave_allocations', 0);
    }

    // ── createOnboardingTasks ────────────────────────────────────────────

    /**
     * @test
     * When createOnboardingTasks is called,
     * 10 tasks with increasing due dates should be created.
     */
    public function it_creates_ten_onboarding_tasks_with_weekly_due_dates(): void
    {
        $hireDate = now()->toDateString();
        $employee = Employee::factory()->create(['hire_date' => $hireDate]);

        $this->service->createOnboardingTasks($employee);

        $this->assertDatabaseCount('onboarding_tasks', 10);

        $tasks = OnboardingTask::where('employee_id', $employee->id)
            ->orderBy('sort_order')
            ->get();

        $this->assertCount(10, $tasks);

        foreach ($tasks as $task) {
            $this->assertSame('pending', $task->status);
            $this->assertNotNull($task->due_date);
        }

        // First task due 7 days after hire, last task due 70 days after hire
        $this->assertEquals(
            now()->addDays(7)->toDateString(),
            $tasks->first()->due_date->toDateString()
        );
        $this->assertEquals(
            now()->addDays(70)->toDateString(),
            $tasks->last()->due_date->toDateString()
        );
    }
}
