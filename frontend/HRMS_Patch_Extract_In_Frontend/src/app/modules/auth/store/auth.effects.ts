import { Injectable } from '@angular/core';
import { Actions, createEffect, ofType } from '@ngrx/effects';
import { Router } from '@angular/router';
import { of } from 'rxjs';
import { catchError, map, switchMap, tap } from 'rxjs/operators';
import { AuthService } from '../../../core/services/auth.service';
import { login, loginSuccess, loginFailure, logout, logoutSuccess } from './auth.actions';

@Injectable()
export class AuthEffects {
  constructor(private actions$: Actions, private auth: AuthService, private router: Router) {}

  login$ = createEffect(() => this.actions$.pipe(
    ofType(login),
    switchMap(({ credentials }) =>
      this.auth.login(credentials.email, credentials.password).pipe(
        map(res => loginSuccess({ user: res.user, token: res.token })),
        catchError(err => of(loginFailure({ error: err.error?.message || 'Login failed' })))
      )
    )
  ));

  loginSuccess$ = createEffect(() => this.actions$.pipe(
    ofType(loginSuccess),
    tap(() => this.router.navigate(['/dashboard']))
  ), { dispatch: false });

  logout$ = createEffect(() => this.actions$.pipe(
    ofType(logout),
    tap(() => { this.auth.logout(); this.router.navigate(['/auth/login']); }),
    map(() => logoutSuccess())
  ));
}
