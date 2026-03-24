<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('performance_cycles')) Schema::create('performance_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['annual','mid_year','quarterly','probation','pip'])->default('annual');
            $table->string('review_period')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('self_assessment_deadline')->nullable();
            $table->date('manager_review_deadline')->nullable();
            $table->boolean('include_360')->default(false);
            $table->enum('status', ['draft','active','closed'])->default('draft');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('performance_cycles'); }
};
