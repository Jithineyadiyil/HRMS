<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mark which leave type is the annual leave type
        Schema::table('leave_types', function (Blueprint $table) {
            $table->boolean('is_annual')->default(false)->after('is_hourly')
                  ->comment('Annual leave — shows exit re-entry & ticket options');
        });

        // Add annual leave-specific fields to leave_requests
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->boolean('requires_exit_reentry')->default(false)->after('half_day_period')
                  ->comment('Employee needs exit re-entry visa');
            $table->boolean('requires_ticket')->default(false)->after('requires_exit_reentry')
                  ->comment('Employee needs air ticket');
            $table->string('destination_country', 100)->nullable()->after('requires_ticket')
                  ->comment('Travel destination for annual leave');
        });

        // Auto-mark existing Annual Leave type if present
        \DB::table('leave_types')
            ->where('name', 'LIKE', '%annual%')
            ->orWhere('code', 'LIKE', '%annual%')
            ->orWhere('code', 'AL')
            ->update(['is_annual' => true]);
    }

    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn('is_annual');
        });
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn(['requires_exit_reentry', 'requires_ticket', 'destination_country']);
        });
    }
};
