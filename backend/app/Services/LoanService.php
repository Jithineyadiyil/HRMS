<?php
namespace App\Services;

use App\Models\Loan;
use App\Models\LoanInstallment;
use Carbon\Carbon;

class LoanService
{
    // ── Generate unique reference ─────────────────────────────────────────
    public function generateReference(): string
    {
        $year  = now()->year;
        $count = Loan::whereYear('created_at', $year)->count() + 1;
        return 'LOAN-' . $year . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
    }

    // ── Calculate monthly installment (flat interest) ─────────────────────
    public function calculateMonthlyInstallment(float $amount, int $installments, float $annualRate = 0): float
    {
        if ($installments <= 0) return $amount;
        if ($annualRate <= 0)   return round($amount / $installments, 2);

        $monthlyRate = $annualRate / 100 / 12;
        // Standard amortization formula
        $payment = $amount * ($monthlyRate * pow(1 + $monthlyRate, $installments))
                           / (pow(1 + $monthlyRate, $installments) - 1);
        return round($payment, 2);
    }

    // ── Generate installment schedule ─────────────────────────────────────
    public function generateInstallments(Loan $loan): void
    {
        // Remove any existing schedule first
        $loan->installments()->delete();

        $startDate   = $loan->first_installment_date
                     ? Carbon::parse($loan->first_installment_date)
                     : Carbon::parse($loan->disbursed_date)->addMonth()->startOfMonth();

        $amount      = $loan->approved_amount ?? $loan->requested_amount;
        $installAmt  = $loan->monthly_installment
                     ?? $this->calculateMonthlyInstallment($amount, $loan->installments);

        $rows = [];
        for ($i = 1; $i <= $loan->installments; $i++) {
            // Last installment gets the rounding remainder
            $installAmount = ($i === $loan->installments)
                ? round($amount - ($installAmt * ($loan->installments - 1)), 2)
                : $installAmt;

            $rows[] = [
                'loan_id'          => $loan->id,
                'installment_no'   => $i,
                'due_date'         => $startDate->copy()->addMonths($i - 1)->toDateString(),
                'amount'           => $installAmount,
                'paid_amount'      => 0,
                'status'           => 'pending',
                'created_at'       => now(),
                'updated_at'       => now(),
            ];
        }
        LoanInstallment::insert($rows);

        // Sync balance
        $loan->update(['balance_remaining' => $amount]);
    }

    // ── Mark installment as paid ──────────────────────────────────────────
    public function payInstallment(LoanInstallment $inst, ?string $paidDate = null, ?string $notes = null): void
    {
        $inst->update([
            'status'       => 'paid',
            'paid_amount'  => $inst->amount,
            'paid_date'    => $paidDate ?? now()->toDateString(),
            'processed_by' => auth()->id(),
            'notes'        => $notes,
        ]);

        $loan = $inst->loan;
        $loan->increment('total_paid',          $inst->amount);
        $loan->decrement('balance_remaining',   $inst->amount);
        $loan->increment('installments_paid');

        // Auto-complete if all paid
        $pendingCount = $loan->installments()->whereIn('status',['pending','overdue'])->count();
        if ($pendingCount === 0) {
            $loan->update(['status' => 'completed', 'balance_remaining' => 0]);
        }
    }

    // ── Skip installment for one month ────────────────────────────────────
    public function skipInstallment(LoanInstallment $inst, ?string $notes = null): void
    {
        $inst->update([
            'status'       => 'skipped',
            'processed_by' => auth()->id(),
            'notes'        => $notes ?? 'Skipped — deferred to next month',
        ]);

        $loan = $inst->loan;
        $loan->increment('installments_skipped');

        // Push a new installment at the end of the schedule
        // NOTE: Cannot use $loan->installments()->orderBy() because the relationship
        // already has a default ASC order; chaining DESC appends rather than replaces,
        // causing it to still return installment #1. Query directly instead.
        $lastInst = LoanInstallment::where('loan_id', $loan->id)
                        ->orderBy('installment_no', 'desc')
                        ->first();
        $newDue   = Carbon::parse($lastInst->due_date)->addMonth()->toDateString();

        LoanInstallment::create([
            'loan_id'        => $loan->id,
            'installment_no' => $lastInst->installment_no + 1,
            'due_date'       => $newDue,
            'amount'         => $inst->amount,
            'status'         => 'pending',
        ]);
    }

    // ── Mark overdue installments ─────────────────────────────────────────
    public function markOverdue(): int
    {
        return LoanInstallment::where('status','pending')
            ->where('due_date', '<', now()->toDateString())
            ->update(['status' => 'overdue']);
    }

    // ── Loan summary stats ────────────────────────────────────────────────
    public function stats(): array
    {
        return [
            'pending_manager'  => Loan::where('status','pending_manager')->count(),
            'pending_hr'       => Loan::where('status','pending_hr')->count(),
            'pending_finance'  => Loan::where('status','pending_finance')->count(),
            'active_loans'     => Loan::whereIn('status',['approved','disbursed'])->count(),
            'total_outstanding'=> Loan::whereIn('status',['approved','disbursed'])->sum('balance_remaining'),
            'completed'        => Loan::where('status','completed')->count(),
        ];
    }
}
