<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRecruitmentTables extends Migration {
    public function up() {
        Schema::create('job_postings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title', 150);
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('designation_id')->nullable();
            $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'intern']);
            $table->enum('status', ['draft', 'open', 'closed', 'on_hold'])->default('draft');
            $table->integer('vacancies')->default(1);
            $table->text('description');
            $table->text('requirements')->nullable();
            $table->text('benefits')->nullable();
            $table->decimal('salary_min', 12, 2)->nullable();
            $table->decimal('salary_max', 12, 2)->nullable();
            $table->string('location', 100)->nullable();
            $table->date('closing_date')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            $table->foreign('designation_id')->references('id')->on('designations')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users');
        });

        Schema::create('job_applications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('job_posting_id');
            $table->string('applicant_name', 150);
            $table->string('applicant_email', 191);
            $table->string('applicant_phone', 20)->nullable();
            $table->string('cv_path')->nullable();
            $table->string('cover_letter_path')->nullable();
            $table->text('cover_letter_text')->nullable();
            $table->enum('stage', ['applied', 'screening', 'interview', 'offer', 'hired', 'rejected'])->default('applied');
            $table->text('hr_notes')->nullable();
            $table->decimal('expected_salary', 12, 2)->nullable();
            $table->date('available_from')->nullable();
            $table->timestamps();
            $table->foreign('job_posting_id')->references('id')->on('job_postings')->onDelete('cascade');
        });

        Schema::create('interviews', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('application_id');
            $table->string('round', 50); // HR, Technical, Final
            $table->datetime('scheduled_at');
            $table->integer('duration_minutes')->default(60);
            $table->enum('format', ['in_person', 'video', 'phone'])->default('video');
            $table->string('location_or_link')->nullable();
            $table->enum('status', ['scheduled', 'completed', 'cancelled', 'no_show'])->default('scheduled');
            $table->text('feedback')->nullable();
            $table->enum('result', ['pass', 'fail', 'pending'])->default('pending');
            $table->json('interviewers')->nullable();
            $table->timestamps();
            $table->foreign('application_id')->references('id')->on('job_applications')->onDelete('cascade');
        });

        Schema::create('onboarding_tasks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('employee_id');
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->enum('category', ['it_setup', 'hr_documents', 'training', 'introduction', 'probation'])->default('hr_documents');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'skipped'])->default('pending');
            $table->date('due_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('assigned_to')->references('id')->on('users');
        });
    }
    public function down() {
        Schema::dropIfExists('onboarding_tasks');
        Schema::dropIfExists('interviews');
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('job_postings');
    }
}
