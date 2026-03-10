import { ActionReducerMap } from '@ngrx/store';
import { authReducer, AuthState } from '../modules/auth/store/auth.reducer';

// Root state only holds auth — feature stores register via StoreModule.forFeature()
export interface AppState {
  auth: AuthState;
  // Feature slices (employees, payroll, leave) are registered lazily
  [key: string]: any;
}

export const reducers: ActionReducerMap<any> = {
  auth: authReducer,
};
