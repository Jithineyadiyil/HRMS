<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_allocations', function (Blueprint $table) {
            if (!Schema::hasColumn('leave_allocations', 'accrual_year_start')) {
                $table->date('accrual_year_start')->nullable()->after('remaining_days')
                      ->comment('Anniversary date that started the current accrual year');
            }
            if (!Schema::hasColumn('leave_allocations', 'last_accrual_date')) {
                $table->date('last_accrual_date')->nullable()->after('accrual_year_start')
                      ->comment('Date this record was last updated by the accrual command');
            }
            if (!Schema::hasColumn('leave_allocations', 'annual_entitlement')) {
                $table->unsignedTinyInteger('annual_entitlement')->default(22)->after('last_accrual_date')
                      ->comment('22 days (<5 yrs service) or 30 days (>=5 yrs service)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leave_allocations', function (Blueprint $table) {
            $table->dropColumn(['accrual_year_start', 'last_accrual_date', 'annual_entitlement']);
        });
    }
};
