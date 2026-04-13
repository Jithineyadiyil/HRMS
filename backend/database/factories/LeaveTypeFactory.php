<?php

namespace Database\Factories;

use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveTypeFactory extends Factory
{
    protected $model = LeaveType::class;

    public function definition(): array
    {
        static $seq = 1;
        return [
            'name'                  => $this->faker->word() . ' Leave',
            'code'                  => 'LT' . str_pad($seq++, 3, '0', STR_PAD_LEFT),
            'days_allowed'          => $this->faker->numberBetween(5, 30),
            'is_paid'               => true,
            'carry_forward'         => false,
            'requires_document'     => false,
            'is_active'             => true,
            'skip_manager_approval' => false,
        ];
    }
}
