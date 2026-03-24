<?php

declare(strict_types=1);

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates incoming data for the PUT /api/v1/employees/{id} endpoint.
 *
 * All rules use 'sometimes' so partial updates are supported — only
 * fields present in the request body are validated and applied.
 * The email uniqueness rule ignores the current employee record.
 */
class UpdateEmployeeRequest extends FormRequest
{
    /**
     * Authorise HR staff and the employee themselves for profile updates.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $user       = $this->user();
        $employeeId = (int) $this->route('id');

        // HR roles may update any employee
        if ($user?->hasAnyRole(['super_admin', 'hr_manager', 'hr_staff'])) {
            return true;
        }

        // Employees may update their own profile
        return $user?->employee?->id === $employeeId;
    }

    /**
     * Validation rules.
     *
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        $employeeId = (int) $this->route('id');

        return [
            'prefix'              => ['sometimes', 'nullable', 'string', 'max:10'],
            'first_name'          => ['sometimes', 'required', 'string', 'max:100'],
            'last_name'           => ['sometimes', 'required', 'string', 'max:100'],
            'arabic_name'         => ['sometimes', 'nullable', 'string', 'max:200'],
            'email'               => ['sometimes', 'required', 'email:rfc,dns', "unique:employees,email,{$employeeId}"],
            'phone'               => ['sometimes', 'nullable', 'string', 'max:30'],
            'work_phone'          => ['sometimes', 'nullable', 'string', 'max:30'],
            'extension'           => ['sometimes', 'nullable', 'string', 'max:10'],
            'dob'                 => ['sometimes', 'nullable', 'date', 'before:-18 years'],
            'gender'              => ['sometimes', 'nullable', 'in:male,female,other'],
            'marital_status'      => ['sometimes', 'nullable', 'in:single,married,divorced,widowed'],
            'nationality'         => ['sometimes', 'nullable', 'string', 'max:80'],
            'national_id'         => ['sometimes', 'nullable', 'string', 'max:50'],
            'address'             => ['sometimes', 'nullable', 'string', 'max:255'],
            'city'                => ['sometimes', 'nullable', 'string', 'max:100'],
            'country'             => ['sometimes', 'nullable', 'string', 'max:80'],

            'department_id'       => ['sometimes', 'nullable', 'exists:departments,id'],
            'designation_id'      => ['sometimes', 'nullable', 'exists:designations,id'],
            'manager_id'          => ['sometimes', 'nullable', 'exists:employees,id'],
            'employment_type'     => ['sometimes', 'required', 'in:full_time,part_time,contract,intern'],
            'mode_of_employment'  => ['sometimes', 'nullable', 'in:direct,agency,secondment'],
            'status'              => ['sometimes', 'in:active,inactive,probation,on_leave,terminated'],
            'hire_date'           => ['sometimes', 'required', 'date'],
            'confirmation_date'   => ['sometimes', 'nullable', 'date'],
            'termination_date'    => ['sometimes', 'nullable', 'date'],
            'probation_period'    => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
            'years_of_experience' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:60'],

            'salary'              => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999999.99'],
            'bank_name'           => ['sometimes', 'nullable', 'string', 'max:100'],
            'bank_account'        => ['sometimes', 'nullable', 'string', 'max:50'],

            'emergency_contact_name'     => ['sometimes', 'nullable', 'string', 'max:100'],
            'emergency_contact_phone'    => ['sometimes', 'nullable', 'string', 'max:30'],
            'emergency_contact_relation' => ['sometimes', 'nullable', 'string', 'max:50'],

            'notes'               => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
