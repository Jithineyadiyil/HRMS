import { Pipe, PipeTransform } from '@angular/core';

@Pipe({ name: 'limitedCount', standalone: false })
export class LimitedCountPipe implements PipeTransform {
  transform(rows: any[]): number {
    return (rows || []).filter(r => r.is_limited).length;
  }
}
