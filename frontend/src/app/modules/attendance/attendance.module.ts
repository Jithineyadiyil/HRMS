import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { SharedModule } from '../../shared/shared.module';

import { AttendanceComponent } from './components/attendance.component';
import { BioTimeComponent }    from './components/biotime.component';
import { MatchedPipe }         from './pipes/matched.pipe';

const routes: Routes = [
  { path: '',         component: AttendanceComponent },
  { path: 'biotime',  component: BioTimeComponent    },
];

@NgModule({
  declarations: [
    AttendanceComponent,
    BioTimeComponent,
    MatchedPipe,
  ],
  imports: [
    SharedModule,
    RouterModule.forChild(routes),
  ],
})
export class AttendanceModule {}
