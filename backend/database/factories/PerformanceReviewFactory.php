<?php
namespace Database\Factories;
use App\Models\PerformanceReview;
use App\Models\PerformanceCycle;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;
class PerformanceReviewFactory extends Factory {
    protected $model = PerformanceReview::class;
    public function definition(): array {
        // FIX: removed 'reference_no' — the actual performance_reviews table
        // (created by 2024_01_01_000007) does not have this column.
        // Status enum in DB: pending, self_submitted, manager_reviewed, hr_calibrated, finalized
        return [
            'cycle_id'    => PerformanceCycle::factory(),
            'employee_id' => Employee::factory(),
            'status'      => 'pending',
        ];
    }
}
