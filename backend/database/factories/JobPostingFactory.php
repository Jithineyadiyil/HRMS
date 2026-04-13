<?php
namespace Database\Factories;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
class JobPostingFactory extends Factory {
    protected $model = JobPosting::class;
    public function definition(): array {
        return [
            'title'           => $this->faker->jobTitle(),
            'employment_type' => 'full_time',
            'description'     => $this->faker->paragraph(),
            'status'          => 'open',
            'vacancies'       => $this->faker->numberBetween(1, 5),
            'created_by'      => User::factory(), // FIX: was hardcoded 1, causing FK violation
        ];
    }
}
