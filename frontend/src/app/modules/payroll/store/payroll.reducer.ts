import { createAction, props, createReducer, on } from '@ngrx/store';

// ── Actions ──────────────────────────────────────────────────────────────────
export const loadPayrolls          = createAction('[Payroll] Load',            props<{ params?: any }>());
export const loadPayrollsSuccess   = createAction('[Payroll] Load Success',    props<{ data: any }>());
export const runPayroll            = createAction('[Payroll] Run',             props<{ data: any }>());
export const runPayrollSuccess     = createAction('[Payroll] Run Success',     props<{ payroll: any }>());
export const approvePayroll        = createAction('[Payroll] Approve',         props<{ id: number }>());
export const approvePayrollSuccess = createAction('[Payroll] Approve Success', props<{ id: number }>());
export const loadPayslips          = createAction('[Payroll] Load Payslips',   props<{ payrollId: number; params?: any }>());
export const loadPayslipsSuccess   = createAction('[Payroll] Payslips Success',props<{ data: any }>());
export const payrollFailure        = createAction('[Payroll] Failure',         props<{ error: any }>());

// ── State & Reducer ───────────────────────────────────────────────────────────
export interface PayrollState {
  payrolls: any[]; selectedPayslips: any[];
  loading: boolean; runLoading: boolean;
  pagination: any; error: any;
}
const init: PayrollState = {
  payrolls: [], selectedPayslips: [],
  loading: false, runLoading: false,
  pagination: null, error: null
};

export const payrollReducer = createReducer(init,
  on(loadPayrolls,         s => ({ ...s, loading: true })),
  on(loadPayrollsSuccess,  (s, { data }) => ({ ...s, loading: false, payrolls: data.data || [], pagination: data })),
  on(runPayroll,           s => ({ ...s, runLoading: true })),
  on(runPayrollSuccess,    (s, { payroll }) => ({ ...s, runLoading: false, payrolls: [payroll, ...s.payrolls] })),
  on(loadPayslipsSuccess,  (s, { data }) => ({ ...s, selectedPayslips: data.data || [] })),
  on(payrollFailure,       (s, { error }) => ({ ...s, loading: false, runLoading: false, error })),
);
