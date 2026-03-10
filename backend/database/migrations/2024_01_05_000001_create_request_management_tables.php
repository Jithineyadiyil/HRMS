<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── Request Types (HR configures these) ───────────────────────────
        Schema::create('request_types', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 120);
            $table->string('code', 30)->unique();
            $table->string('category', 60)->default('general');
            // categories: visa, travel, documents, hr, it, admin, finance, other
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();    // shown to employee when filling
            $table->integer('sla_days')->default(3);     // expected completion days
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('requires_manager_approval')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->string('icon', 50)->default('description'); // Material icon name
            $table->string('color', 20)->default('#6366f1');
            $table->timestamps();
        });

        // ── Requests ──────────────────────────────────────────────────────
        Schema::create('employee_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('reference', 30)->unique();  // REQ-2025-00001
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('request_type_id');

            $table->enum('status', [
                'pending',          // submitted, waiting for HR
                'pending_manager',  // awaiting manager approval first
                'in_progress',      // HR is processing
                'completed',        // done, document ready
                'rejected',         // declined
                'cancelled',        // withdrawn by employee
            ])->default('pending');

            $table->text('details');        // employee fills in specifics
            $table->text('hr_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->date('required_by')->nullable();    // when employee needs it
            $table->integer('copies_needed')->default(1);

            // Approval
            $table->unsignedBigInteger('manager_approved_by')->nullable();
            $table->timestamp('manager_approved_at')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();      // HR staff
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();

            // SLA tracking
            $table->date('due_date')->nullable();
            $table->boolean('is_overdue')->default(false);

            // Completion document
            $table->string('completion_file')->nullable();
            $table->text('completion_notes')->nullable();

            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('request_type_id')->references('id')->on('request_types');
            $table->foreign('manager_approved_by')->references('id')->on('users');
            $table->foreign('assigned_to')->references('id')->on('users');
            $table->foreign('completed_by')->references('id')->on('users');
            $table->foreign('rejected_by')->references('id')->on('users');
        });

        // ── Request Comments / Activity ───────────────────────────────────
        Schema::create('request_comments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('user_id');
            $table->text('comment');
            $table->boolean('is_internal')->default(false); // HR-only notes
            $table->timestamps();

            $table->foreign('request_id')->references('id')->on('employee_requests')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_comments');
        Schema::dropIfExists('employee_requests');
        Schema::dropIfExists('request_types');
    }
};
