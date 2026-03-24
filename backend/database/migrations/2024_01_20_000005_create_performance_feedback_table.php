<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('performance_feedback')) Schema::create('performance_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('review_id')->nullable()->constrained('performance_reviews')->nullOnDelete();
            $table->enum('relationship', ['self','manager','peer','report','client'])->default('peer');
            $table->boolean('is_anonymous')->default(false);
            $table->unsignedTinyInteger('communication')->nullable();
            $table->unsignedTinyInteger('teamwork')->nullable();
            $table->unsignedTinyInteger('technical')->nullable();
            $table->unsignedTinyInteger('leadership')->nullable();
            $table->unsignedTinyInteger('initiative')->nullable();
            $table->text('strengths')->nullable();
            $table->text('improvements')->nullable();
            $table->text('overall_comment')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('performance_feedback'); }
};
