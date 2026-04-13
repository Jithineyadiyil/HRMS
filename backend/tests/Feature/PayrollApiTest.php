<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\Payslip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for the Payroll API.
 *
 * @group payroll
 */
class PayrollApiTest extends TestCase
{
    use RefreshDatabase;

    private User $hrManager;
    private User $financeManager;
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        // FIX: correct seeder class name
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);

        $this->hrManager = User::factory()->create();
        $this->hrManager->assignRole('hr_manager');

        $this->financeManager = User::factory()->create();
        $this->financeManager->assignRole('finance_manager');

        $this->employee = User::factory()->create();
        $this->employee->assignRole('employee');

        Employee::factory()->count(3)->create(['status' => 'active']);
    }

    /** @test */
    public function stats_returns_correct_structure(): void
    {
        $this->actingAs($this->hrManager, 'sanctum')
            ->getJson('/api/v1/payroll/stats')
            ->assertOk()
            ->assertJsonStructure([
                'total_runs', 'pending_approval', 'approved', 'paid',
                'latest_net', 'latest_gross', 'latest_month',
            ]);
    }

    /** @test */
    public function hr_manager_can_run_payroll(): void
    {
        $month = now()->format('Y-m');

        $this->actingAs($this->hrManager, 'sanctum')
            ->postJson('/api/v1/payroll/run', [
                'month'        => $month,
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end'   => now()->endOfMonth()->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Payroll run successfully');

        $this->assertDatabaseHas('payrolls', ['month' => $month]);
    }

    /** @test */
    public function duplicate_payroll_run_is_rejected(): void
    {
        $month = now()->format('Y-m');

        Payroll::factory()->create(['month' => $month, 'status' => 'pending_approval']);

        $this->actingAs($this->hrManager, 'sanctum')
            ->postJson('/api/v1/payroll/run', [
                'month'        => $month,
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end'   => now()->endOfMonth()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', fn ($msg) => str_contains($msg, 'already exists'));
    }

    /** @test */
    public function run_requires_valid_month_format(): void
    {
        $this->actingAs($this->hrManager, 'sanctum')
            ->postJson('/api/v1/payroll/run', [
                'month'        => 'invalid',
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end'   => now()->endOfMonth()->toDateString(),
            ])
            ->assertUnprocessable();
    }

    /** @test */
    public function finance_manager_can_approve_payroll(): void
    {
        $payroll = Payroll::factory()->create(['status' => 'pending_approval']);

        $this->actingAs($this->financeManager, 'sanctum')
            ->postJson("/api/v1/payroll/{$payroll->id}/approve")
            ->assertOk()
            ->assertJsonPath('payroll.status', 'approved');
    }

    /** @test */
    public function regular_employee_cannot_approve_payroll(): void
    {
        $payroll = Payroll::factory()->create(['status' => 'pending_approval']);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/v1/payroll/{$payroll->id}/approve")
            ->assertForbidden();
    }

    /** @test */
    public function cannot_approve_already_approved_payroll(): void
    {
        $payroll = Payroll::factory()->create(['status' => 'approved']);

        $this->actingAs($this->financeManager, 'sanctum')
            ->postJson("/api/v1/payroll/{$payroll->id}/approve")
            ->assertUnprocessable();
    }

    /** @test */
    public function finance_manager_can_mark_payroll_as_paid(): void
    {
        $payroll = Payroll::factory()->create(['status' => 'approved']);

        $this->actingAs($this->financeManager, 'sanctum')
            ->postJson("/api/v1/payroll/{$payroll->id}/mark-paid")
            ->assertOk()
            ->assertJsonPath('payroll.status', 'paid');
    }

    /** @test */
    public function cannot_mark_pending_payroll_as_paid(): void
    {
        $payroll = Payroll::factory()->create(['status' => 'pending_approval']);

        $this->actingAs($this->financeManager, 'sanctum')
            ->postJson("/api/v1/payroll/{$payroll->id}/mark-paid")
            ->assertUnprocessable();
    }

    /** @test */
    public function hr_manager_can_reject_payroll(): void
    {
        $payroll = Payroll::factory()->create(['status' => 'pending_approval']);

        $this->actingAs($this->hrManager, 'sanctum')
            ->postJson("/api/v1/payroll/{$payroll->id}/reject", ['reason' => 'Data errors found.'])
            ->assertOk();

        $this->assertDatabaseHas('payrolls', ['id' => $payroll->id, 'status' => 'rejected']);
    }

    /** @test */
    public function approved_payroll_can_be_reopened(): void
    {
        $payroll = Payroll::factory()->create(['status' => 'approved']);

        $this->actingAs($this->hrManager, 'sanctum')
            ->postJson("/api/v1/payroll/{$payroll->id}/reopen")
            ->assertOk()
            ->assertJsonPath('payroll.status', 'pending_approval');
    }

    /** @test */
    public function can_list_payslips_for_payroll(): void
    {
        $payroll = Payroll::factory()->create();
        Payslip::factory()->count(3)->create(['payroll_id' => $payroll->id]);

        $this->actingAs($this->hrManager, 'sanctum')
            ->getJson("/api/v1/payroll/{$payroll->id}/payslips")
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    /** @test */
    public function unauthenticated_access_is_rejected(): void
    {
        $this->getJson('/api/v1/payroll/stats')->assertUnauthorized();
    }
}
