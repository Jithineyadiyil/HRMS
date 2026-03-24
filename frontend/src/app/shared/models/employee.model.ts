/**
 * @fileoverview Shared TypeScript interfaces for the HRMS domain.
 *
 * Every component, service, and NgRx store slice must use these
 * interfaces instead of `any`. This file is the single source of truth
 * for the wire format returned by the Laravel API Resources.
 *
 * @module shared/models/employee.model
 */

// ── Lookup types ──────────────────────────────────────────────────────────────

export type EmploymentType = 'full_time' | 'part_time' | 'contract' | 'intern';
export type EmployeeStatus = 'active' | 'inactive' | 'terminated' | 'on_leave' | 'probation';
export type Gender        = 'male' | 'female' | 'other';
export type MaritalStatus = 'single' | 'married' | 'divorced' | 'widowed';

// ── Embedded lookup objects ───────────────────────────────────────────────────

export interface DepartmentRef {
  id:   number;
  name: string;
}

export interface DesignationRef {
  id:    number;
  title: string;
}

export interface ManagerRef {
  id:        number;
  full_name: string;
}

export interface LeaveAllocationSummary {
  leave_type:     string;
  allocated_days: number;
  used_days:      number;
  remaining_days: number;
  pending_days:   number;
  year:           number;
}

// ── Main Employee interface ───────────────────────────────────────────────────

/**
 * Matches the shape returned by `EmployeeResource::toArray()`.
 * Sensitive fields (salary, bank_account, national_id) are `null`
 * unless the authenticated user has the finance/hr role.
 */
export interface Employee {
  id:                   number;
  employee_code:        string;
  prefix:               string | null;
  first_name:           string;
  last_name:            string;
  full_name:            string;
  arabic_name:          string | null;
  email:                string;
  phone:                string | null;
  work_phone:           string | null;
  extension:            string | null;
  avatar_url:           string | null;

  // Personal
  dob:                  string | null;
  gender:               Gender | null;
  marital_status:       MaritalStatus | null;
  nationality:          string | null;
  address:              string | null;
  city:                 string | null;
  country:              string | null;

  // Employment
  department_id:        number | null;
  designation_id:       number | null;
  manager_id:           number | null;
  employment_type:      EmploymentType;
  mode_of_employment:   string | null;
  role:                 string | null;
  status:               EmployeeStatus;
  hire_date:            string | null;
  confirmation_date:    string | null;
  termination_date:     string | null;
  probation_period:     number | null;
  years_of_experience:  number | null;

  // Financial (null unless caller has hr/finance role)
  salary:               string | null;
  bank_name:            string | null;
  bank_account:         string | null;
  national_id:          string | null;

  // Emergency
  emergency_contact_name:     string | null;
  emergency_contact_phone:    string | null;
  emergency_contact_relation: string | null;

  notes:                string | null;

  // Conditionally loaded relations
  department?:          DepartmentRef;
  designation?:         DesignationRef;
  manager?:             ManagerRef | null;
  leave_allocations?:   LeaveAllocationSummary[];

  created_at:           string | null;
  updated_at:           string | null;
}

// ── Pagination wrapper ────────────────────────────────────────────────────────

export interface PaginatedResponse<T> {
  data:          T[];
  current_page:  number;
  last_page:     number;
  per_page:      number;
  total:         number;
  from:          number | null;
  to:            number | null;
}

// ── Filter / params ───────────────────────────────────────────────────────────

export interface EmployeeFilters {
  search?:          string;
  status?:          EmployeeStatus | '';
  department_id?:   number | '';
  employment_type?: EmploymentType | '';
  sort_by?:         string;
  sort_dir?:        'asc' | 'desc';
  page?:            number;
  per_page?:        number;
}

// ── Create / Update DTOs ──────────────────────────────────────────────────────

export type CreateEmployeeDto = Omit<Employee,
  'id' | 'employee_code' | 'full_name' | 'avatar_url' | 'department' | 'designation' | 'manager' | 'leave_allocations' | 'created_at' | 'updated_at'
>;

export type UpdateEmployeeDto = Partial<CreateEmployeeDto>;
