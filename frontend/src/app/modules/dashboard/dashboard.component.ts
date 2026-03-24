import { Component, OnInit, AfterViewInit, OnDestroy } from '@angular/core';
import { Router } from '@angular/router';
import { HttpClient } from '@angular/common/http';

@Component({
  standalone: false,
  selector: 'app-dashboard',
  templateUrl: './dashboard.component.html',
  styleUrls: ['./dashboard.component.scss']
})
export class DashboardComponent implements OnInit, AfterViewInit, OnDestroy {

  loading = true;
  private st: any = null;

  // ── Pre-computed arrays (NOT methods — avoids change detection loop) ──────
  kpiItems:   any[] = [];
  statItems:  any[] = [];

  employees:  any[] = [];
  leaveReqs:  any[] = [];
  openJobs:   any[] = [];
  reviews:    any[] = [];
  activity:   any[] = [];

  private charts: any[] = [];
  private chartRetries = 0;
  private readonly MAX_RETRIES = 10;

  constructor(private http: HttpClient, private router: Router) {}

  // ── Greeting / Date ───────────────────────────────────────────────────────
  readonly greeting = (() => {
    const h = new Date().getHours();
    return h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening';
  })();

  readonly userName = (() => {
    try { return JSON.parse(localStorage.getItem('hrms_user') || '{}')?.name?.split(' ')[0] || 'Admin'; }
    catch { return 'Admin'; }
  })();

  readonly dateStr = new Date().toLocaleDateString('en-GB', {
    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
  });

  // ── Helpers ───────────────────────────────────────────────────────────────
  pct(a: number, b: number) { return b ? Math.round((a / b) * 100) : 0; }
  isOD(d: string)           { return d && new Date(d) < new Date(); }
  go(path: string)          { this.router.navigate([path]); }

  empStatusCls(s: string)   { return ({ active:'badge-green', on_leave:'badge-yellow', probation:'badge-blue', inactive:'badge-gray', terminated:'badge-red' } as any)[s] || 'badge-gray'; }
  leaveCls(s: string)       { return ({ pending:'badge-yellow', approved:'badge-green', rejected:'badge-red', cancelled:'badge-gray' } as any)[s] || 'badge-gray'; }
  jobCls(s: string)         { return ({ open:'badge-green', paused:'badge-yellow', closed:'badge-gray', filled:'badge-blue' } as any)[s] || 'badge-gray'; }
  perfCls(s: string)        { return ({ pending:'badge-yellow', in_progress:'badge-blue', completed:'badge-green', cancelled:'badge-gray' } as any)[s] || 'badge-gray'; }

  initial(n?: string) { return n?.charAt(0)?.toUpperCase() || '?'; }
  avCol(n?: string) {
    const c = ['#3b82f6','#10b981','#f59e0b','#ef4444','#6366f1','#0ea5e9','#f97316','#a78bfa'];
    return c[(n?.charCodeAt(0) ?? 0) % c.length];
  }
  actIcon(a?: string) {
    return ({ created:'add_circle_outline', updated:'edit', deleted:'delete_outline', approved:'check_circle_outline',
              rejected:'cancel', joined:'person_add_alt_1', resigned:'person_remove' } as any)[a||''] || 'fiber_manual_record';
  }
  actColor(a?: string) {
    return ({ created:'#10b981', updated:'#3b82f6', deleted:'#ef4444', approved:'#10b981',
              rejected:'#ef4444', joined:'#6366f1', resigned:'#f59e0b' } as any)[a||''] || '#8b949e';
  }

  // ── Lifecycle ─────────────────────────────────────────────────────────────
  ngOnInit()        { this.loadAll(); }
  ngAfterViewInit() { setTimeout(() => this.tryBuildCharts(), 1000); }
  ngOnDestroy()     { this.destroyCharts(); }

  refresh() {
    this.loading = true;
    this.chartRetries = 0;
    this.destroyCharts();
    this.loadAll();
    setTimeout(() => this.tryBuildCharts(), 1000);
  }

  private destroyCharts() {
    this.charts.forEach(c => { try { c.destroy(); } catch {} });
    this.charts = [];
  }

