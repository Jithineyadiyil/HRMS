<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the employee_contracts table.
 *
 * Stores employment contract records with version tracking,
 * status workflow, and optional PDF path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_contracts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('employee_id');
            $table->string('reference', 50)->unique();
            $table->enum('type', [
                'full_time', 'part_time', 'contract', 'intern',
                'probation', 'fixed_term', 'unlimited',
            ])->default('full_time');
            $table->enum('status', [
                'draft', 'active', 'expired', 'terminated', 'cancelled',
            ])->default('draft');
            $table->date('start_date');
            $table->date('end_date')->nullable();          // null = unlimited
            $table->decimal('salary', 12, 2)->nullable();
            $table->string('currency', 3)->default('SAR');
            $table->string('position', 150)->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->text('terms')->nullable();             // contract body / notes
            $table->string('pdf_path', 500)->nullable();   // uploaded / generated PDF
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('employee_id')
                  ->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('department_id')
                  ->references('id')->on('departments')->onDelete('set null');
            $table->foreign('created_by')
                  ->references('id')->on('users')->onDelete('set null');
            $table->foreign('approved_by')
                  ->references('id')->on('users')->onDelete('set null');

            $table->index(['employee_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_contracts');
    }
};
