<?php
namespace Database\Factories;
use App\Models\LoanType;
use Illuminate\Database\Eloquent\Factories\Factory;
class LoanTypeFactory extends Factory {
    protected $model = LoanType::class;
    public function definition(): array {
        static $seq = 1;
        return [
            'name'             => $this->faker->word() . ' Loan',
            'code'             => 'LN' . str_pad($seq++, 3, '0', STR_PAD_LEFT),
            'max_amount'       => 50000,
            'max_installments' => 24,
            'interest_rate'    => 0,
            'is_active'        => true,
        ];
    }
}
