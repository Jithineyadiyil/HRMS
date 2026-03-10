<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_excuse_limits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('department_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->decimal('monthly_hours_limit', 5, 2)->nullable()
                  ->comment('NULL = unlimited. Set a value to cap hours per month.');
            $table->boolean('is_limited')->default(true)
                  ->comment('false = unlimited regardless of monthly_hours_limit');
            $table->timestamps();

            $table->unique(['department_id', 'leave_type_id'], 'dept_leavetype_unique');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('cascade');
            $table->foreign('leave_type_id')->references('id')->on('leave_types')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_excuse_limits');
    }
};
