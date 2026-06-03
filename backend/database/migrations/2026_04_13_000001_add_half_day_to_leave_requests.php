<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            // Half day flag: deducts 0.5 days from the linked leave type balance
            $table->boolean('is_half_day')->default(false)->after('total_days');
            // Which half: 'morning' or 'afternoon'
            $table->enum('half_day_period', ['morning', 'afternoon'])->nullable()->after('is_half_day');
            // Manager approval stage tracking
            $table->enum('status', ['pending', 'manager_approved', 'approved', 'rejected', 'cancelled'])
                  ->default('pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn(['is_half_day', 'half_day_period']);
        });
    }
};
