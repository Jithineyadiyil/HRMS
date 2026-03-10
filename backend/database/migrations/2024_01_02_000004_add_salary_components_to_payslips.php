<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            // Earnings
            if (!Schema::hasColumn('payslips', 'housing_allowance')) {
                $table->decimal('housing_allowance', 12, 2)->default(0)->after('basic_salary')
                      ->comment('25% of basic salary');
            }
            if (!Schema::hasColumn('payslips', 'transport_allowance')) {
                $table->decimal('transport_allowance', 12, 2)->default(0)->after('housing_allowance')
                      ->comment('Fixed monthly transport allowance');
            }
            if (!Schema::hasColumn('payslips', 'other_allowances')) {
                $table->decimal('other_allowances', 12, 2)->default(0)->after('transport_allowance')
                      ->comment('Any other allowances / bonuses');
            }

            // Deductions
            if (!Schema::hasColumn('payslips', 'gosi_employee')) {
                $table->decimal('gosi_employee', 12, 2)->default(0)->after('total_earnings')
                      ->comment('GOSI employee share: 9% of basic (Saudi nationals only)');
            }
            if (!Schema::hasColumn('payslips', 'gosi_employer')) {
                $table->decimal('gosi_employer', 12, 2)->default(0)->after('gosi_employee')
                      ->comment('GOSI employer share: 11.75% of basic (cost, not deducted from employee)');
            }
            if (!Schema::hasColumn('payslips', 'other_deductions')) {
                $table->decimal('other_deductions', 12, 2)->default(0)->after('gosi_employer')
                      ->comment('Loans, penalties, etc.');
            }

            // Meta
            if (!Schema::hasColumn('payslips', 'is_saudi')) {
                $table->boolean('is_saudi')->default(false)->after('other_deductions')
                      ->comment('Whether GOSI applies');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropColumn([
                'housing_allowance','transport_allowance','other_allowances',
                'gosi_employee','gosi_employer','other_deductions','is_saudi',
            ]);
        });
    }
};
