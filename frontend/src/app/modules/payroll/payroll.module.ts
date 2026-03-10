import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { SharedModule } from '../../shared/shared.module';
import { HttpClientModule } from '@angular/common/http';
import { StoreModule } from '@ngrx/store';
import { EffectsModule } from '@ngrx/effects';
import { payrollReducer } from './store/payroll.reducer';
import { PayrollEffects } from './store/payroll.effects';
import { PayrollListComponent } from './components/payroll-list.component';

const routes: Routes = [{ path: '', component: PayrollListComponent }];

@NgModule({
  declarations: [PayrollListComponent],
  imports: [
    SharedModule, HttpClientModule,
    RouterModule.forChild(routes),
    StoreModule.forFeature('payroll', payrollReducer),
    EffectsModule.forFeature([PayrollEffects])
  ]
})
export class PayrollModule {}
