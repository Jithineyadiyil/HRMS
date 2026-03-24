import { Pipe, PipeTransform } from '@angular/core';

@Pipe({ name: 'matched', standalone: false })
export class MatchedPipe implements PipeTransform {
  transform(items: any[]): number {
    return (items || []).filter(i => i.matched).length;
  }
}
