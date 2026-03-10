import { Injectable } from '@angular/core';
import { Actions, createEffect, ofType } from '@ngrx/effects';
import { ToastrService } from 'ngx-toastr';
import { of } from 'rxjs';
import { switchMap, map, catchError, tap } from 'rxjs/operators';
import { PayrollApiService } from '../../../core/services/api.services';
import { loadPayrolls, loadPayrollsSuccess, runPayroll, runPayrollSuccess, approvePayroll, approvePayrollSuccess, loadPayslips, loadPayslipsSuccess, payrollFailure } from './payroll.reducer';

@Injectable()
export class PayrollEffects {
  constructor(private actions$: Actions, private api: PayrollApiService, private toastr: ToastrService) {}

  load$ = createEffect(() => this.actions$.pipe(
    ofType(loadPayrolls),
    switchMap(({ params }) => this.api.getAll(params).pipe(
      map(data => loadPayrollsSuccess({ data })),
      catchError(err => of(payrollFailure({ error: err }))),
    )),
  ));

  run$ = createEffect(() => this.actions$.pipe(
    ofType(runPayroll),
    switchMap(({ data }) => this.api.run(data).pipe(
      map(res => runPayrollSuccess({ payroll: res.payroll })),
      catchError(err => of(payrollFailure({ error: err }))),
    )),
  ));

  runSuccess$ = createEffect(() => this.actions$.pipe(
    ofType(runPayrollSuccess),
    tap(() => this.toastr.success('Payroll run successfully. Pending approval.')),
  ), { dispatch: false });

  approve$ = createEffect(() => this.actions$.pipe(
    ofType(approvePayroll),
    switchMap(({ id }) => this.api.approve(id).pipe(
      map(() => approvePayrollSuccess({ id })),
      catchError(err => of(payrollFailure({ error: err }))),
    )),
  ));

  loadPayslips$ = createEffect(() => this.actions$.pipe(
    ofType(loadPayslips),
    switchMap(({ payrollId, params }) => this.api.getPayslips(payrollId, params).pipe(
      map(data => loadPayslipsSuccess({ data })),
      catchError(err => of(payrollFailure({ error: err }))),
    )),
  ));
}
