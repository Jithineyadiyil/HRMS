<?php

declare(strict_types=1);

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates incoming data for the POST /api/v1/employees endpoint.
 *
 * Centralising validation here keeps the controller thin and makes rules
 * individually testable without booting a full HTTP stack.
 */
class StoreEmployeeRequest extends FormRequest
{
    /**
     * Only HR managers and super admins may create employees.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['super_admin', 'hr_manager', 'hr_staff']) ?? false;
    }

    /**
     * Validation rules.
     *
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            // Personal
            'prefix'              => ['nullable', 'string', 'max:10'],
            'first_name'          => ['required', 'string', 'max:100'],
            'last_name'           => ['required', 'string', 'max:100'],
            'arabic_name'         => ['nullable', 'string', 'max:200'],
            'email'               => ['required', 'email:rfc,dns', 'unique:employees,email'],
            'phone'               => ['nullable', 'string', 'max:30'],
            'work_phone'          => ['nullable', 'string', 'max:30'],
            'extension'           => ['nullable', 'string', 'max:10'],
            'dob'                 => ['nullable', 'date', 'before:-18 years'],
            'gender'              => ['nullable', 'in:male,female,other'],
            'marital_status'      => ['nullable', 'in:single,married,divorced,widowed'],
            'nationality'         => ['nullable', 'string', 'max:80'],
            'national_id'         => ['nullable', 'string', 'max:50'],
            'address'             => ['nullable', 'string', 'max:255'],
            'city'                => ['nullable', 'string', 'max:100'],
            'country'             => ['nullable', 'string', 'max:80'],

            // Employment
            'department_id'       => ['nullable', 'exists:departments,id'],
            'designation_id'      => ['nullable', 'exists:designations,id'],
            'manager_id'          => ['nullable', 'exists:employees,id'],
            'employment_type'     => ['required', 'in:full_time,part_time,contract,intern'],
            'mode_of_employment'  => ['nullable', 'in:direct,agency,secondment'],
            'role'                => ['nullable', 'string', 'max:50'],
            'status'              => ['sometimes', 'in:active,inactive,probation,on_leave,terminated'],
            'hire_date'           => ['required', 'date'],
            'confirmation_date'   => ['nullable', 'date', 'after_or_equal:hire_date'],
            'termination_date'    => ['nullable', 'date', 'after_or_equal:hire_date'],
            'probation_period'    => ['nullable', 'integer', 'min:0', 'max:365'],
            'years_of_experience' => ['nullable', 'integer', 'min:0', 'max:60'],

            // Financial
            'salary'              => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'bank_name'           => ['nullable', 'string', 'max:100'],
            'bank_account'        => ['nullable', 'string', 'max:50'],

            // Emergency
            'emergency_contact_name'     => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone'    => ['nullable', 'string', 'max:30'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:50'],

            'notes'               => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Human-readable attribute labels used in error messages.
     *
     * @return array<string,string>
     */
    public function attributes(): array
    {
        return [
            'first_name'      => 'first name',
            'last_name'       => 'last name',
            'department_id'   => 'department',
            'designation_id'  => 'designation',
            'manager_id'      => 'manager',
            'employment_type' => 'employment type',
            'hire_date'       => 'hire date',
        ];
    }
}
