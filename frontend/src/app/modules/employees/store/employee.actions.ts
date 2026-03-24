/**
 * @fileoverview NgRx actions for the Employee feature.
 *
 * All actions are strictly typed using the {@see Employee} interface.
 * No `any` types are permitted in this file.
 *
 * @module employees/store/employee.actions
 */

import { createAction, props } from '@ngrx/store';
import {
  Employee,
  EmployeeFilters,
  PaginatedResponse,
} from '../../../shared/models/employee.model';

// ── Load list ────────────────────────────────────────────────────────────────

/** Dispatch to trigger a filtered/paginated employee list fetch. */
export const loadEmployees = createAction(
  '[Employees] Load',
  props<{ params?: EmployeeFilters }>()
);

export const loadEmployeesSuccess = createAction(
  '[Employees] Load Success',
  props<{ data: PaginatedResponse<Employee> }>()
);

export const loadEmployeesFailure = createAction(
  '[Employees] Load Failure',
  props<{ error: string }>()
);

// ── Load single ──────────────────────────────────────────────────────────────

export const loadEmployee = createAction(
  '[Employees] Load One',
  props<{ id: number }>()
);

export const loadEmployeeSuccess = createAction(
  '[Employees] Load One Success',
  props<{ employee: Employee }>()
);

export const loadEmployeeFailure = createAction(
  '[Employees] Load One Failure',
  props<{ error: string }>()
);

// ── Create ───────────────────────────────────────────────────────────────────

export const createEmployee = createAction(
  '[Employees] Create',
  props<{ data: Partial<Employee> }>()
);

export const createEmployeeSuccess = createAction(
  '[Employees] Create Success',
  props<{ employee: Employee }>()
);

export const createEmployeeFailure = createAction(
  '[Employees] Create Failure',
  props<{ error: string }>()
);

// ── Update ───────────────────────────────────────────────────────────────────

export const updateEmployee = createAction(
  '[Employees] Update',
  props<{ id: number; data: Partial<Employee> }>()
);

export const updateEmployeeSuccess = createAction(
  '[Employees] Update Success',
  props<{ employee: Employee }>()
);

export const updateEmployeeFailure = createAction(
  '[Employees] Update Failure',
  props<{ error: string }>()
);

// ── Delete / Terminate ───────────────────────────────────────────────────────

export const deleteEmployee = createAction(
  '[Employees] Delete',
  props<{ id: number }>()
);

export const deleteEmployeeSuccess = createAction(
  '[Employees] Delete Success',
  props<{ id: number }>()
);

export const deleteEmployeeFailure = createAction(
  '[Employees] Delete Failure',
  props<{ error: string }>()
);

// ── Filters ──────────────────────────────────────────────────────────────────

export const setFilter = createAction(
  '[Employees] Set Filter',
  props<{ filters: EmployeeFilters }>()
);
