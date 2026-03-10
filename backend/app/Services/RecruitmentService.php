<?php
namespace App\Services;

use App\Models\JobApplication;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class RecruitmentService
{
    protected EmployeeService $employeeService;

    public function __construct(EmployeeService $employeeService)
    {
        $this->employeeService = $employeeService;
    }

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

        $user = User::create([
            'name'     => $app->applicant_name,
            'email'    => $app->applicant_email,
            'password' => Hash::make('Password@123'),
        ]);
        $user->assignRole('employee');

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
            'employee_code'   => $this->employeeService->generateCode(),
        ]);

        $this->employeeService->createDefaultLeaveAllocations($employee);
        $this->employeeService->createOnboardingTasks($employee);

        return $employee;
    }
}
