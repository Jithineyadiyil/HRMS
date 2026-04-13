<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveAllocation;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feature tests for the Leave Management API.
 *
 * @group leave
 */
class LeaveApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User     $hrManager;
    private User     $deptManager;
    private User     $employee;
    private Employee $empRecord;
    private LeaveType $annualLeave;
    private LeaveType $sickLeave;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        // FIX: correct seeder class name (no 'And')
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);

        $this->hrManager = User::factory()->create();
        $this->hrManager->assignRole('hr_manager');

        $this->deptManager = User::factory()->create();
        $this->deptManager->assignRole('department_manager');

        $this->employee = User::factory()->create();
        $this->employee->assignRole('employee');

        $this->empRecord = Employee::factory()->create(['user_id' => $this->employee->id]);

        $this->annualLeave = LeaveType::factory()->create([
            'name'                  => 'Annual Leave',
            'code'                  => 'AL',
            'days_allowed'          => 21,
            'is_paid'               => true,
            'skip_manager_approval' => false,
            'requires_document'     => false,
        ]);

        $this->sickLeave = LeaveType::factory()->create([
            'name'                  => 'Sick Leave',
            'code'                  => 'SL',
            'days_allowed'          => 14,
            'is_paid'               => true,
            'skip_manager_approval' => true,
            'requires_document'     => false,
        ]);

        LeaveAllocation::factory()->create([
            'employee_id'        => $this->empRecord->id,
            'leave_type_id'      => $this->annualLeave->id,
            'year'               => now()->year,
            'allocated_days'     => 21,
            'annual_entitlement' => 21,
            'used_days'          => 0,
            'pending_days'       => 0,
            'remaining_days'     => 21,
        ]);
    }

    /** @test */
    public function hr_can_fetch_leave_types(): void
    {
        $this->actingAs($this->hrManager, 'sanctum')
            ->getJson('/api/v1/leave/types')
            ->assertOk()
            ->assertJsonStructure(['types' => [['id', 'name', 'code', 'days_allowed']]]);
    }

    /** @test */
    public function hr_can_create_leave_type(): void
    {
        $this->actingAs($this->hrManager, 'sanctum')
            ->postJson('/api/v1/leave/types', [
                'name'         => 'Maternity Leave',
                'code'         => 'ML',
                'days_allowed' => 90,
                'is_paid'      => true,
            ])
            ->assertCreated()
            ->assertJsonPath('type.code', 'ML');

        $this->assertDatabaseHas('leave_types', ['code' => 'ML']);
    }

    /** @test */
    public function leave_type_code_must_be_unique(): void
    {
        $this->actingAs($this->hrManager, 'sanctum')
            ->postJson('/api/v1/leave/types', [
                'name'         => 'Duplicate Annual',
                'code'         => 'AL',
                'days_allowed' => 21,
            ])
            ->assertUnprocessable();
    }

    /** @test */
    public function employee_can_submit_leave_request(): void
    {
        $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/v1/leave/requests', [
                'leave_type_id' => $this->annualLeave->id,
                'start_date'    => now()->addDays(5)->toDateString(),
                'end_date'      => now()->addDays(7)->toDateString(),
                'reason'        => 'Family vacation scheduled for next week',
            ])
            ->assertCreated()
            ->assertJsonPath('request.status', 'pending');

        $this->assertDatabaseHas('leave_requests', [
            'employee_id'   => $this->empRecord->id,
            'leave_type_id' => $this->annualLeave->id,
            'status'        => 'pending',
        ]);
    }

    /** @test */
    public function sick_leave_skips_manager_stage(): void
    {
        $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/v1/leave/requests', [
                'leave_type_id' => $this->sickLeave->id,
                'start_date'    => now()->addDay()->toDateString(),
                'end_date'      => now()->addDays(2)->toDateString(),
                'reason'        => 'Medical appointment confirmed by doctor',
            ])
            ->assertCreated()
            ->assertJsonPath('request.status', 'manager_approved');
    }

    /** @test */
    public function leave_request_fails_with_insufficient_balance(): void
    {
        LeaveAllocation::where('employee_id', $this->empRecord->id)->update(['remaining_days' => 0]);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/v1/leave/requests', [
                'leave_type_id' => $this->annualLeave->id,
                'start_date'    => now()->addDays(5)->toDateString(),
                'end_date'      => now()->addDays(7)->toDateString(),
                'reason'        => 'Family vacation insufficient balance test',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', fn ($msg) => str_contains($msg, 'Insufficient'));
    }

    /** @test */
    public function leave_request_requires_minimum_reason_length(): void
    {
        $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/v1/leave/requests', [
                'leave_type_id' => $this->annualLeave->id,
                'start_date'    => now()->addDays(5)->toDateString(),
                'end_date'      => now()->addDays(7)->toDateString(),
                'reason'        => 'Short',
            ])
            ->assertUnprocessable();
    }

    /** @test */
    public function manager_can_approve_at_stage_1(): void
    {
        $leave = LeaveRequest::factory()->create([
            'employee_id'   => $this->empRecord->id,
            'leave_type_id' => $this->annualLeave->id,
            'status'        => 'pending',
        ]);

        $this->actingAs($this->deptManager, 'sanctum')
            ->postJson("/api/v1/leave/requests/{$leave->id}/approve")
            ->assertOk();

        $this->assertDatabaseHas('leave_requests', ['id' => $leave->id, 'status' => 'manager_approved']);
    }

    /** @test */
    public function hr_can_give_final_approval(): void
    {
        $leave = LeaveRequest::factory()->create([
            'employee_id'         => $this->empRecord->id,
            'leave_type_id'       => $this->annualLeave->id,
            'status'              => 'manager_approved',
            'manager_approved_by' => $this->deptManager->id,
            'manager_approved_at' => now(),
        ]);

        $this->actingAs($this->hrManager, 'sanctum')
            ->postJson("/api/v1/leave/requests/{$leave->id}/approve")
            ->assertOk();

        $this->assertDatabaseHas('leave_requests', ['id' => $leave->id, 'status' => 'approved']);
    }

    /** @test */
    public function employee_cannot_approve_leave(): void
    {
        $leave = LeaveRequest::factory()->create([
            'employee_id'   => $this->empRecord->id,
            'leave_type_id' => $this->annualLeave->id,
            'status'        => 'pending',
        ]);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/v1/leave/requests/{$leave->id}/approve")
            ->assertForbidden();
    }

    /** @test */
    public function hr_can_reject_leave_with_reason(): void
    {
        $leave = LeaveRequest::factory()->create([
            'employee_id'   => $this->empRecord->id,
            'leave_type_id' => $this->annualLeave->id,
            'status'        => 'pending',
        ]);

        $this->actingAs($this->hrManager, 'sanctum')
            ->postJson("/api/v1/leave/requests/{$leave->id}/reject", [
                'reason' => 'Critical project deadline in the same period.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('leave_requests', ['id' => $leave->id, 'status' => 'rejected']);
    }

    /** @test */
    public function rejection_requires_reason(): void
    {
        $leave = LeaveRequest::factory()->create([
            'employee_id'   => $this->empRecord->id,
            'leave_type_id' => $this->annualLeave->id,
            'status'        => 'pending',
        ]);

        $this->actingAs($this->hrManager, 'sanctum')
            ->postJson("/api/v1/leave/requests/{$leave->id}/reject", [])
            ->assertUnprocessable();
    }

    /** @test */
    public function employee_can_cancel_pending_leave(): void
    {
        $leave = LeaveRequest::factory()->create([
            'employee_id'   => $this->empRecord->id,
            'leave_type_id' => $this->annualLeave->id,
            'status'        => 'pending',
        ]);

        $this->actingAs($this->employee, 'sanctum')
            ->deleteJson("/api/v1/leave/requests/{$leave->id}")
            ->assertOk();

        $this->assertDatabaseHas('leave_requests', ['id' => $leave->id, 'status' => 'cancelled']);
    }

    /** @test */
    public function employee_can_view_own_leave_balance(): void
    {
        $this->actingAs($this->employee, 'sanctum')
            ->getJson("/api/v1/leave/balance/{$this->empRecord->id}")
            ->assertOk()
            ->assertJsonStructure(['balances' => [['leave_type_id', 'allocated_days', 'remaining_days']]]);
    }

    /** @test */
    public function stats_endpoint_returns_counts(): void
    {
        $this->actingAs($this->hrManager, 'sanctum')
            ->getJson('/api/v1/leave/stats')
            ->assertOk()
            ->assertJsonStructure(['pending_count', 'on_leave_today', 'approved_month', 'cancelled_count']);
    }

    /** @test */
    public function unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/leave/requests')->assertUnauthorized();
    }
}
