<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Contract;
use App\Models\ContractRenewal;
use Carbon\Carbon;

class CreateContractRenewals extends Command
{
    protected $signature   = 'contracts:create-renewals';
    protected $description = 'Auto-create renewal requests for contracts expiring within 60 days (Saudi Arabia 1-year contracts)';

    public function handle(): void
    {
        // Find all active fixed-term contracts expiring within 60 days
        // that don't already have an open renewal
        $contracts = Contract::active()
            ->whereIn('contract_type', ['fixed', 'part_time', 'freelance'])
            ->whereDate('end_date', '>=', now())
            ->whereDate('end_date', '<=', now()->addDays(60))
            ->where('renewal_requested', false)
            ->get();

        $created = 0;

        foreach ($contracts as $contract) {
            // Double-check: no open renewal already
            $hasOpen = $contract->renewals()
                ->whereNotIn('status', ['approved', 'rejected'])
                ->exists();

            if ($hasOpen) {
                $contract->update(['renewal_requested' => true]);
                continue;
            }

            ContractRenewal::create([
                'contract_id'         => $contract->id,
                'status'              => 'pending_manager',
                'auto_created'        => true,
                'requested_by'        => null,
                // Proposed: next 1-year cycle from end date
                'proposed_start_date' => Carbon::parse($contract->end_date)->addDay(),
                'proposed_end_date'   => Carbon::parse($contract->end_date)->addYear(),
            ]);

            $contract->update([
                'renewal_notified'  => true,
                'renewal_requested' => true,
            ]);

            $created++;

            $this->line("✓ Renewal created for employee #{$contract->employee_id} — contract ends {$contract->end_date}");
        }

        $this->info("Done. {$created} renewal request(s) created.");
    }
}
