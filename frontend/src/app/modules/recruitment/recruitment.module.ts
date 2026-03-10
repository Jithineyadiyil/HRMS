import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { SharedModule } from '../../shared/shared.module';
import { HttpClientModule } from '@angular/common/http';
import { RecruitmentListComponent } from './components/recruitment-list.component';

const routes: Routes = [{ path: '', component: RecruitmentListComponent }];

@NgModule({
  declarations: [RecruitmentListComponent],
  imports: [SharedModule, HttpClientModule, RouterModule.forChild(routes)]
})
export class RecruitmentModule {}
