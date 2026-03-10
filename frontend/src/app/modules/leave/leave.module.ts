import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Routes } from '@angular/router';
import { SharedModule } from '../../shared/shared.module';
import { StoreModule } from '@ngrx/store';
import { EffectsModule } from '@ngrx/effects';
import { leaveReducer } from './store/leave.reducer';
import { LeaveEffects } from './store/leave.effects';
import { LeaveListComponent } from './components/leave-list.component';
import { LimitedCountPipe } from './pipes/limited-count.pipe';

const routes: Routes = [
  { path: '', component: LeaveListComponent }
];

@NgModule({
  declarations: [
    LeaveListComponent,
    LimitedCountPipe,
  ],
  imports: [
    CommonModule,
    SharedModule,
    RouterModule.forChild(routes),
    StoreModule.forFeature('leave', leaveReducer),
    EffectsModule.forFeature([LeaveEffects]),
  ]
})
export class LeaveModule {}
