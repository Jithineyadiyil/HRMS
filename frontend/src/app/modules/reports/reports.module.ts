import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { SharedModule } from '../../shared/shared.module';
import { HttpClientModule } from '@angular/common/http';
import { ReportsComponent } from './components/reports.component';

const routes: Routes = [{ path: '', component: ReportsComponent }];

@NgModule({
  declarations: [ReportsComponent],
  imports: [SharedModule, HttpClientModule, RouterModule.forChild(routes)]
})
export class ReportsModule {}
