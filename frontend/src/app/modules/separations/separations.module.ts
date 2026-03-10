import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Routes } from '@angular/router';
import { SharedModule } from '../../shared/shared.module';
import { SeparationListComponent } from './components/separation-list.component';

const routes: Routes = [{ path: '', component: SeparationListComponent }];

@NgModule({
  declarations: [SeparationListComponent],
  imports: [CommonModule, SharedModule, RouterModule.forChild(routes)]
})
export class SeparationsModule {}
