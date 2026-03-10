import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Routes } from '@angular/router';
import { SharedModule } from '../../shared/shared.module';
import { LoanListComponent } from './components/loan-list.component';

const routes: Routes = [{ path: '', component: LoanListComponent }];

@NgModule({
  declarations: [LoanListComponent],
  imports: [CommonModule, SharedModule, RouterModule.forChild(routes)]
})
export class LoansModule {}
