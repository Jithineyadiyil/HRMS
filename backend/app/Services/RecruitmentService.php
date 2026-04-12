<?php
namespace App\Services;

use App\Models\JobApplication;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RecruitmentService
{
    public function __construct() {}

    public function sendInterviewInvite($interview): void
    {
        // Mail::to($interview->application->applicant_email)->send(new InterviewInviteMail($interview));
    }

    public function generateOfferLetter(JobApplication $app, array $data): array
    {
        // PDF generation placeholder - implement view when ready
        $path = "recruitment/offers/{$app->id}_offer.pdf";
        return ['pdf_path' => $path, 'salary' => $data['offered_salary'] ?? null];
    }

    public function hireApplicant(JobApplication $app, array $data): Employee
    {
        $nameParts = explode(' ', $app->applicant_name, 2);
        $firstName = $nameParts[0];
        $lastName  = $nameParts[1] ?? '-';

        // Find or create user — avoids duplicate email constraint violation
        $user = User::where('email', $app->applicant_email)->first();

        if ($user) {
            // User already exists (e.g. previously rejected, re-applied, or internal transfer)
            // Just ensure they have the employee role
            if (!$user->hasRole('employee')) {
                $user->assignRole('employee');
            }
        } else {
            $user = User::create([
                'name'     => $app->applicant_name,
                'email'    => $app->applicant_email,
                'password' => Hash::make('Password@123'),
            ]);
            $user->assignRole('employee');
        }

        // Prevent creating a duplicate employee record for the same user
        $existingEmployee = Employee::where('user_id', $user->id)->first();
        if ($existingEmployee) {
            return $existingEmployee;
        }

        $employee = Employee::create([
            'user_id'         => $user->id,
            'first_name'      => $firstName,
            'last_name'       => $lastName,
            'email'           => $app->applicant_email,
            'phone'           => $app->applicant_phone,
            'hire_date'       => $data['hire_date'] ?? now(),
            'employment_type' => $app->jobPosting->employment_type,
            'salary'          => $data['salary'] ?? 0,
            'department_id'   => $app->jobPosting->department_id,
            'designation_id'  => $app->jobPosting->designation_id,
            'employee_code'   => $this->generateEmployeeCode(),
        ]);

        $this->createDefaultLeaveAllocations($employee);

        return $employee;
    }

    /** Generate next employee code e.g. EMP0042 */
    private function generateEmployeeCode(): string
    {
        $last = \App\Models\Employee::withTrashed()->orderBy('id', 'desc')->first();
        $next = $last ? intval(substr($last->employee_code, 3)) + 1 : 1;
        return 'EMP' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
    }

    /** Create annual leave allocations for all active leave types */
    private function createDefaultLeaveAllocations(\App\Models\Employee $employee): void
    {
        $year  = now()->year;
        $types = \App\Models\LeaveType::where('is_active', true)->get();
        foreach ($types as $type) {
            \App\Models\LeaveAllocation::firstOrCreate(
                ['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => $year],
                [
                    'allocated_days' => $type->days_allowed,
                    'used_days'      => 0,
                    'pending_days'   => 0,
                    'remaining_days' => $type->days_allowed,
                ]
            );
        }
    }
}
