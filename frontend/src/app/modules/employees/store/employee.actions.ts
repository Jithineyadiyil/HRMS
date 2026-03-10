// ── actions ───────────────────────────────────────────────────────────────────
import { createAction, props } from '@ngrx/store';

export const loadEmployees        = createAction('[Employees] Load',         props<{ params?: any }>());
export const loadEmployeesSuccess = createAction('[Employees] Load Success', props<{ data: any }>());
export const loadEmployeesFailure = createAction('[Employees] Load Failure', props<{ error: any }>());
export const loadEmployee         = createAction('[Employees] Load One',     props<{ id: number }>());
export const loadEmployeeSuccess  = createAction('[Employees] Load One Success', props<{ employee: any }>());
export const createEmployee       = createAction('[Employees] Create',       props<{ data: any }>());
export const createEmployeeSuccess= createAction('[Employees] Create Success', props<{ employee: any }>());
export const updateEmployee       = createAction('[Employees] Update',       props<{ id: number; data: any }>());
export const updateEmployeeSuccess= createAction('[Employees] Update Success', props<{ employee: any }>());
export const deleteEmployee       = createAction('[Employees] Delete',       props<{ id: number }>());
export const deleteEmployeeSuccess= createAction('[Employees] Delete Success', props<{ id: number }>());
export const setFilter            = createAction('[Employees] Set Filter',   props<{ filters: any }>());
