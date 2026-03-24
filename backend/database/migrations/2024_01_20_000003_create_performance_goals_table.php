<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('performance_goals')) Schema::create('performance_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('review_id')->nullable()->constrained('performance_reviews')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('category', ['professional','learning','leadership','project','personal','okr'])->default('professional');
            $table->enum('priority', ['low','medium','high','critical'])->default('medium');
            $table->enum('status', ['not_started','in_progress','achieved','on_hold','cancelled'])->default('not_started');
            $table->decimal('target_value', 10, 2)->nullable();
            $table->decimal('current_value', 10, 2)->default(0);
            $table->string('unit')->nullable();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('achieved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('performance_goals'); }
};
