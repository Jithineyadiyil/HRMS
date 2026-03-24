/**
 * @fileoverview Contracts feature module.
 *
 * Provides the lazy-loaded routing entry point for the /contracts path
 * declared in app-routing.module.ts. Without this module, Angular throws
 * a ChunkLoadError at runtime when any user navigates to /contracts.
 *
 * @module modules/contracts/contracts.module
 */

import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Routes } from '@angular/router';
import { ContractListComponent } from './components/contract-list.component';

const routes: Routes = [
  {
    path:      '',
    component: ContractListComponent,
  },
];

/**
 * Feature module for employee contracts.
 *
 * This module is intentionally minimal — it exists to resolve the missing
 * chunk that was causing a runtime crash. The full contracts feature
 * (PDF generation, e-signature, versioning) can be implemented incrementally.
 */
@NgModule({
  declarations: [ContractListComponent],
  imports: [
    CommonModule,
    RouterModule.forChild(routes),
  ],
})
export class ContractsModule {}
