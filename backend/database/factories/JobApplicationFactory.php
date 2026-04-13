<?php
namespace Database\Factories;
use App\Models\JobApplication;
use App\Models\JobPosting;
use Illuminate\Database\Eloquent\Factories\Factory;
class JobApplicationFactory extends Factory {
    protected $model = JobApplication::class;
    public function definition(): array {
        return [
            'job_posting_id'  => JobPosting::factory(),
            'applicant_name'  => $this->faker->name(),
            'applicant_email' => $this->faker->unique()->safeEmail(),
            'stage'           => 'applied',
        ];
    }
}
