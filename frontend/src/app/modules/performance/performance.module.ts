import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { SharedModule } from '../../shared/shared.module';
import { HttpClientModule } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { PerformanceListComponent } from './components/performance-list.component';

const routes: Routes = [{ path: '', component: PerformanceListComponent }];

@NgModule({
  declarations: [PerformanceListComponent],
  imports: [SharedModule, HttpClientModule, FormsModule, RouterModule.forChild(routes)]
})
export class PerformanceModule {}
