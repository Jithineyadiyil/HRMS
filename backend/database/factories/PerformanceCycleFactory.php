<?php
namespace Database\Factories;
use App\Models\PerformanceCycle;
use Illuminate\Database\Eloquent\Factories\Factory;
class PerformanceCycleFactory extends Factory {
    protected $model = PerformanceCycle::class;
    public function definition(): array {
        return [
            'name'       => 'Q' . $this->faker->numberBetween(1,4) . ' ' . now()->year . ' Review',
            'type'       => 'quarterly',
            'status'     => 'draft',
            'start_date' => now()->startOfQuarter()->toDateString(),
            'end_date'   => now()->endOfQuarter()->toDateString(),
        ];
    }
}