  // ── Data loading ──────────────────────────────────────────────────────────
  private loadAll() {
    const get = (url: string, cb: (d: any) => void) =>
      this.http.get<any>(url).subscribe({ next: cb, error: () => {} });

    get('/api/v1/dashboard/stats', d => {
      this.st = d;
      this.loading = false;
      this.buildKpis();
      this.buildStatCards();
    });

    get('/api/v1/employees?per_page=5&sort=created_at',   r => this.employees = r?.data || []);
    get('/api/v1/leave?status=pending&per_page=5',        r => this.leaveReqs = r?.data || []);
    get('/api/v1/recruitment?per_page=5',                 r => this.openJobs  = r?.data || []);
    get('/api/v1/performance/reviews?per_page=5',         r => this.reviews   = r?.data || []);
    get('/api/v1/dashboard/recent-activities',            r => this.activity  = Array.isArray(r) ? r : r?.data || []);
  }

  // ── Build arrays once (called after stats load) ────────────────────────────
  private buildKpis() {
    const d = this.st || {};
    this.kpiItems = [
      { label: 'Total Employees',   value: d.employees?.total          ?? 0,    color: '#3b82f6', icon: 'group' },
      { label: 'On Leave Today',    value: d.leave?.on_leave_today     ?? 0,    color: '#f59e0b', icon: 'event_busy' },
      { label: 'Pending Leaves',    value: d.leave?.pending            ?? 0,    color: d.leave?.pending > 5 ? '#ef4444' : '#f59e0b', icon: 'pending_actions' },
      { label: 'Payroll Processed', value: d.payroll?.processed        ?? 0,    color: '#10b981', icon: 'payments' },
      { label: 'Open Positions',    value: d.recruitment?.open_positions ?? 0,  color: '#6366f1', icon: 'work_outline' },
      { label: 'Pending Reviews',   value: d.performance?.pending      ?? 0,    color: '#0ea5e9', icon: 'rate_review' },
      { label: 'Attendance Rate',   value: (d.attendance?.rate ?? 0) + '%',     color: d.attendance?.rate >= 90 ? '#10b981' : '#f59e0b', icon: 'fingerprint' },
      { label: 'Expiring Contracts',value: d.employees?.contracts_expiring ?? 0, color: d.employees?.contracts_expiring > 0 ? '#ef4444' : '#8b949e', icon: 'description' },
    ];
  }

