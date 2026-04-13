<?php
namespace Database\Factories;
use App\Models\Payslip;
use App\Models\Payroll;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;
class PayslipFactory extends Factory {
    protected $model = Payslip::class;
    public function definition(): array {
        return [
            'payroll_id'       => Payroll::factory(),
            'employee_id'      => Employee::factory(),
            'basic_salary'     => 10000,
            'gross_salary'     => 12000,
            'total_earnings'   => 12000,
            'total_deductions' => 900,
            'net_salary'       => 11100,
            'working_days'     => 22,
            'absent_days'      => 0,
        ];
    }
}
