<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCarryForwardToLeaveAllocations extends Migration
{
    public function up()
    {
        Schema::table('leave_allocations', function (Blueprint $table) {
            if (!Schema::hasColumn('leave_allocations', 'carried_forward_days')) {
                $table->decimal('carried_forward_days', 5, 1)->default(0)
                      ->after('remaining_days')
                      ->comment('Days carried forward from previous year');
            }
        });
    }

    public function down()
    {
        Schema::table('leave_allocations', function (Blueprint $table) {
            $table->dropColumn('carried_forward_days');
        });
    }
}
