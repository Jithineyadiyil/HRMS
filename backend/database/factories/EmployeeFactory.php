<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        static $seq = 1;
        return [
            'user_id'         => User::factory(),
            'employee_code'   => 'EMP' . str_pad($seq++, 4, '0', STR_PAD_LEFT),
            'first_name'      => $this->faker->firstName(),
            'last_name'       => $this->faker->lastName(),
            'email'           => $this->faker->unique()->safeEmail(),
            'hire_date'       => $this->faker->dateTimeBetween('-3 years', '-1 month')->format('Y-m-d'),
            'employment_type' => 'full_time',
            'status'          => 'active',
            'salary'          => $this->faker->numberBetween(5000, 25000),
        ];
    }
}
