import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { ChartData } from 'chart.js';

@Component({
  standalone: false,
  selector: 'app-dashboard',
  templateUrl: './dashboard.component.html',
  styleUrls: ['./dashboard.component.scss']
})
export class DashboardComponent implements OnInit {
  currentHour = new Date().getHours();
  loading = true;
  stats: Record<string, number> = {};
  chartType: 'doughnut' = 'doughnut';
  deptChartData: ChartData<'doughnut'> | null = null;

  statCards = [
    { key: 'total_employees',    label: 'Total Employees',     icon: 'people',          color: '#1565c0' },
    { key: 'active_employees',   label: 'Active Employees',    icon: 'person',           color: '#2e7d32' },
    { key: 'on_leave_today',     label: 'On Leave Today',      icon: 'event_available',  color: '#f57c00' },
    { key: 'pending_approvals',  label: 'Pending Approvals',   icon: 'pending_actions',  color: '#c62828' },
    { key: 'open_positions',     label: 'Open Positions',      icon: 'work_outline',     color: '#6a1b9a' },
    { key: 'payroll_this_month', label: 'Payroll This Month',  icon: 'payments',         color: '#00695c' },
  ];

  constructor(private http: HttpClient) {}

  ngOnInit() {
    this.http.get<any>(`${environment.apiUrl}/dashboard/stats`).subscribe({
      next: (data) => {
        this.stats   = data.stats   ?? data ?? {};
        this.loading = false;
        const depts = data.departments as { name: string; count: number }[] | undefined;
        if (depts?.length) {
          this.deptChartData = {
            labels:   depts.map(d => d.name),
            datasets: [{ data: depts.map(d => d.count), backgroundColor: [
              '#1565c0','#2e7d32','#f57c00','#c62828','#6a1b9a','#00695c','#0277bd','#558b2f'
            ]}]
          };
        }
      },
      error: () => { this.loading = false; }
    });
  }
}
