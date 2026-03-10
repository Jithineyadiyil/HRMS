import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Routes } from '@angular/router';
import { SharedModule } from '../../shared/shared.module';
import { RequestListComponent } from './components/request-list.component';

const routes: Routes = [{ path: '', component: RequestListComponent }];

@NgModule({
  declarations: [RequestListComponent],
  imports: [CommonModule, SharedModule, RouterModule.forChild(routes)]
})
export class RequestsModule {}
