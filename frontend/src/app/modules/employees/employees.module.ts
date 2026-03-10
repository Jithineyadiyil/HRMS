import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { SharedModule } from '../../shared/shared.module';
import { StoreModule } from '@ngrx/store';
import { EffectsModule } from '@ngrx/effects';

import { EmployeeListComponent }   from './components/employee-list.component';
import { EmployeeFormComponent }   from './components/employee-form.component';
import { EmployeeDetailComponent } from './components/employee-detail.component';

import { employeeReducer } from './store/employee.reducer';
import { EmployeeEffects }  from './store/employee.effects';

const routes: Routes = [
  { path: '',          component: EmployeeListComponent },
  { path: 'new',       component: EmployeeFormComponent },
  { path: ':id',       component: EmployeeDetailComponent },
  { path: ':id/edit',  component: EmployeeFormComponent },
];

@NgModule({
  declarations: [
    EmployeeListComponent,
    EmployeeFormComponent,
    EmployeeDetailComponent,
  ],
  imports: [
    SharedModule,
    RouterModule.forChild(routes),
    StoreModule.forFeature('employees', employeeReducer),
    EffectsModule.forFeature([EmployeeEffects]),
  ],
})
export class EmployeesModule {}
