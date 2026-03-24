/**
 * @fileoverview NgRx reducer and state for the Employee feature.
 *
 * Uses @ngrx/entity for O(1) lookups and normalised storage.
 * All state properties are explicitly typed — no `any`.
 *
 * @module employees/store/employee.reducer
 */

import { createEntityAdapter, EntityAdapter, EntityState } from '@ngrx/entity';
import { createReducer, on } from '@ngrx/store';
import { Employee, EmployeeFilters } from '../../../shared/models/employee.model';
import * as EmployeeActions from './employee.actions';

// ── State shape ──────────────────────────────────────────────────────────────

export interface Pagination {
  currentPage:  number;
  lastPage:     number;
  perPage:      number;
  total:        number;
  from:         number | null;
  to:           number | null;
}

export interface EmployeeState extends EntityState<Employee> {
  /** The currently viewed employee in the detail pane. */
  selectedEmployee: Employee | null;
  /** True while any HTTP request is in flight. */
  loading:          boolean;
  /** Pagination metadata from the last list response. */
  pagination:       Pagination | null;
  /** Active filter values used for the current list fetch. */
  filters:          EmployeeFilters;
  /** Last error message, or null when clean. */
  error:            string | null;
}

// ── Entity adapter ───────────────────────────────────────────────────────────

export const adapter: EntityAdapter<Employee> = createEntityAdapter<Employee>({
  selectId: (e: Employee) => e.id,
  sortComparer: false,
});

const initialState: EmployeeState = adapter.getInitialState({
  selectedEmployee: null,
  loading:          false,
  pagination:       null,
  filters:          {},
  error:            null,
});

// ── Reducer ──────────────────────────────────────────────────────────────────

export const employeeReducer = createReducer(
  initialState,

  // List
  on(EmployeeActions.loadEmployees,
    (state): EmployeeState => ({ ...state, loading: true, error: null })
  ),
  on(EmployeeActions.loadEmployeesSuccess,
    (state, { data }): EmployeeState => adapter.setAll(data.data, {
      ...state,
      loading: false,
      pagination: {
        currentPage: data.current_page,
        lastPage:    data.last_page,
        perPage:     data.per_page,
        total:       data.total,
        from:        data.from,
        to:          data.to,
      },
    })
  ),
  on(EmployeeActions.loadEmployeesFailure,
    (state, { error }): EmployeeState => ({ ...state, loading: false, error })
  ),

  // Single
  on(EmployeeActions.loadEmployee,
    (state): EmployeeState => ({ ...state, loading: true, error: null })
  ),
  on(EmployeeActions.loadEmployeeSuccess,
    (state, { employee }): EmployeeState => ({
      ...state,
      loading:          false,
      selectedEmployee: employee,
    })
  ),
  on(EmployeeActions.loadEmployeeFailure,
    (state, { error }): EmployeeState => ({ ...state, loading: false, error })
  ),

  // Create
  on(EmployeeActions.createEmployee,
    (state): EmployeeState => ({ ...state, loading: true, error: null })
  ),
  on(EmployeeActions.createEmployeeSuccess,
    (state, { employee }): EmployeeState => adapter.addOne(employee, { ...state, loading: false })
  ),
  on(EmployeeActions.createEmployeeFailure,
    (state, { error }): EmployeeState => ({ ...state, loading: false, error })
  ),

  // Update
  on(EmployeeActions.updateEmployee,
    (state): EmployeeState => ({ ...state, loading: true, error: null })
  ),
  on(EmployeeActions.updateEmployeeSuccess,
    (state, { employee }): EmployeeState =>
      adapter.updateOne({ id: employee.id, changes: employee }, { ...state, loading: false })
  ),
  on(EmployeeActions.updateEmployeeFailure,
    (state, { error }): EmployeeState => ({ ...state, loading: false, error })
  ),

  // Delete
  on(EmployeeActions.deleteEmployee,
    (state): EmployeeState => ({ ...state, loading: true, error: null })
  ),
  on(EmployeeActions.deleteEmployeeSuccess,
    (state, { id }): EmployeeState => adapter.removeOne(id, { ...state, loading: false })
  ),
  on(EmployeeActions.deleteEmployeeFailure,
    (state, { error }): EmployeeState => ({ ...state, loading: false, error })
  ),

  // Filters
  on(EmployeeActions.setFilter,
    (state, { filters }): EmployeeState => ({ ...state, filters })
  ),
);

// ── Selectors ────────────────────────────────────────────────────────────────

export const {
  selectAll,
  selectEntities,
  selectIds,
  selectTotal,
} = adapter.getSelectors();
