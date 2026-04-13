<?php
namespace Database\Factories;
use App\Models\LeaveRequest;
use App\Models\Employee;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;
class LeaveRequestFactory extends Factory {
    protected $model = LeaveRequest::class;
    public function definition(): array {
        $start = $this->faker->dateTimeBetween('+1 day', '+10 days');
        $end   = $this->faker->dateTimeBetween($start, '+15 days');
        return [
            'employee_id'   => Employee::factory(),
            'leave_type_id' => LeaveType::factory(),
            'start_date'    => $start->format('Y-m-d'),
            'end_date'      => $end->format('Y-m-d'),
            'total_days'    => $this->faker->numberBetween(1, 5),
            'reason'        => $this->faker->sentence(12),
            'status'        => 'pending',
        ];
    }
}
