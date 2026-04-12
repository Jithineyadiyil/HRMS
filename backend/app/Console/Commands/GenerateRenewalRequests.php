<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\ContractRenewalRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Scans for contracts expiring within 60 days and auto-generates renewal requests.
 *
 * Run daily via Laravel scheduler.
 * Skips contracts that already have an open renewal request.
 *
 * Usage:
 *   php artisan contracts:generate-renewals
 *   php artisan contracts:generate-renewals --days=90  (custom window)
 */
class GenerateRenewalRequests extends Command
{
    /** @var string */
    protected $signature = 'contracts:generate-renewals {--days=60 : Days before expiry to trigger renewal}';

    /** @var string */
    protected $description = 'Auto-generate renewal requests for contracts expiring within the given number of days';

    /**
     * Execute the command.
     *
     * @return int
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');

        $this->info("Scanning for contracts expiring within {$days} days…");

        // Find active contracts expiring within the window
        $expiring = Contract::with(['employee.manager'])
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now()->toDateString(), now()->addDays($days)->toDateString()])
            ->get();

        $generated = 0;
        $skipped   = 0;

        foreach ($expiring as $contract) {
            // Skip if an open renewal already exists for this contract
            $existing = ContractRenewalRequest::where('contract_id', $contract->id)
                ->whereNotIn('status', ['rejected', 'cancelled'])
                ->exists();

            if ($existing) {
                $skipped++;
                $this->line("  Skipping {$contract->reference} — renewal already open.");
                continue;
            }

            // Determine manager_id from the employee's direct manager
            $managerId = $contract->employee?->manager_id ?? null;

            ContractRenewalRequest::create([
                'contract_id'          => $contract->id,
                'employee_id'          => $contract->employee_id,
                'reference'            => ContractRenewalRequest::generateReference(),
                'status'               => 'pending',
                'proposed_start_date'  => $contract->end_date->addDay(),
                'proposed_end_date'    => $contract->end_date?->addYear(),  // default: 1-year renewal
                'proposed_salary'      => $contract->salary,
                'proposed_type'        => $contract->type,
                'manager_id'           => $managerId,
                'auto_generated'       => true,
                'notified_at'          => now(),
                'notes'                => "Auto-generated: contract {$contract->reference} expires on {$contract->end_date->toDateString()}. {$days}-day renewal window triggered.",
            ]);

            $generated++;

            $this->line("  ✓ Created renewal request for {$contract->reference} "
                      . "(expires {$contract->end_date->toDateString()})");

            Log::info('[Contracts] Renewal request auto-generated', [
                'contract_reference' => $contract->reference,
                'employee_id'        => $contract->employee_id,
                'expires_on'         => $contract->end_date->toDateString(),
            ]);
        }

        $this->info("Done. Generated: {$generated} | Skipped (existing): {$skipped}");

        return self::SUCCESS;
    }
}
