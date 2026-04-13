<?php
namespace Database\Factories;
use App\Models\LeaveAllocation;
use App\Models\Employee;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;
class LeaveAllocationFactory extends Factory {
    protected $model = LeaveAllocation::class;
    public function definition(): array {
        $allocated = $this->faker->numberBetween(10, 30);
        $used      = $this->faker->numberBetween(0, $allocated);
        return [
            'employee_id'        => Employee::factory(),
            'leave_type_id'      => LeaveType::factory(),
            'year'               => now()->year,
            'allocated_days'     => $allocated,
            'annual_entitlement' => $allocated,
            'used_days'          => $used,
            'pending_days'       => 0,
            'remaining_days'     => $allocated - $used,
        ];
    }
}
