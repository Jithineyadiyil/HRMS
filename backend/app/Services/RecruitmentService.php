<?php
namespace App\Services;

use App\Models\JobApplication;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

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

    /**
     * Convert a hired applicant into a full Employee record.
     * Returns employee + temp password + onboarding tasks created.
     */
    public function hireApplicant(JobApplication $app, array $data): array
    {
        $nameParts = explode(' ', $app->applicant_name, 2);
        $firstName = $nameParts[0];
        $lastName  = $nameParts[1] ?? '-';
        $tempPassword = 'Password@123';

        // Find or create User account
        $isNewUser = false;
        $user = User::where('email', $app->applicant_email)->first();
        if ($user) {
            if (!$user->hasRole('employee')) {
                $user->assignRole('employee');
            }
        } else {
            $isNewUser = true;
            $user = User::create([
                'name'     => $app->applicant_name,
                'email'    => $app->applicant_email,
                'password' => Hash::make($tempPassword),
            ]);
            $user->assignRole('employee');
        }

        // Prevent duplicate employee records
        $existingEmployee = Employee::where('user_id', $user->id)->first();
        if ($existingEmployee) {
            return ['employee' => $existingEmployee, 'is_new' => false, 'temp_password' => null];
        }

        $empCode = $this->generateEmployeeCode();

        $employee = Employee::create([
            'user_id'          => $user->id,
            'first_name'       => $firstName,
            'last_name'        => $lastName,
            'email'            => $app->applicant_email,
            'phone'            => $app->applicant_phone,
            'hire_date'        => $data['hire_date']        ?? now()->toDateString(),
            'employment_type'  => $data['employment_type']  ?? $app->jobPosting?->employment_type ?? 'full_time',
            'salary'           => $data['salary']           ?? 0,
            'department_id'    => $data['department_id']    ?? $app->jobPosting?->department_id,
            'designation_id'   => $data['designation_id']   ?? $app->jobPosting?->designation_id,
            'manager_id'       => $data['manager_id']       ?? null,
            'probation_period' => $data['probation_period'] ?? 90,
            'status'           => 'probation',
            'employee_code'    => $empCode,
        ]);

        $this->createDefaultLeaveAllocations($employee);
        $onboardingTasks = $this->createOnboardingTasks($employee, $data);

        return [
            'employee'         => $employee->load('department','designation'),
            'employee_code'    => $empCode,
            'temp_password'    => $isNewUser ? $tempPassword : null,
            'is_new'           => $isNewUser,
            'onboarding_tasks' => count($onboardingTasks),
            'login_email'      => $app->applicant_email,
        ];
    }

    /**
     * Create standard onboarding tasks for a new employee.
     * Customise the list as needed for your organisation.
     */
    private function createOnboardingTasks(Employee $employee, array $data): array
    {
        $hireDate = \Carbon\Carbon::parse($data['hire_date'] ?? now());
        $tasks = [];

        $defaultTasks = [
            ['title' => 'Submit copy of National ID / Iqama',         'category' => 'hr_documents', 'days' => 1],
            ['title' => 'Submit educational certificates',            'category' => 'hr_documents', 'days' => 3],
            ['title' => 'Submit bank account details for payroll',    'category' => 'hr_documents', 'days' => 3],
            ['title' => 'Sign employment contract',                   'category' => 'hr_documents', 'days' => 1],
            ['title' => 'Complete HR policy acknowledgement form',    'category' => 'hr_documents', 'days' => 7],
            ['title' => 'IT: Set up workstation & email',             'category' => 'it_setup',     'days' => 1],
            ['title' => 'IT: Configure VPN & system access',          'category' => 'it_setup',     'days' => 2],
            ['title' => 'Complete system orientation / training',     'category' => 'training',     'days' => 14],
            ['title' => 'Meet department manager & team introduction','category' => 'introduction', 'days' => 1],
            ['title' => 'Complete probation review at 90 days',       'category' => 'probation',    'days' => (int)($data['probation_period'] ?? 90)],
        ];

        foreach ($defaultTasks as $i => $t) {
            $task = \App\Models\OnboardingTask::create([
                'employee_id' => $employee->id,
                'title'       => $t['title'],
                'category'    => $t['category'],
                'status'      => 'pending',
                'due_date'    => $hireDate->copy()->addDays($t['days'])->toDateString(),
                'sort_order'  => $i,
            ]);
            $tasks[] = $task;
        }

        // Add custom tasks from request if any
        if (!empty($data['custom_tasks'])) {
            foreach ($data['custom_tasks'] as $j => $ct) {
                $tasks[] = \App\Models\OnboardingTask::create([
                    'employee_id' => $employee->id,
                    'title'       => $ct,
                    'category'    => 'hr_documents',
                    'status'      => 'pending',
                    'sort_order'  => count($defaultTasks) + $j,
                ]);
            }
        }

        return $tasks;
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
