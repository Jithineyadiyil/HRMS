import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Routes } from '@angular/router';
import { ReactiveFormsModule } from '@angular/forms';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatTableModule } from '@angular/material/table';
import { MatChipsModule } from '@angular/material/chips';
import { AttendanceComponent } from './components/attendance.component';
import { BioTimeComponent } from './components/biotime.component';

const routes: Routes = [
  { path: '',        component: AttendanceComponent },
  { path: 'biotime', component: BioTimeComponent },
];

@NgModule({
  declarations: [
    AttendanceComponent,
    BioTimeComponent,   // ← added so mat-icon and other Material directives resolve
  ],
  imports: [
    CommonModule,
    ReactiveFormsModule,
    RouterModule.forChild(routes),
    MatIconModule,       // provides <mat-icon>
    MatButtonModule,
    MatTooltipModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatTableModule,
    MatChipsModule,
  ],
})
export class AttendanceModule {}
