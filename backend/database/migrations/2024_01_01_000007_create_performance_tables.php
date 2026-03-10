<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePerformanceTables extends Migration {
    public function up() {
        Schema::create('kpis', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->enum('category', ['quantitative', 'qualitative', 'behavioral', 'learning']);
            $table->decimal('target_value', 10, 2)->nullable();
            $table->string('unit', 50)->nullable();
            $table->integer('weight')->default(10); // percentage weight
            $table->integer('year');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
        });

        Schema::create('performance_cycles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100);
            $table->enum('type', ['annual', 'semi_annual', 'quarterly']);
            $table->date('start_date');
            $table->date('end_date');
            $table->date('self_assessment_deadline')->nullable();
            $table->date('manager_review_deadline')->nullable();
            $table->enum('status', ['draft', 'active', 'completed', 'archived'])->default('draft');
            $table->timestamps();
        });

        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('cycle_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('reviewer_id')->nullable();
            $table->enum('status', ['pending', 'self_submitted', 'manager_reviewed', 'hr_calibrated', 'finalized'])->default('pending');
            // Self assessment
            $table->decimal('self_rating', 3, 1)->nullable();
            $table->text('self_comments')->nullable();
            $table->json('self_kpi_scores')->nullable();
            // Manager review
            $table->decimal('manager_rating', 3, 1)->nullable();
            $table->text('manager_comments')->nullable();
            $table->json('manager_kpi_scores')->nullable();
            // Final
            $table->decimal('final_rating', 3, 1)->nullable();
            $table->enum('performance_band', ['excellent', 'good', 'average', 'below_average', 'poor'])->nullable();
            $table->text('development_plan')->nullable();
            $table->text('hr_notes')->nullable();
            $table->timestamps();
            $table->unique(['cycle_id', 'employee_id']);
            $table->foreign('cycle_id')->references('id')->on('performance_cycles')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('reviewer_id')->references('id')->on('employees');
        });
    }
    public function down() {
        Schema::dropIfExists('performance_reviews');
        Schema::dropIfExists('performance_cycles');
        Schema::dropIfExists('kpis');
    }
}
