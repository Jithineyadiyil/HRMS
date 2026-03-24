<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms an {@see \App\Models\Employee} model into a JSON-safe array.
 *
 * Sensitive fields (national_id, bank_account, bank_name) are excluded
 * unless the authenticated user holds the 'hr_manager' or 'super_admin' role.
 * This is the single place where the Employee wire format is defined —
 * controllers must never call response()->json($employee) directly.
 *
 * @mixin \App\Models\Employee
 */
class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request $request
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        $canViewSensitive = $request->user()?->hasAnyRole(['super_admin', 'hr_manager', 'finance_manager']);

        return [
            'id'                  => $this->id,
            'employee_code'       => $this->employee_code,
            'prefix'              => $this->prefix,
            'first_name'          => $this->first_name,
            'last_name'           => $this->last_name,
            'full_name'           => $this->full_name,
            'arabic_name'         => $this->arabic_name,
            'email'               => $this->email,
            'phone'               => $this->phone,
            'work_phone'          => $this->work_phone,
            'extension'           => $this->extension,
            'avatar_url'          => $this->avatar_url,

            // Personal
            'dob'                 => $this->dob?->toDateString(),
            'gender'              => $this->gender,
            'marital_status'      => $this->marital_status,
            'nationality'         => $this->nationality,
            'address'             => $this->address,
            'city'                => $this->city,
            'country'             => $this->country,

            // Employment
            'department_id'       => $this->department_id,
            'designation_id'      => $this->designation_id,
            'manager_id'          => $this->manager_id,
            'employment_type'     => $this->employment_type,
            'mode_of_employment'  => $this->mode_of_employment,
            'role'                => $this->role,
            'status'              => $this->status,
            'hire_date'           => $this->hire_date?->toDateString(),
            'confirmation_date'   => $this->confirmation_date?->toDateString(),
            'termination_date'    => $this->termination_date?->toDateString(),
            'probation_period'    => $this->probation_period,
            'years_of_experience' => $this->years_of_experience,

            // Financial — only visible to HR/Finance roles
            'salary'              => $canViewSensitive ? $this->salary : null,
            'bank_name'           => $canViewSensitive ? $this->bank_name : null,
            'bank_account'        => $canViewSensitive ? $this->bank_account : null,
            'national_id'         => $canViewSensitive ? $this->national_id : null,

            // Emergency contact
            'emergency_contact_name'     => $this->emergency_contact_name,
            'emergency_contact_phone'    => $this->emergency_contact_phone,
            'emergency_contact_relation' => $this->emergency_contact_relation,

            'notes'               => $this->notes,

            // Relations (conditionally loaded)
            'department'          => $this->whenLoaded('department', fn () => [
                'id'   => $this->department->id,
                'name' => $this->department->name,
            ]),
            'designation'         => $this->whenLoaded('designation', fn () => [
                'id'    => $this->designation->id,
                'title' => $this->designation->title,
            ]),
            'manager'             => $this->whenLoaded('manager', fn () => $this->manager ? [
                'id'        => $this->manager->id,
                'full_name' => $this->manager->full_name,
            ] : null),
            'leave_allocations'   => $this->whenLoaded(
                'leaveAllocations',
                fn () => $this->leaveAllocations->map(fn ($a) => [
                    'leave_type'     => $a->leaveType?->name,
                    'allocated_days' => $a->allocated_days,
                    'used_days'      => $a->used_days,
                    'remaining_days' => $a->remaining_days,
                    'pending_days'   => $a->pending_days,
                    'year'           => $a->year,
                ])
            ),

            'created_at'          => $this->created_at?->toIso8601String(),
            'updated_at'          => $this->updated_at?->toIso8601String(),
        ];
    }
}
