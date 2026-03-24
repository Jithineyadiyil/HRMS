<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_renewals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();

            // Proposed new contract dates (populated when CEO approves)
            $table->date('proposed_start_date')->nullable();
            $table->date('proposed_end_date')->nullable();

            // Approval workflow: pending_manager → pending_hr → pending_ceo → approved | rejected
            $table->enum('status', [
                'pending_manager',
                'pending_hr',
                'pending_ceo',
                'approved',
                'rejected',
            ])->default('pending_manager');

            // Track which stage it was rejected at
            $table->string('rejected_at_stage')->nullable();
            $table->text('rejection_reason')->nullable();

            // Who requested this (could be HR via cron or manually)
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('auto_created')->default(false); // true = created by scheduler

            // Approver references
            $table->foreignId('manager_approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('hr_approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ceo_approver_id')->nullable()->constrained('users')->nullOnDelete();

            // Approval timestamps
            $table->timestamp('manager_approved_at')->nullable();
            $table->timestamp('hr_approved_at')->nullable();
            $table->timestamp('ceo_approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['contract_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_renewals');
    }
};
