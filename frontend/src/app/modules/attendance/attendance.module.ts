import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Routes } from '@angular/router';
import { ReactiveFormsModule, FormsModule } from '@angular/forms';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatTableModule } from '@angular/material/table';
import { MatChipsModule } from '@angular/material/chips';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatDialogModule } from '@angular/material/dialog';
import { MatSnackBarModule } from '@angular/material/snack-bar';

import { AttendanceComponent } from './components/attendance.component';
import { AttendanceDashboardComponent } from './components/attendance-dashboard.component';
import { BioTimeComponent } from './components/biotime.component';

const routes: Routes = [
  { path: '',          component: AttendanceDashboardComponent },
  { path: 'log',       component: AttendanceComponent },
  { path: 'biotime',   component: BioTimeComponent },
];

@NgModule({
  declarations: [
    AttendanceComponent,
    AttendanceDashboardComponent,
    BioTimeComponent,         // ← declared here so mat-icon and all Material directives resolve
  ],
  imports: [
    CommonModule,
    ReactiveFormsModule,
    FormsModule,
    RouterModule.forChild(routes),
    MatIconModule,            // provides <mat-icon>
    MatButtonModule,
    MatTooltipModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatTableModule,
    MatChipsModule,
    MatProgressSpinnerModule,
    MatDialogModule,
    MatSnackBarModule,
  ],
})
export class AttendanceModule {}
