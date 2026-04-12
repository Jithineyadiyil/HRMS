<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the contract_renewal_requests table.
 *
 * Tracks the 3-level approval workflow (Manager → HR → CEO) for contract renewals.
 * Auto-generated 60 days before contract expiry by the GenerateRenewalRequests command.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_renewal_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('contract_id');
            $table->unsignedBigInteger('employee_id');
            $table->string('reference', 60)->unique();  // e.g. RNW-2024-00012

            // ── Proposed renewal terms ──────────────────────────────────────
            $table->date('proposed_start_date');
            $table->date('proposed_end_date')->nullable();   // null = unlimited
            $table->decimal('proposed_salary', 12, 2)->nullable();
            $table->string('proposed_type', 30)->nullable(); // new contract type
            $table->text('notes')->nullable();

            // ── Workflow status ─────────────────────────────────────────────
            $table->enum('status', [
                'pending',           // waiting for manager
                'manager_approved',  // manager approved, waiting for HR
                'hr_approved',       // HR approved, waiting for CEO
                'approved',          // CEO approved — final
                'rejected',          // rejected at any stage
                'cancelled',         // contract expired/closed before completion
            ])->default('pending');

            // ── Level 1: Manager ────────────────────────────────────────────
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->unsignedBigInteger('manager_approved_by')->nullable();
            $table->timestamp('manager_approved_at')->nullable();
            $table->text('manager_notes')->nullable();

            // ── Level 2: HR ─────────────────────────────────────────────────
            $table->unsignedBigInteger('hr_approved_by')->nullable();
            $table->timestamp('hr_approved_at')->nullable();
            $table->text('hr_notes')->nullable();

            // ── Level 3: CEO ─────────────────────────────────────────────────
            $table->unsignedBigInteger('ceo_approved_by')->nullable();
            $table->timestamp('ceo_approved_at')->nullable();
            $table->text('ceo_notes')->nullable();

            // ── Rejection ───────────────────────────────────────────────────
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejected_stage', 20)->nullable(); // manager|hr|ceo
            $table->text('rejection_reason')->nullable();

            // ── Result ──────────────────────────────────────────────────────
            $table->unsignedBigInteger('new_contract_id')->nullable(); // created after full approval
            $table->boolean('auto_generated')->default(true);
            $table->timestamp('notified_at')->nullable();              // when the 60-day email was sent

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('contract_id')->references('id')->on('employee_contracts')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('new_contract_id')->references('id')->on('employee_contracts')->onDelete('set null');

            $table->index(['contract_id', 'status']);
            $table->index(['employee_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_renewal_requests');
    }
};
