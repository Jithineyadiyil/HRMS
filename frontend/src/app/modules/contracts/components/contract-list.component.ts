/**
 * @fileoverview Contracts list component.
 *
 * Placeholder component that resolves the runtime ChunkLoadError caused by
 * the /contracts route pointing to a non-existent module. This stub renders
 * a visible "coming soon" panel so users are not presented with a blank screen
 * or an unhandled error.
 *
 * Replace this file with the full contracts implementation when ready.
 *
 * @module modules/contracts/components/contract-list.component
 */

import { Component, ChangeDetectionStrategy } from '@angular/core';

/**
 * Stub component for the contracts list page.
 *
 * @example
 * <app-contract-list></app-contract-list>
 */
@Component({
  standalone:      false,
  selector:        'app-contract-list',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="page-header">
      <h1 class="page-title">
        <span class="material-icons">description</span>
        Contracts
      </h1>
    </div>

    <div class="empty-state">
      <div class="empty-icon">
        <span class="material-icons">description</span>
      </div>
      <h3>Contracts module — coming soon</h3>
      <p>
        This module will support employee contract creation, PDF generation,
        digital signature workflows, and contract versioning.
      </p>
      <p class="empty-hint">
        Contact your HRMS administrator if you need access to contract records.
      </p>
    </div>
  `,
  styles: [`
    .page-header {
      display: flex;
      align-items: center;
      margin-bottom: 24px;
    }
    .page-title {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 22px;
      font-weight: 500;
      margin: 0;
    }
    .empty-state {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 80px 24px;
      text-align: center;
      color: var(--text-secondary, #666);
    }
    .empty-icon .material-icons {
      font-size: 64px;
      color: var(--border-color, #ccc);
      margin-bottom: 16px;
    }
    .empty-state h3 {
      font-size: 18px;
      font-weight: 500;
      margin: 0 0 8px;
      color: var(--text-primary, #333);
    }
    .empty-state p {
      max-width: 480px;
      margin: 0 0 8px;
      line-height: 1.6;
    }
    .empty-hint {
      font-size: 13px;
      color: var(--text-tertiary, #999);
    }
  `],
})
export class ContractListComponent {}
