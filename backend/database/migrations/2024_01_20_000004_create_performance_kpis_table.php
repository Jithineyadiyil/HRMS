<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('performance_kpis')) Schema::create('performance_kpis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('review_id')->nullable()->constrained('performance_reviews')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('target', 12, 2)->nullable();
            $table->decimal('actual',  12, 2)->nullable();
            $table->string('unit')->nullable();
            $table->string('period')->nullable();
            $table->enum('frequency', ['daily','weekly','monthly','quarterly','annual'])->default('monthly');
            $table->decimal('weight', 5, 2)->default(1);
            $table->enum('status', ['on_track','at_risk','missed','achieved'])->default('on_track');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('performance_kpis'); }
};
