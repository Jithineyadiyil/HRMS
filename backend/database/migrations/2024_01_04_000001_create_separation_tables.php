<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── Separation Requests ───────────────────────────────────────────
        Schema::create('separations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('reference', 30)->unique();  // SEP-2025-00001
            $table->unsignedBigInteger('employee_id');

            $table->enum('type', [
                'resignation',
                'termination',
                'end_of_contract',
                'retirement',
                'abandonment',
                'mutual_agreement',
            ]);

            // Status flow
            $table->enum('status', [
                'draft',
                'submitted',
                'pending_manager',
                'pending_hr',
                'approved',
                'offboarding',
                'completed',
                'cancelled',
                'rejected',
            ])->default('draft');

            // Dates
            $table->date('request_date');
            $table->date('last_working_day');
            $table->date('notice_period_start')->nullable();
            $table->integer('notice_period_days')->default(30);
            $table->boolean('notice_waived')->default(false);
            $table->text('notice_waived_reason')->nullable();

            // Reason / details
            $table->text('reason');
            $table->enum('reason_category', [
                'personal', 'better_opportunity', 'relocation', 'health',
                'misconduct', 'performance', 'restructuring', 'contract_end', 'other'
            ])->nullable();
            $table->text('hr_notes')->nullable();

            // Approvals
            $table->unsignedBigInteger('initiated_by');    // who created it
            $table->unsignedBigInteger('manager_approved_by')->nullable();
            $table->timestamp('manager_approved_at')->nullable();
            $table->unsignedBigInteger('hr_approved_by')->nullable();
            $table->timestamp('hr_approved_at')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            // Exit interview
            $table->boolean('exit_interview_required')->default(true);
            $table->boolean('exit_interview_done')->default(false);
            $table->date('exit_interview_date')->nullable();
            $table->text('exit_interview_notes')->nullable();

            // Final settlement
            $table->decimal('gratuity_amount', 12, 2)->default(0);
            $table->decimal('leave_encashment', 12, 2)->default(0);
            $table->decimal('other_deductions', 12, 2)->default(0);
            $table->decimal('other_additions', 12, 2)->default(0);
            $table->decimal('final_settlement_amount', 12, 2)->default(0);
            $table->boolean('settlement_paid')->default(false);
            $table->date('settlement_paid_date')->nullable();
            $table->text('settlement_notes')->nullable();

            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('initiated_by')->references('id')->on('users');
            $table->foreign('manager_approved_by')->references('id')->on('users');
            $table->foreign('hr_approved_by')->references('id')->on('users');
            $table->foreign('rejected_by')->references('id')->on('users');
        });

        // ── Offboarding Checklist Templates ───────────────────────────────
        Schema::create('offboarding_templates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title', 150);
            $table->string('category', 60)->default('general'); // it, hr, finance, assets, admin
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── Offboarding Checklist Items (per separation) ──────────────────
        Schema::create('offboarding_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('separation_id');
            $table->unsignedBigInteger('template_id')->nullable();
            $table->string('title', 150);
            $table->string('category', 60)->default('general');
            $table->boolean('is_required')->default(true);
            $table->enum('status', ['pending','completed','skipped','na'])->default('pending');
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('separation_id')->references('id')->on('separations')->onDelete('cascade');
            $table->foreign('template_id')->references('id')->on('offboarding_templates');
            $table->foreign('completed_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offboarding_items');
        Schema::dropIfExists('offboarding_templates');
        Schema::dropIfExists('separations');
    }
};
