<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Loan Types ────────────────────────────────────────────────────
        Schema::create('loan_types', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100);
            $table->string('code', 20)->unique();
            $table->decimal('max_amount', 12, 2)->default(0)->comment('0 = no limit');
            $table->integer('max_installments')->default(12);
            $table->decimal('interest_rate', 5, 2)->default(0)->comment('Annual % — 0 for interest-free');
            $table->boolean('requires_guarantor')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // ── Loans ─────────────────────────────────────────────────────────
        Schema::create('loans', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('reference', 30)->unique()->comment('e.g. LOAN-2025-00042');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('loan_type_id');

            $table->decimal('requested_amount', 12, 2);
            $table->decimal('approved_amount',  12, 2)->nullable();
            $table->integer('installments')->comment('Number of monthly installments');
            $table->decimal('monthly_installment', 10, 2)->nullable();
            $table->text('purpose');
            $table->text('notes')->nullable();

            // 7-stage status flow
            $table->enum('status', [
                'pending_manager',
                'pending_hr',
                'pending_finance',
                'approved',
                'disbursed',
                'completed',
                'rejected',
                'cancelled',
            ])->default('pending_manager');

            // Approvals
            $table->unsignedBigInteger('manager_approved_by')->nullable();
            $table->timestamp('manager_approved_at')->nullable();
            $table->unsignedBigInteger('hr_approved_by')->nullable();
            $table->timestamp('hr_approved_at')->nullable();
            $table->unsignedBigInteger('finance_approved_by')->nullable();
            $table->timestamp('finance_approved_at')->nullable();

            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->enum('rejected_stage', ['manager','hr','finance'])->nullable();

            $table->date('disbursed_date')->nullable();
            $table->date('first_installment_date')->nullable();

            // Running totals
            $table->decimal('total_paid', 12, 2)->default(0);
            $table->decimal('balance_remaining', 12, 2)->default(0);
            $table->integer('installments_paid')->default(0);
            $table->integer('installments_skipped')->default(0);

            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('loan_type_id')->references('id')->on('loan_types');
            $table->foreign('manager_approved_by')->references('id')->on('users');
            $table->foreign('hr_approved_by')->references('id')->on('users');
            $table->foreign('finance_approved_by')->references('id')->on('users');
            $table->foreign('rejected_by')->references('id')->on('users');
        });

        // ── Loan Installments ─────────────────────────────────────────────
        Schema::create('loan_installments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('loan_id');
            $table->integer('installment_no');
            $table->date('due_date');
            $table->decimal('amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->enum('status', ['pending','paid','skipped','overdue'])->default('pending');
            $table->date('paid_date')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('loan_id')->references('id')->on('loans')->onDelete('cascade');
            $table->foreign('processed_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_installments');
        Schema::dropIfExists('loans');
        Schema::dropIfExists('loan_types');
    }
};
