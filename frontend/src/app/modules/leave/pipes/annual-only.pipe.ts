import { Pipe, PipeTransform } from '@angular/core';

/**
 * Filters leave balance array to show only Annual Leave (code='AL' or name contains 'Annual').
 * Used in balance sidebar cards to show only annual leave balance.
 */
@Pipe({ name: 'annualOnly', standalone: false })
export class AnnualOnlyPipe implements PipeTransform {
  transform(balances: any[]): any[] {
    if (!balances?.length) return [];
    const annual = balances.filter(b =>
      b.leave_type?.code === 'AL' ||
      b.leave_type?.name?.toLowerCase().includes('annual')
    );
    // If none match (no AL type), return all (fallback)
    return annual.length ? annual : balances;
  }
}
