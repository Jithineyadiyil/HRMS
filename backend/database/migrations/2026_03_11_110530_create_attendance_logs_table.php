<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('attendance_logs')) {
            Schema::create('attendance_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('employee_id');
                $table->date('date');
                $table->time('check_in')->nullable();
                $table->time('check_out')->nullable();
                $table->integer('total_minutes')->nullable();
                $table->enum('status', ['present', 'absent', 'late', 'half_day', 'on_leave', 'holiday'])->default('present');
                $table->enum('source', ['manual', 'api', 'biometric', 'import'])->default('api');
                $table->string('ip_address', 45)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['employee_id', 'date']);
                $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};