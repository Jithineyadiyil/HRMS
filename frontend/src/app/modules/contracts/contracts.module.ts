import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Routes } from '@angular/router';
import { ReactiveFormsModule, FormsModule } from '@angular/forms';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatTableModule } from '@angular/material/table';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';

import { ContractListComponent } from './components/contract-list.component';
import { ContractRenewalsComponent } from './components/contract-renewals.component';

const routes: Routes = [
  {
    path:      '',
    component: ContractListComponent,
  },
  {
    path:      'renewals',
    component: ContractRenewalsComponent,
  },
];

@NgModule({
  declarations: [
    ContractListComponent,
    ContractRenewalsComponent,
  ],
  imports: [
    CommonModule,
    ReactiveFormsModule,
    FormsModule,
    RouterModule.forChild(routes),
    MatIconModule,
    MatButtonModule,
    MatTooltipModule,
    MatTableModule,
    MatProgressSpinnerModule,
  ],
})
export class ContractsModule {}