  private buildStatCards() {
    const d = this.st || {};
    this.statItems = [
      {
        title: 'Workforce',  icon: 'people_alt', color: '#3b82f6',
        main: d.employees?.total ?? 0, mainLabel: 'Total Employees',
        items: [
          { label: 'Active',    value: d.employees?.active    ?? 0, color: '#10b981' },
          { label: 'Probation', value: d.employees?.probation ?? 0, color: '#f59e0b' },
          { label: 'On Leave',  value: d.employees?.on_leave  ?? 0, color: '#6366f1' },
          { label: 'New/Month', value: d.employees?.new_this_month ?? 0, color: '#0ea5e9' },
        ],
        route: '/employees',
        alert: d.employees?.contracts_expiring > 0 ? `${d.employees.contracts_expiring} contracts expiring` : null,
        alertColor: '#f59e0b',
        progress: this.pct(d.employees?.active, d.employees?.total),
        progressColor: '#3b82f6', progressLabel: '% Active',
      },
      {
        title: 'Leave', icon: 'event_available', color: '#f59e0b',
        main: d.leave?.pending ?? 0, mainLabel: 'Pending Approval',
        items: [
          { label: 'Approved',   value: d.leave?.approved      ?? 0, color: '#10b981' },
          { label: 'Active Now', value: d.leave?.on_leave_today ?? 0, color: '#f59e0b' },
          { label: 'Rejected',   value: d.leave?.rejected       ?? 0, color: '#ef4444' },
          { label: 'This Month', value: d.leave?.approved_this_month ?? 0, color: '#6366f1' },
        ],
        route: '/leave',
        alert: d.leave?.pending > 5 ? `${d.leave.pending} requests waiting` : null,
        alertColor: '#ef4444',
        progress: this.pct(d.leave?.approved, d.leave?.total),
        progressColor: '#10b981', progressLabel: '% Approval Rate',
      },
      {
        title: 'Recruitment', icon: 'work', color: '#6366f1',
        main: d.recruitment?.open_positions ?? 0, mainLabel: 'Open Positions',
        items: [
          { label: 'Applicants',  value: d.recruitment?.applicants        ?? 0, color: '#3b82f6' },
          { label: 'Interviews',  value: d.recruitment?.interviews_today  ?? 0, color: '#f59e0b' },
          { label: 'Offers Sent', value: d.recruitment?.offers_sent       ?? 0, color: '#10b981' },
          { label: 'Hired/Month', value: d.recruitment?.hired_this_month  ?? 0, color: '#0ea5e9' },
        ],
        route: '/recruitment',
        alert: null,
        progress: this.pct(d.recruitment?.offers_sent, d.recruitment?.applicants),
        progressColor: '#6366f1', progressLabel: '% Offer Rate',
      },
      {
        title: 'Performance', icon: 'insights', color: '#0ea5e9',
        main: d.performance?.pending ?? 0, mainLabel: 'Pending Reviews',
        items: [
          { label: 'In Progress', value: d.performance?.in_progress ?? 0, color: '#0ea5e9' },
          { label: 'Completed',   value: d.performance?.completed   ?? 0, color: '#10b981' },
          { label: 'Overdue',     value: d.performance?.overdue     ?? 0, color: '#ef4444' },
          { label: 'Avg Score',   value: d.performance?.avg_score   ?? '—', color: '#6366f1' },
        ],
        route: '/performance',
        alert: d.performance?.overdue > 0 ? `${d.performance.overdue} overdue` : null,
        alertColor: '#ef4444',
        progress: this.pct(d.performance?.completed, d.performance?.total),
        progressColor: '#0ea5e9', progressLabel: '% Completion',
      },
      {
        title: 'Payroll', icon: 'account_balance_wallet', color: '#10b981',
        main: d.payroll?.due_this_month ?? 0, mainLabel: 'Due This Month',
        items: [
          { label: 'Processed', value: d.payroll?.processed         ?? 0, color: '#10b981' },
          { label: 'Pending',   value: d.payroll?.pending_approvals ?? 0, color: '#f59e0b' },
          { label: 'Errors',    value: d.payroll?.errors            ?? 0, color: d.payroll?.errors > 0 ? '#ef4444' : '#8b949e' },
          { label: 'On Hold',   value: d.payroll?.on_hold           ?? 0, color: '#6366f1' },
        ],
        route: '/payroll',
        alert: d.payroll?.errors > 0 ? `${d.payroll.errors} payroll errors` : null,
        alertColor: '#ef4444',
        progress: this.pct(d.payroll?.processed, d.payroll?.total),
        progressColor: '#10b981', progressLabel: '% Processed',
      },
      {
        title: 'Organisation', icon: 'account_tree', color: '#a78bfa',
        main: d.departments?.total ?? 0, mainLabel: 'Departments',
        items: [
          { label: 'Teams',      value: d.departments?.teams      ?? 0, color: '#a78bfa' },
          { label: 'Managers',   value: d.departments?.managers   ?? 0, color: '#10b981' },
          { label: 'No Manager', value: d.departments?.vacant_mgr ?? 0, color: d.departments?.vacant_mgr > 0 ? '#f59e0b' : '#8b949e' },
          { label: 'Headcount',  value: d.employees?.total        ?? 0, color: '#3b82f6' },
        ],
        route: '/org-chart',
        alert: null,
        progress: this.pct(d.departments?.managers, d.departments?.total),
        progressColor: '#a78bfa', progressLabel: '% Have Manager',
      },
    ];
  }

  // ── Charts ────────────────────────────────────────────────────────────────
  private tryBuildCharts() {
    if (this.chartRetries >= this.MAX_RETRIES) return; // stop retrying
    const C = (window as any).Chart;
    if (!C) {
      this.chartRetries++;
      setTimeout(() => this.tryBuildCharts(), 500);
      return;
    }
    this.chartRetries = 0;
    this.http.get<any>('/api/v1/dashboard/charts').subscribe({
      next:  d => this.buildCharts(d, C),
      error: () => this.buildCharts(null, C)
    });
  }

  private buildCharts(d: any, C: any) {
    this.mkTrend(d, C);
    this.mkDept(d, C);
    this.mkLeave(d, C);
    this.mkPerf(d, C);
    this.mkPayroll(d, C);
  }

  private chartOpts(extra = {}) {
    return {
      responsive: true, maintainAspectRatio: false,
      interaction: { mode: 'index' as const, intersect: false },
      plugins: { legend: { labels: { color: '#8b949e', font: { size: 11 }, boxWidth: 12, padding: 12 } } },
      scales: {
        x: { grid: { color: 'rgba(255,255,255,.04)' }, ticks: { color: '#8b949e', font: { size: 10 } } },
        y: { grid: { color: 'rgba(255,255,255,.04)' }, ticks: { color: '#8b949e', font: { size: 10 } }, beginAtZero: true }
      }, ...extra
    };
  }

