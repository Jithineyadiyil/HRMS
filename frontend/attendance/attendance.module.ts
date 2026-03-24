import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { SharedModule } from '../../shared/shared.module';

import { AttendanceComponent } from './components/attendance.component';

const routes: Routes = [
  { path: '', component: AttendanceComponent },
];

@NgModule({
  declarations: [AttendanceComponent],
  imports: [
    SharedModule,
    RouterModule.forChild(routes),
  ],
})
export class AttendanceModule {}
