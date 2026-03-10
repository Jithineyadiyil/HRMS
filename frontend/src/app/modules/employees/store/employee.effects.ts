import { Injectable } from '@angular/core';
import { Actions, createEffect, ofType } from '@ngrx/effects';
import { ToastrService } from 'ngx-toastr';
import { of } from 'rxjs';
import { map, switchMap, catchError, tap } from 'rxjs/operators';
import { EmployeeApiService } from '../../../core/services/api.services';
import * as A from './employee.actions';

@Injectable()
export class EmployeeEffects {
  constructor(private actions$: Actions, private api: EmployeeApiService, private toastr: ToastrService) {}

  load$ = createEffect(() => this.actions$.pipe(
    ofType(A.loadEmployees),
    switchMap(({ params }) => this.api.getAll(params).pipe(
      map(data => A.loadEmployeesSuccess({ data })),
      catchError(error => of(A.loadEmployeesFailure({ error }))),
    )),
  ));

  loadOne$ = createEffect(() => this.actions$.pipe(
    ofType(A.loadEmployee),
    switchMap(({ id }) => this.api.getOne(id).pipe(
      map(res => A.loadEmployeeSuccess({ employee: res.employee })),
      catchError(error => of(A.loadEmployeesFailure({ error }))),
    )),
  ));

  create$ = createEffect(() => this.actions$.pipe(
    ofType(A.createEmployee),
    switchMap(({ data }) => this.api.create(data).pipe(
      map(res => A.createEmployeeSuccess({ employee: res.employee })),
      catchError(error => of(A.loadEmployeesFailure({ error }))),
    )),
  ));

  createSuccess$ = createEffect(() => this.actions$.pipe(
    ofType(A.createEmployeeSuccess),
    tap(() => this.toastr.success('Employee created successfully')),
  ), { dispatch: false });

  update$ = createEffect(() => this.actions$.pipe(
    ofType(A.updateEmployee),
    switchMap(({ id, data }) => this.api.update(id, data).pipe(
      map(res => A.updateEmployeeSuccess({ employee: res.employee })),
      catchError(error => of(A.loadEmployeesFailure({ error }))),
    )),
  ));

  updateSuccess$ = createEffect(() => this.actions$.pipe(
    ofType(A.updateEmployeeSuccess),
    tap(() => this.toastr.success('Employee updated successfully')),
  ), { dispatch: false });

  delete$ = createEffect(() => this.actions$.pipe(
    ofType(A.deleteEmployee),
    switchMap(({ id }) => this.api.delete(id).pipe(
      map(() => A.deleteEmployeeSuccess({ id })),
      catchError(error => of(A.loadEmployeesFailure({ error }))),
    )),
  ));
}
