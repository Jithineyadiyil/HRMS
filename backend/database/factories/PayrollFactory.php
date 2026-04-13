<?php
namespace Database\Factories;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
class PayrollFactory extends Factory {
    protected $model = Payroll::class;
    public function definition(): array {
        return [
            'cycle_name'       => 'Monthly - ' . now()->format('M Y'),
            'month'            => now()->format('Y-m'),
            'period_start'     => now()->startOfMonth()->toDateString(),
            'period_end'       => now()->endOfMonth()->toDateString(),
            'status'           => 'pending_approval',
            'total_gross'      => $this->faker->numberBetween(50000, 200000),
            'total_deductions' => $this->faker->numberBetween(5000, 20000),
            'total_net'        => $this->faker->numberBetween(40000, 180000),
            'created_by'       => User::factory(),
        ];
    }
}
