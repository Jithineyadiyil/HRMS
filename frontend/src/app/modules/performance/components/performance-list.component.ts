import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';

@Component({
  standalone: false, selector: 'app-performance-list',
  templateUrl: './performance-list.component.html',
  styleUrls:  ['./performance-list.component.scss'],
})
export class PerformanceListComponent implements OnInit {
  loading  = true;
  reviews: any[] = [];
  displayedColumns = ['employee','period','reviewer','score','status','actions'];

  constructor(private http: HttpClient) {}
  ngOnInit() {
    this.http.get<any>('/api/v1/performance/reviews').subscribe({
      next: r => { this.reviews = r?.data || []; this.loading = false; },
      error: () => this.loading = false
    });
  }
  statusCls(s: string) {
    return ({ pending:'badge-yellow', in_progress:'badge-blue', completed:'badge-green', cancelled:'badge-gray' } as any)[s] || 'badge-gray';
  }
  scoreCls(score: number) {
    if (!score) return '#8b949e';
    if (score >= 4) return '#10b981';
    if (score >= 3) return '#3b82f6';
    if (score >= 2) return '#f59e0b';
    return '#ef4444';
  }
}
