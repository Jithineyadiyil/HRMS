import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';

@Component({
  standalone: false, selector: 'app-recruitment-list',
  templateUrl: './recruitment-list.component.html',
  styleUrls:  ['./recruitment-list.component.scss'],
})
export class RecruitmentListComponent implements OnInit {
  loading  = true;
  jobs: any[] = [];
  displayedColumns = ['title','department','type','applicants','status','actions'];

  constructor(private http: HttpClient) {}
  ngOnInit() {
    this.http.get<any>('/api/v1/recruitment').subscribe({
      next: r => { this.jobs = r?.data || []; this.loading = false; },
      error: () => this.loading = false
    });
  }
  statusCls(s: string) {
    return ({ open:'badge-green', paused:'badge-yellow', closed:'badge-gray', filled:'badge-blue' } as any)[s] || 'badge-gray';
  }
}
