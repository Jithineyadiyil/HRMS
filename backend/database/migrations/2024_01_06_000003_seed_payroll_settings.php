<?php
use Illuminate\Database\Migrations\Migration;
use App\Models\PayrollSetting;

/**
 * Idempotent seed of payroll_settings — runs as a migration so settings
 * are created even if db:seed was never called after deployment.
 */
class SeedPayrollSettings extends Migration
{
    public function up()
    {
        $rows = [
            // ── Deductions ───────────────────────────────────────────────
            ['key'=>'daily_rate_basis',          'value'=>'monthly', 'type'=>'string',  'label'=>'Daily Rate Calculation Basis',           'group'=>'deductions', 'description'=>'monthly = salary ÷ working days in period | fixed = salary ÷ 26 | annual = salary × 12 ÷ 260'],
            ['key'=>'working_days_per_month',    'value'=>'26',      'type'=>'integer', 'label'=>'Working Days Per Month (Fixed Basis)',   'group'=>'deductions', 'description'=>'Used when daily_rate_basis = "fixed". Saudi standard is 26.'],
            // ── Leave deductions ─────────────────────────────────────────
            ['key'=>'deduct_unpaid_leave',       'value'=>'1',       'type'=>'boolean', 'label'=>'Deduct Unpaid Leave from Salary',        'group'=>'leave',      'description'=>'When ON, approved leaves of types marked "Unpaid" are deducted from basic salary at the daily rate.'],
            ['key'=>'deduct_absences',           'value'=>'1',       'type'=>'boolean', 'label'=>'Deduct Unrecorded Absences',             'group'=>'leave',      'description'=>'When ON, days marked Absent in attendance with no approved leave request are deducted from basic salary.'],
            ['key'=>'deduct_allowances_on_leave','value'=>'0',       'type'=>'boolean', 'label'=>'Deduct Allowances on Unpaid Leave',      'group'=>'leave',      'description'=>'When ON, housing and transport allowances are also pro-rated for unpaid leave days.'],
            // ── GOSI ──────────────────────────────────────────────────────
            ['key'=>'gosi_apply_saudi_only',     'value'=>'1',       'type'=>'boolean', 'label'=>'Apply GOSI to Saudi Nationals Only',     'group'=>'gosi',       'description'=>'When ON, GOSI deductions only apply to Saudi national employees.'],
            ['key'=>'gosi_employee_rate',        'value'=>'0.09',    'type'=>'decimal', 'label'=>'GOSI Employee Contribution Rate',        'group'=>'gosi',       'description'=>'Employee-side GOSI rate (default 9% = 0.09).'],
            ['key'=>'gosi_employer_rate',        'value'=>'0.1175',  'type'=>'decimal', 'label'=>'GOSI Employer Contribution Rate',        'group'=>'gosi',       'description'=>'Employer-side GOSI rate (default 11.75% = 0.1175).'],
            // ── Overtime ──────────────────────────────────────────────────
            ['key'=>'overtime_rate',             'value'=>'1.5',     'type'=>'decimal', 'label'=>'Overtime Rate Multiplier',              'group'=>'overtime',   'description'=>'Daily rate multiplier for overtime (e.g. 1.5 = 150% of daily rate).'],
        ];

        foreach ($rows as $row) {
            $data = $row; unset($data['key']);
            PayrollSetting::firstOrCreate(['key' => $row['key']], $data);
        }
    }

    public function down() {}
}
