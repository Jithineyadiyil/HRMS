<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSalaryComponentsToEmployees extends Migration
{
    public function up()
    {
        Schema::table('employees', function (Blueprint $table) {
            // Individual salary breakdown — if set, overrides PayrollService defaults
            if (!Schema::hasColumn('employees', 'housing_allowance')) {
                $table->decimal('housing_allowance', 12, 2)->nullable()
                      ->after('salary')
                      ->comment('Monthly housing allowance (SAR). If null, 25% of basic is used.');
            }
            if (!Schema::hasColumn('employees', 'transport_allowance')) {
                $table->decimal('transport_allowance', 12, 2)->nullable()
                      ->after('housing_allowance')
                      ->comment('Monthly transport allowance (SAR). If null, SAR 400 default is used.');
            }
            if (!Schema::hasColumn('employees', 'other_allowances')) {
                $table->decimal('other_allowances', 12, 2)->nullable()->default(0)
                      ->after('transport_allowance')
                      ->comment('Other fixed monthly allowances (SAR).');
            }
            if (!Schema::hasColumn('employees', 'mobile_allowance')) {
                $table->decimal('mobile_allowance', 12, 2)->nullable()->default(0)
                      ->after('other_allowances')
                      ->comment('Monthly mobile/phone allowance (SAR).');
            }
            if (!Schema::hasColumn('employees', 'food_allowance')) {
                $table->decimal('food_allowance', 12, 2)->nullable()->default(0)
                      ->after('mobile_allowance')
                      ->comment('Monthly food/meal allowance (SAR).');
            }
        });
    }

    public function down()
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'housing_allowance', 'transport_allowance',
                'other_allowances', 'mobile_allowance', 'food_allowance',
            ]);
        });
    }
}
