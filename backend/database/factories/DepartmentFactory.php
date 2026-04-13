<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        static $seq = 1;
        return [
            'name'      => $this->faker->unique()->word() . ' Department',
            'code'      => 'DEPT' . str_pad($seq++, 3, '0', STR_PAD_LEFT),
            'is_active' => true,
        ];
    }
}
