// ── reducer ───────────────────────────────────────────────────────────────────
import { createReducer, on } from '@ngrx/store';
import { createEntityAdapter, EntityAdapter, EntityState } from '@ngrx/entity';
import * as A from './employee.actions';

export interface EmployeeState extends EntityState<any> {
  selectedEmployee: any | null;
  loading: boolean;
  pagination: any;
  filters: any;
  error: any;
}

export const adapter: EntityAdapter<any> = createEntityAdapter<any>();
const init: EmployeeState = adapter.getInitialState({ selectedEmployee: null, loading: false, pagination: null, filters: {}, error: null });

export const employeeReducer = createReducer(init,
  on(A.loadEmployees,         s => ({ ...s, loading: true })),
  on(A.loadEmployeesSuccess,  (s, { data }) => adapter.setAll(data.data, { ...s, loading: false, pagination: { ...data, data: undefined } })),
  on(A.loadEmployeesFailure,  (s, { error }) => ({ ...s, loading: false, error })),
  on(A.loadEmployeeSuccess,   (s, { employee }) => ({ ...s, selectedEmployee: employee })),
  on(A.createEmployeeSuccess, (s, { employee }) => adapter.addOne(employee, s)),
  on(A.updateEmployeeSuccess, (s, { employee }) => adapter.updateOne({ id: employee.id, changes: employee }, s)),
  on(A.deleteEmployeeSuccess, (s, { id }) => adapter.removeOne(id, s)),
  on(A.setFilter,             (s, { filters }) => ({ ...s, filters })),
);

export const { selectAll, selectEntities, selectIds } = adapter.getSelectors();
