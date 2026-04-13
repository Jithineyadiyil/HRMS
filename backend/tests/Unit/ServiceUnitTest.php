<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanType;
use App\Services\LoanService;
use App\Services\LeaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for LoanService and LeaveService business logic.
 *
 * @group unit
 */
class ServiceUnitTest extends TestCase
{
    use RefreshDatabase;

    private LoanService  $loanService;
    private LeaveService $leaveService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loanService  = app(LoanService::class);
        $this->leaveService = app(LeaveService::class);
    }

    // ── LoanService: Monthly installment ──────────────────────────────────

    /** @test */
    public function calculates_monthly_installment_without_interest(): void
    {
        $result = $this->loanService->calculateMonthlyInstallment(12000, 12, 0);
        $this->assertEquals(1000.0, $result);
    }

    /** @test */
    public function calculates_monthly_installment_with_interest(): void
    {
        $result = $this->loanService->calculateMonthlyInstallment(10000, 12, 12);
        $this->assertEqualsWithDelta(888.49, $result, 1.0);
    }

    /** @test */
    public function single_installment_returns_full_amount(): void
    {
        $result = $this->loanService->calculateMonthlyInstallment(5000, 1, 0);
        $this->assertEquals(5000.0, $result);
    }

    // ── LoanService: Reference generation ────────────────────────────────

    /** @test */
    public function generates_loan_reference_with_correct_prefix(): void
    {
        $ref = $this->loanService->generateReference();

        // Actual format is 'LOAN-YYYY-NNNNN'
        $this->assertStringStartsWith('LOAN-', $ref);
        $this->assertMatchesRegularExpression('/^LOAN-\d{4}-\d{5}$/', $ref);
    }

    // ── LoanService: Stats ────────────────────────────────────────────────

    /** @test */
    public function stats_returns_expected_keys(): void
    {
        $stats = $this->loanService->stats();

        // Actual keys from LoanService::stats()
        $this->assertArrayHasKey('pending_manager', $stats);
        $this->assertArrayHasKey('pending_hr',      $stats);
        $this->assertArrayHasKey('pending_finance',  $stats);
        $this->assertArrayHasKey('active_loans',     $stats);
        $this->assertArrayHasKey('completed',        $stats);
    }

    // ── LoanService: Mark overdue ─────────────────────────────────────────

    /** @test */
    public function marks_past_due_installments_as_overdue(): void
    {
        $loanType = LoanType::factory()->create();
        $loan     = Loan::factory()->create(['loan_type_id' => $loanType->id, 'status' => 'disbursed']);

        LoanInstallment::factory()->create([
            'loan_id'  => $loan->id,
            'due_date' => now()->subDays(5)->toDateString(),
            'status'   => 'pending',
        ]);

        $count = $this->loanService->markOverdue();

        $this->assertGreaterThan(0, $count);
        $this->assertDatabaseHas('loan_installments', ['loan_id' => $loan->id, 'status' => 'overdue']);
    }

    // ── LeaveService: Working days ────────────────────────────────────────

    /** @test */
    public function calculates_working_days_excluding_weekend(): void
    {
        // 2026-04-06 = Monday, 2026-04-10 = Friday (Saudi Fri/Sat weekend)
        // Mon, Tue, Wed, Thu = 4 working days (Fri excluded)
        $result = $this->leaveService->calculateWorkingDays('2026-04-06', '2026-04-10');
        $this->assertEquals(4, $result);
    }

    /** @test */
    public function calculates_single_day_leave(): void
    {
        $result = $this->leaveService->calculateWorkingDays('2026-04-07', '2026-04-07'); // Tuesday
        $this->assertEquals(1, $result);
    }

    // ── LeaveService: Excuse hours ────────────────────────────────────────

    /** @test */
    public function excuse_hours_calculated_correctly(): void
    {
        $hours = $this->leaveService->calculateExcuseHours('2026-04-07', '08:00', '10:30');
        $this->assertEquals(2.5, $hours);
    }
}
