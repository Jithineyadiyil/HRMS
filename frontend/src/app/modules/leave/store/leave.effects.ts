import { Injectable } from '@angular/core';
import { Actions, createEffect, ofType } from '@ngrx/effects';
import { ToastrService } from 'ngx-toastr';
import { of } from 'rxjs';
import { catchError, map, switchMap, tap } from 'rxjs/operators';
import { LeaveApiService } from '../../../core/services/api.services';
import {
  loadLeaveRequests, loadLeaveRequestsSuccess,
  loadLeaveTypes, loadLeaveTypesSuccess,
  submitLeaveRequest, submitLeaveSuccess,
  approveLeave, approveLeaveSuccess,
  leaveFailure
} from './leave.reducer';

@Injectable()
export class LeaveEffects {
  constructor(
    private actions$: Actions,
    private api: LeaveApiService,
    private toastr: ToastrService
  ) {}

  load$ = createEffect(() => this.actions$.pipe(
    ofType(loadLeaveRequests),
    switchMap(({ params }) => this.api.getRequests(params).pipe(
      map(data => loadLeaveRequestsSuccess({ data })),
      catchError(err => of(leaveFailure({ error: err })))
    ))
  ));

  loadTypes$ = createEffect(() => this.actions$.pipe(
    ofType(loadLeaveTypes),
    switchMap(() => this.api.getTypes().pipe(
      map(res => loadLeaveTypesSuccess({ types: res.data || res })),
      catchError(err => of(leaveFailure({ error: err })))
    ))
  ));

  submit$ = createEffect(() => this.actions$.pipe(
    ofType(submitLeaveRequest),
    switchMap(({ data }) => this.api.createRequest(data).pipe(
      map(res => submitLeaveSuccess({ request: res.request || res })),
      catchError(err => of(leaveFailure({ error: err })))
    ))
  ));

  submitSuccess$ = createEffect(() => this.actions$.pipe(
    ofType(submitLeaveSuccess),
    tap(() => this.toastr.success('Leave request submitted'))
  ), { dispatch: false });

  approve$ = createEffect(() => this.actions$.pipe(
    ofType(approveLeave),
    switchMap(({ id }) => this.api.approve(id).pipe(
      map(() => approveLeaveSuccess({ id })),
      catchError(err => of(leaveFailure({ error: err })))
    ))
  ));
}
