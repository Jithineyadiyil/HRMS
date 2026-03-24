<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('manpower_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique()->nullable();
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('department_id')->constrained('departments')->onDelete('cascade');
            $table->string('position_title');
            $table->unsignedTinyInteger('headcount')->default(1);
            $table->unsignedTinyInteger('approved_headcount')->nullable();
            $table->enum('employment_type', ['full_time','part_time','contract','internship','freelance'])->default('full_time');
            $table->enum('urgency', ['low','medium','high','critical'])->default('medium');
            $table->text('reason');
            $table->date('expected_start_date')->nullable();
            $table->unsignedBigInteger('salary_min')->nullable();
            $table->unsignedBigInteger('salary_max')->nullable();
            $table->text('job_description')->nullable();
            $table->text('requirements')->nullable();
            $table->text('notes')->nullable();
            $table->text('hr_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->enum('status', ['draft','pending_hr','approved','rejected','hired'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->boolean('job_posting_created')->default(false);
            $table->unsignedBigInteger('job_posting_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('manpower_requests'); }
};
