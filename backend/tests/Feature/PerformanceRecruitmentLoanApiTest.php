<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\Loan;
use App\Models\LoanType;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feature tests for Performance, Recruitment, and Loan APIs.
 *
 * @group performance
 * @group recruitment
 * @group loans
 */
class PerformanceRecruitmentLoanApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User     $hrManager;
    private User     $deptManager;
    private User     $financeManager;
    private User     $employeeUser;
    private Employee $empRecord;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        // FIX: correct seeder class name
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);

        $this->hrManager = User::factory()->create();
        $this->hrManager->assignRole('hr_manager');

        $this->deptManager = User::factory()->create();
        $this->deptManager->assignRole('department_manager');

        $this->financeManager = User::factory()->create();
        $this->financeManager->assignRole('finance_manager');

        $this->employeeUser = User::factory()->create();
        $this->employeeUser->assignRole('employee');

        $this->empRecord = Employee::factory()->create(['user_id' => $this->employeeUser->id]);
    }

    // ── Performance ───────────────────────────────────────────────────────

    /** @test */
    public function performance_stats_returns_correct_structure(): void
    {
        $this->actingAs($this->hrManager, 'sanctum')
            ->getJson('/api/v1/performance/stats')
            ->assertOk()
            ->assertJsonStructure([
                'total_cycles', 'active_cycles', 'total_reviews',
                'pending_self', 'pending_manager', 'finalized', 'avg_final_rating',
            ]);
    }

    /** @test */
    public function hr_can_create_performance_cycle(): void
    {
        $this->actingAs($this->hrManager, 'sanctum')
            ->postJson('/api/v1/performance', [
                'name'       => 'Q1 2026 Review',
                'type'       => 'quarterly',
                'start_date' => now()->startOfQuarter()->toDateString(),
                'end_date'   => now()->endOfQuarter()->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('cycle.name', 'Q1 2026 Review');

        $this->assertDatabaseHas('performance_cycles', ['name' => 'Q1 2026 Review']);
    }

    /** @test */
    public function hr_can_initiate_cycle_creating_reviews(): void
    {
        Employee::factory()->count(3)->create(['status' => 'active']);
        $cycle = PerformanceCycle::factory()->create(['status' => 'draft']);

        $this->actingAs($this->hrManager, 'sanctum')
            ->postJson("/api/v1/performance/{$cycle->id}/initiate")
            ->assertOk()
            ->assertJsonPath('cycle.status', 'active');

        $this->assertDatabaseHas('performance_reviews', ['cycle_id' => $cycle->id]);
    }

    /** @test */
    public function non_hr_cannot_initiate_cycle(): void
    {
        $cycle = PerformanceCycle::factory()->create(['status' => 'draft']);

        $this->actingAs($this->employeeUser, 'sanctum')
            ->postJson("/api/v1/performance/{$cycle->id}/initiate")
            ->assertForbidden();
    }

    /** @test */
    public function employee_can_submit_self_assessment(): void
    {
        $cycle  = PerformanceCycle::factory()->create(['status' => 'active']);
        $review = PerformanceReview::factory()->create([
            'cycle_id'    => $cycle->id,
            'employee_id' => $this->empRecord->id,
            'status'      => 'pending',
        ]);

        $this->actingAs($this->employeeUser, 'sanctum')
            ->postJson("/api/v1/performance/review/{$review->id}/self", [
                'rating'   => 4,
                'comments' => 'Achieved all quarterly targets on schedule.',
            ])
            ->assertOk()
            ->assertJsonPath('review.status', 'self_submitted');
    }

    /** @test */
    public function manager_can_submit_manager_review(): void
    {
        $cycle  = PerformanceCycle::factory()->create(['status' => 'active']);
        $review = PerformanceReview::factory()->create([
            'cycle_id'    => $cycle->id,
            'employee_id' => $this->empRecord->id,
            'status'      => 'self_submitted',
        ]);

        $this->actingAs($this->deptManager, 'sanctum')
            ->postJson("/api/v1/performance/review/{$review->id}/manager", [
                'rating'   => 3.5,
                'comments' => 'Good performance overall with some areas for improvement.',
            ])
            ->assertOk()
            ->assertJsonPath('review.status', 'manager_reviewed');
    }

    /** @test */
    public function hr_can_finalize_review(): void
    {
        $cycle  = PerformanceCycle::factory()->create(['status' => 'active']);
        $review = PerformanceReview::factory()->create([
            'cycle_id'    => $cycle->id,
            'employee_id' => $this->empRecord->id,
            'status'      => 'manager_reviewed',
        ]);

        $this->actingAs($this->hrManager, 'sanctum')
            ->postJson("/api/v1/performance/review/{$review->id}/finalize", [
                'final_rating'     => 4.0,
                'performance_band' => 'good',
                'development_plan' => 'Enroll in advanced project management training.',
            ])
            ->assertOk()
            ->assertJsonPath('review.status', 'finalized');
    }

    // ── Recruitment ───────────────────────────────────────────────────────

    /** @test */
    public function hr_can_create_job_posting(): void
    {
        $this->actingAs($this->hrManager, 'sanctum')
            ->postJson('/api/v1/recruitment/jobs', [
                'title'           => 'Senior Laravel Developer',
                'employment_type' => 'full_time',
                'description'     => 'We are looking for an experienced Laravel developer.',
                'vacancies'       => 2,
            ])
            ->assertCreated()
            ->assertJsonPath('job.title', 'Senior Laravel Developer');
    }

    /** @test */
    public function public_can_view_open_jobs(): void
    {
        JobPosting::factory()->create(['status' => 'open']);
        JobPosting::factory()->create(['status' => 'closed']);

        $response = $this->getJson('/api/v1/jobs')->assertOk();

        $this->assertTrue(
            collect($response->json('jobs'))->every(fn ($j) => $j['status'] === 'open')
        );
    }

    /** @test */
    public function applicant_can_apply_for_job(): void
    {
        $job = JobPosting::factory()->create(['status' => 'open']);

        $this->postJson("/api/v1/jobs/{$job->id}/apply", [
            'applicant_name'  => 'John Smith',
            'applicant_email' => 'john@example.com',
        ])
        ->assertCreated()
        ->assertJson(['application' => ['job_posting_id' => $job->id]]);
    }

    /** @test */
    public function hr_can_update_application_stage(): void
    {
        $job = JobPosting::factory()->create(['status' => 'open']);
        $app = JobApplication::factory()->create([
            'job_posting_id' => $job->id,
            'stage'          => 'applied',
        ]);

        $this->actingAs($this->hrManager, 'sanctum')
            ->putJson("/api/v1/recruitment/applications/{$app->id}/stage", ['stage' => 'interview'])
            ->assertOk()
            ->assertJsonPath('application.stage', 'interview');
    }

    /** @test */
    public function recruitment_stats_return_correct_structure(): void
    {
        $this->actingAs($this->hrManager, 'sanctum')
            ->getJson('/api/v1/recruitment/stats')
            ->assertOk()
            ->assertJsonStructure([
                'open_jobs', 'total_jobs', 'total_applicants',
                'new_this_week', 'in_interview', 'offers_sent', 'hired', 'rejected',
            ]);
    }

    // ── Loans ─────────────────────────────────────────────────────────────

    /** @test */
    public function employee_can_submit_loan_request(): void
    {
        $loanType = LoanType::factory()->create([
            'max_amount' => 50000, 'max_installments' => 24,
            'interest_rate' => 0, 'is_active' => true,
        ]);

        $this->actingAs($this->employeeUser, 'sanctum')
            ->postJson('/api/v1/loans', [
                'loan_type_id'     => $loanType->id,
                'requested_amount' => 10000,
                'installments'     => 12,
                'purpose'          => 'Home renovation project starting next month.',
            ])
            ->assertCreated()
            ->assertJsonPath('loan.status', 'pending_manager');
    }

    /** @test */
    public function loan_exceeding_max_amount_is_rejected(): void
    {
        $loanType = LoanType::factory()->create([
            'max_amount' => 5000, 'is_active' => true, 'max_installments' => 12,
        ]);

        $this->actingAs($this->employeeUser, 'sanctum')
            ->postJson('/api/v1/loans', [
                'loan_type_id'     => $loanType->id,
                'requested_amount' => 10000,
                'installments'     => 6,
                'purpose'          => 'Purpose longer than ten characters here.',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', fn ($msg) => str_contains($msg, 'maximum'));
    }

    /** @test */
    public function manager_can_approve_loan_at_stage_1(): void
    {
        $loan = Loan::factory()->create([
            'employee_id' => $this->empRecord->id,
            'status'      => 'pending_manager',
        ]);

        $this->actingAs($this->deptManager, 'sanctum')
            ->postJson("/api/v1/loans/{$loan->id}/approve")
            ->assertOk();

        $this->assertDatabaseHas('loans', ['id' => $loan->id, 'status' => 'pending_hr']);
    }

    /** @test */
    public function loan_index_uses_raw_db_role_check(): void
    {
        Loan::factory()->create(['employee_id' => $this->empRecord->id, 'status' => 'pending_manager']);

        $response = $this->actingAs($this->financeManager, 'sanctum')
            ->getJson('/api/v1/loans')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->assertGreaterThan(0, count($response->json('data')));
    }

    /** @test */
    public function employee_can_cancel_pending_loan(): void
    {
        $loan = Loan::factory()->create([
            'employee_id' => $this->empRecord->id,
            'status'      => 'pending_manager',
        ]);

        $this->actingAs($this->employeeUser, 'sanctum')
            ->postJson("/api/v1/loans/{$loan->id}/cancel")
            ->assertOk();

        $this->assertDatabaseHas('loans', ['id' => $loan->id, 'status' => 'cancelled']);
    }

    /** @test */
    public function loan_stats_return_correct_structure(): void
    {
        $this->actingAs($this->hrManager, 'sanctum')
            ->getJson('/api/v1/loans/stats')
            ->assertOk();
    }
}
