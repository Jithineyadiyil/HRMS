<?php
namespace Database\Factories;
use App\Models\LoanInstallment;
use App\Models\Loan;
use Illuminate\Database\Eloquent\Factories\Factory;
class LoanInstallmentFactory extends Factory {
    protected $model = LoanInstallment::class;
    public function definition(): array {
        static $seq = 1;
        return [
            'loan_id'        => Loan::factory(),
            'installment_no' => $seq++,
            'due_date'       => now()->addMonth()->toDateString(),
            'amount'         => $this->faker->numberBetween(500, 3000),
            'status'         => 'pending',
        ];
    }
}
