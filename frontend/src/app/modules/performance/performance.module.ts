import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Routes } from '@angular/router';
import { ReactiveFormsModule, FormsModule } from '@angular/forms';
import { MatIconModule } from '@angular/material/icon';
import { MatTableModule } from '@angular/material/table';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { PerformanceListComponent } from './components/performance-list.component';

const routes: Routes = [{ path: '', component: PerformanceListComponent }];

@NgModule({
  declarations: [PerformanceListComponent],
  imports: [
    CommonModule, ReactiveFormsModule, FormsModule,
    RouterModule.forChild(routes),
    MatIconModule, MatTableModule, MatTooltipModule, MatProgressSpinnerModule,
  ],
})
export class PerformanceModule {}
