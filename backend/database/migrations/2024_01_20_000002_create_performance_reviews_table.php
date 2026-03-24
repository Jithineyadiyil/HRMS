<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('performance_reviews')) Schema::create('performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no')->unique();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cycle_id')->nullable()->constrained('performance_cycles')->nullOnDelete();
            $table->enum('review_type', ['annual','mid_year','quarterly','probation','pip'])->default('annual');
            $table->string('review_period')->nullable();
            $table->date('due_date')->nullable();
            $table->enum('status', ['draft','pending_self','pending_manager','completed','cancelled'])->default('draft');
            $table->decimal('self_score', 3, 1)->nullable();
            $table->decimal('manager_score', 3, 1)->nullable();
            $table->decimal('feedback_360_score', 3, 1)->nullable();
            $table->decimal('final_score', 3, 1)->nullable();
            $table->json('self_assessment')->nullable();
            $table->json('manager_evaluation')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('self_submitted_at')->nullable();
            $table->timestamp('manager_submitted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('performance_reviews'); }
};
