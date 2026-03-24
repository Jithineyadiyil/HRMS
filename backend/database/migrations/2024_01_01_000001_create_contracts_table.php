<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->enum('contract_type', ['fixed', 'unlimited', 'part_time', 'freelance'])->default('fixed');
            $table->string('position')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->date('probation_end')->nullable();
            $table->decimal('salary', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            // Track if renewal notification was sent / request created
            $table->boolean('renewal_notified')->default(false);
            $table->boolean('renewal_requested')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'is_active']);
            $table->index('end_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