  private donut(elId: string, labels: string[], vals: number[], colors: string[], C: any) {
    const el = document.getElementById(elId) as HTMLCanvasElement;
    if (!el) return;
    this.charts.push(new C(el, {
      type: 'doughnut',
      data: { labels, datasets: [{ data: vals, backgroundColor: colors, borderWidth: 0, hoverOffset: 6 }] },
      options: { responsive: true, maintainAspectRatio: false, cutout: '72%',
        plugins: { legend: { position: 'bottom' as const, labels: { color: '#8b949e', font: { size: 10 }, boxWidth: 10, padding: 10 } } } }
    }));
  }

  private mkTrend(d: any, C: any) {
    const el = document.getElementById('trendChart') as HTMLCanvasElement;
    if (!el) return;
    const hires  = d?.hire_trend || [];
    const exits  = d?.exit_trend || [];
    const labels = hires.length ? hires.map((x: any) => x.month) : ['Aug','Sep','Oct','Nov','Dec','Jan'];
    this.charts.push(new C(el, {
      type: 'line',
      data: { labels, datasets: [
        { label: 'New Hires', data: hires.length ? hires.map((x:any) => x.count) : [3,5,4,7,4,6],
          borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,.08)', tension: .4, fill: true,
          pointRadius: 4, pointBackgroundColor: '#3b82f6', pointBorderColor: '#161b22', pointBorderWidth: 2 },
        { label: 'Exits', data: exits.length ? exits.map((x:any) => x.count) : [1,2,1,2,1,2],
          borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,.05)', tension: .4, fill: true,
          pointRadius: 4, pointBackgroundColor: '#ef4444', pointBorderColor: '#161b22', pointBorderWidth: 2 },
      ]},
      options: this.chartOpts()
    }));
  }

  private mkDept(d: any, C: any) {
    const raw    = d?.dept_distribution || [];
    const labels = raw.length ? raw.map((x:any) => x.name)  : ['Engineering','HR','Finance','Operations','Sales'];
    const vals   = raw.length ? raw.map((x:any) => x.count) : [12,4,6,8,5];
    this.donut('deptChart', labels, vals, ['#3b82f6','#10b981','#f59e0b','#6366f1','#0ea5e9','#a78bfa','#f472b6'], C);
  }

  private mkLeave(d: any, C: any) {
    const raw    = d?.leave_by_type || [];
    const labels = raw.length ? raw.map((x:any) => x.leave_type) : ['Annual','Sick','Emergency','Unpaid'];
    const vals   = raw.length ? raw.map((x:any) => x.count)      : [8,4,2,1];
    this.donut('leaveChart', labels, vals, ['#10b981','#ef4444','#f97316','#8b949e','#a78bfa'], C);
  }

  private mkPerf(d: any, C: any) {
    const raw    = d?.performance_ratings || [];
    const labels = raw.length ? raw.map((x:any) => x.rating) : ['Excellent','Good','Average','Needs Work'];
    const vals   = raw.length ? raw.map((x:any) => x.count)  : [5,12,6,2];
    this.donut('perfChart', labels, vals, ['#10b981','#3b82f6','#f59e0b','#ef4444'], C);
  }

  private mkPayroll(d: any, C: any) {
    const el = document.getElementById('payrollChart') as HTMLCanvasElement;
    if (!el) return;
    const raw    = d?.payroll_trend || [];
    const labels = raw.length ? raw.map((x:any) => x.month)  : ['Aug','Sep','Oct','Nov','Dec','Jan'];
    const vals   = raw.length ? raw.map((x:any) => x.total)  : [120000,133000,125000,141000,137000,148000];
    this.charts.push(new C(el, {
      type: 'bar',
      data: { labels, datasets: [{ label: 'Payroll ($)', data: vals,
        backgroundColor: 'rgba(16,185,129,.6)', borderRadius: 6, borderWidth: 0,
        hoverBackgroundColor: 'rgba(16,185,129,.85)' }] },
      options: { ...this.chartOpts(),
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { display: false }, ticks: { color: '#8b949e', font: { size: 10 } } },
          y: { grid: { color: 'rgba(255,255,255,.04)' }, ticks: { color: '#8b949e', callback: (v: any) => '$' + (v/1000) + 'k' } }
        }
      }
    }));
  }
}
