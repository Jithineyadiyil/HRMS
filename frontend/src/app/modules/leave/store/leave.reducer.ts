import { createAction, props, createReducer, on } from '@ngrx/store';

// ── Actions ───────────────────────────────────────────────────────────────────
export const loadLeaveRequests        = createAction('[Leave] Load Requests',         props<{ params?: any }>());
export const loadLeaveRequestsSuccess = createAction('[Leave] Load Requests Success', props<{ data: any }>());
export const loadLeaveTypes           = createAction('[Leave] Load Types');
export const loadLeaveTypesSuccess    = createAction('[Leave] Load Types Success',    props<{ types: any[] }>());
export const submitLeaveRequest       = createAction('[Leave] Submit Request',        props<{ data: any }>());
export const submitLeaveSuccess       = createAction('[Leave] Submit Success',        props<{ request: any }>());
export const approveLeave             = createAction('[Leave] Approve',               props<{ id: number }>());
export const approveLeaveSuccess      = createAction('[Leave] Approve Success',       props<{ id: number }>());
export const rejectLeave              = createAction('[Leave] Reject',                props<{ id: number; reason: string }>());
export const loadLeaveBalance         = createAction('[Leave] Load Balance',          props<{ employeeId: number }>());
export const loadLeaveBalanceSuccess  = createAction('[Leave] Load Balance Success',  props<{ balance: any[] }>());
export const leaveFailure             = createAction('[Leave] Failure',               props<{ error: any }>());

// ── State & Reducer ───────────────────────────────────────────────────────────
export interface LeaveState {
  requests: any[]; types: any[]; balances: any[];
  loading: boolean; pagination: any; error: any;
}
const init: LeaveState = {
  requests: [], types: [], balances: [],
  loading: false, pagination: null, error: null
};

export const leaveReducer = createReducer(init,
  on(loadLeaveRequests,        s => ({ ...s, loading: true })),
  on(loadLeaveRequestsSuccess, (s, { data }) => ({ ...s, loading: false, requests: data.data || [], pagination: data })),
  on(loadLeaveTypesSuccess,    (s, { types }) => ({ ...s, types })),
  on(submitLeaveSuccess,       (s, { request }) => ({ ...s, requests: [request, ...s.requests] })),
  on(loadLeaveBalanceSuccess,  (s, { balance }) => ({ ...s, balances: balance })),
  on(leaveFailure,             (s, { error }) => ({ ...s, loading: false, error })),
);
