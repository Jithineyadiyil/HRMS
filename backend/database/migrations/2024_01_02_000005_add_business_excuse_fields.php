<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add hourly fields to leave_requests
        Schema::table('leave_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('leave_requests', 'start_time')) {
                $table->time('start_time')->nullable()->after('start_date')
                      ->comment('For hourly leave (e.g. Business Excuse)');
            }
            if (!Schema::hasColumn('leave_requests', 'end_time')) {
                $table->time('end_time')->nullable()->after('start_time')
                      ->comment('For hourly leave');
            }
            if (!Schema::hasColumn('leave_requests', 'total_hours')) {
                $table->decimal('total_hours', 5, 2)->nullable()->after('total_days')
                      ->comment('Duration in hours for hourly leave types');
            }
        });

        // Add hourly config to leave_types
        Schema::table('leave_types', function (Blueprint $table) {
            if (!Schema::hasColumn('leave_types', 'is_hourly')) {
                $table->boolean('is_hourly')->default(false)->after('requires_document')
                      ->comment('If true, leave is measured in hours not days');
            }
            if (!Schema::hasColumn('leave_types', 'monthly_hours_limit')) {
                $table->decimal('monthly_hours_limit', 5, 2)->nullable()->after('is_hourly')
                      ->comment('Monthly cap in hours. NULL = unlimited (e.g. Sales team override)');
            }
            if (!Schema::hasColumn('leave_types', 'exempt_department_codes')) {
                $table->json('exempt_department_codes')->nullable()->after('monthly_hours_limit')
                      ->comment('Departments exempt from the monthly hours limit');
            }
        });

        // Add used_hours to leave_allocations
        Schema::table('leave_allocations', function (Blueprint $table) {
            if (!Schema::hasColumn('leave_allocations', 'used_hours')) {
                $table->decimal('used_hours', 7, 2)->default(0)->after('remaining_days')
                      ->comment('Total hours consumed this month (for hourly leave)');
            }
            if (!Schema::hasColumn('leave_allocations', 'pending_hours')) {
                $table->decimal('pending_hours', 7, 2)->default(0)->after('used_hours');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests',   fn($t) => $t->dropColumn(['start_time','end_time','total_hours']));
        Schema::table('leave_types',      fn($t) => $t->dropColumn(['is_hourly','monthly_hours_limit','exempt_department_codes']));
        Schema::table('leave_allocations',fn($t) => $t->dropColumn(['used_hours','pending_hours']));
    }
};
