<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Create jobs table if it doesn't exist yet
        if (!Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('manpower_request_id')->nullable();
                $table->string('source')->nullable()->default('direct'); // 'direct' | 'manpower_request'
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->string('title');
                $table->enum('status', ['open','paused','closed','filled'])->default('open');
                $table->enum('employment_type', ['full_time','part_time','contract','internship','freelance'])->default('full_time');
                $table->unsignedInteger('vacancies')->default(1);
                $table->string('location')->nullable();
                $table->unsignedInteger('experience_years')->nullable();
                $table->unsignedBigInteger('salary_min')->nullable();
                $table->unsignedBigInteger('salary_max')->nullable();
                $table->date('deadline')->nullable();
                $table->text('description')->nullable();
                $table->text('requirements')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            // Table exists — just add the missing columns
            Schema::table('jobs', function (Blueprint $table) {
                if (!Schema::hasColumn('jobs', 'manpower_request_id')) {
                    $table->unsignedBigInteger('manpower_request_id')->nullable()->after('id');
                }
                if (!Schema::hasColumn('jobs', 'source')) {
                    $table->string('source')->nullable()->default('direct')->after('manpower_request_id');
                }
                if (!Schema::hasColumn('jobs', 'created_by')) {
                    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void {
        Schema::dropIfExists('jobs');
    }
};
