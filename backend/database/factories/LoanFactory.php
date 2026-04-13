<?php
namespace Database\Factories;
use App\Models\Loan;
use App\Models\Employee;
use App\Models\LoanType;
use Illuminate\Database\Eloquent\Factories\Factory;
class LoanFactory extends Factory {
    protected $model = Loan::class;
    public function definition(): array {
        static $seq = 1;
        return [
            'reference'           => 'LOAN-' . now()->year . '-' . str_pad($seq++, 5, '0', STR_PAD_LEFT),
            'employee_id'         => Employee::factory(),
            'loan_type_id'        => LoanType::factory(),
            'requested_amount'    => $this->faker->numberBetween(1000, 20000),
            'installments'        => $this->faker->numberBetween(3, 12),
            'monthly_installment' => $this->faker->numberBetween(200, 2000),
            'purpose'             => $this->faker->sentence(10),
            'status'              => 'pending_manager',
        ];
    }
}
