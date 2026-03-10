import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { SharedModule } from '../../shared/shared.module';
import { HttpClientModule } from '@angular/common/http';
import { OrgChartComponent } from './components/org-chart.component';

const routes: Routes = [{ path: '', component: OrgChartComponent }];

@NgModule({
  declarations: [OrgChartComponent],
  imports: [SharedModule, HttpClientModule, RouterModule.forChild(routes)]
})
export class OrgChartModule {}
