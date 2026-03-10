// ── reducer ───────────────────────────────────────────────────────────────────
import { createReducer, on } from '@ngrx/store';
import * as A from './auth.actions';

export interface AuthState { user: any; token: string | null; loading: boolean; error: string | null; }
const init: AuthState = {
  user:    JSON.parse(localStorage.getItem('hrms_user') || 'null'),
  token:   localStorage.getItem('hrms_token'),
  loading: false, error: null,
};

export const authReducer = createReducer(init,
  on(A.login,         s => ({ ...s, loading: true, error: null })),
  on(A.loginSuccess,  (s, { token, user }) => ({ ...s, loading: false, token, user })),
  on(A.loginFailure,  (s, { error }) => ({ ...s, loading: false, error })),
  on(A.logout,        s => ({ ...s, loading: true })),
  on(A.logoutSuccess, () => ({ user: null, token: null, loading: false, error: null })),
  on(A.loadMeSuccess, (s, { user }) => ({ ...s, user })),
);
