import {
  Component,
  OnInit,
  OnDestroy,
  AfterViewInit,
  ChangeDetectionStrategy,
  ChangeDetectorRef,
} from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Subject, interval } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { AuthService } from '../../../core/services/auth.service';

declare const Chart: any;

@Component({
  standalone:      false,
  selector:        'app-attendance-dashboard',
  templateUrl:     './attendance-dashboard.component.html',
  styleUrls:       ['./attendance-dashboard.component.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AttendanceDashboardComponent implements OnInit, AfterViewInit, OnDestroy {

  isAdmin   = false;
  loading   = true;
  clock     = '';
  data: any = null;

  // check-in/out
  checkingIn  = false;
  checkingOut = false;
  actionMsg   = '';
  actionError = '';

  private readonly api      = '/api/v1/attendance';
  private readonly destroy$ = new Subject<void>();
  private charts: any[]     = [];

  constructor(
    private readonly http: HttpClient,
    private readonly auth: AuthService,
    private readonly cdr:  ChangeDetectorRef,
  ) {}

  ngOnInit(): void {
    this.isAdmin = this.auth.isManagerRole();
    this.loadDashboard();

    interval(1000).pipe(takeUntil(this.destroy$)).subscribe(() => {
      this.clock = new Date().toLocaleTimeString('en-GB', {
        hour: '2-digit', minute: '2-digit', second: '2-digit',
      });
      this.cdr.markForCheck();
    });
  }

  ngAfterViewInit(): void {
    // charts rendered after data loads
  }

  // ── Data ─────────────────────────────────────────────────────────────

  loadDashboard(): void {
    this.loading = true;
    this.http.get<any>(`${this.api}/dashboard`)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          this.data    = res;
          this.loading = false;
          this.cdr.markForCheck();
          setTimeout(() => this.renderCharts(), 100);
        },
        error: () => { this.loading = false; this.cdr.markForCheck(); },
      });
  }

  // ── Check-in / out ───────────────────────────────────────────────────

  checkIn(): void {
    this.checkingIn  = true;
    this.actionMsg   = '';
    this.actionError = '';
    this.http.post<any>(`${this.api}/checkin`, {})
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          if (this.data) this.data.today_log = res.log;
          this.actionMsg  = 'Checked in at ' + res.log?.check_in;
          this.checkingIn = false;
          this.cdr.markForCheck();
        },
        error: (err) => {
          if (err?.error?.log && this.data) this.data.today_log = err.error.log;
          this.actionError = err?.error?.message ?? 'Check-in failed.';
          this.checkingIn  = false;
          this.cdr.markForCheck();
        },
      });
  }

  checkOut(): void {
    this.checkingOut = true;
    this.actionMsg   = '';
    this.actionError = '';
    this.http.post<any>(`${this.api}/checkout`, {})
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          if (this.data) this.data.today_log = res.log;
          this.actionMsg   = 'Checked out at ' + res.log?.check_out + ' · ' + this.fmt(res.log?.total_minutes);
          this.checkingOut = false;
          this.cdr.markForCheck();
        },
        error: (err) => {
          if (err?.error?.log && this.data) this.data.today_log = err.error.log;
          this.actionError = err?.error?.message ?? 'Check-out failed.';
          this.checkingOut = false;
          this.cdr.markForCheck();
        },
      });
  }

  get canCheckIn():  boolean { return !this.data?.today_log?.check_in; }
  get canCheckOut(): boolean { return !!this.data?.today_log?.check_in && !this.data?.today_log?.check_out; }

  // ── Helpers ──────────────────────────────────────────────────────────

  fmt(mins: number | null): string {
    if (!mins) return '—';
    const h = Math.floor(mins / 60), m = mins % 60;
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
  }

  statusClass(s: string): string {
    return ({ present: 'badge-green', late: 'badge-yellow',
              absent: 'badge-red', half_day: 'badge-orange',
              no_record: 'badge-gray' } as any)[s] ?? 'badge-gray';
  }

  statusIcon(s: string): string {
    return ({ present: 'check_circle', late: 'schedule',
              absent: 'cancel', no_record: 'radio_button_unchecked',
              half_day: 'timelapse' } as any)[s] ?? 'remove';
  }

  presencePct(): number {
    const s = this.data?.summary;
    if (!s?.total_active) return 0;
    return Math.round(((s.present_today + s.late_today) / s.total_active) * 100);
  }

  // ── Charts ───────────────────────────────────────────────────────────

  private renderCharts(): void {
    this.destroyCharts();
    if (this.isAdmin) {
      this.renderWeeklyAdminChart();
      this.renderDeptChart();
    } else {
      this.renderWeeklyEmployeeChart();
    }
  }

  private renderWeeklyAdminChart(): void {
    const el = document.getElementById('adminWeekChart') as HTMLCanvasElement;
    if (!el || !this.data?.weekly_trend) return;
    const labels  = this.data.weekly_trend.map((d: any) => d.day);
    const present = this.data.weekly_trend.map((d: any) => d.present);
    const absent  = this.data.weekly_trend.map((d: any) => d.absent);
    this.charts.push(new Chart(el, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          { label: 'Present', data: present, backgroundColor: 'rgba(16,185,129,0.75)', borderRadius: 6 },
          { label: 'Absent',  data: absent,  backgroundColor: 'rgba(239,68,68,0.55)',  borderRadius: 6 },
        ],
      },
      options: this.barOpts(),
    }));
  }

  private renderDeptChart(): void {
    const el = document.getElementById('deptChart') as HTMLCanvasElement;
    if (!el || !this.data?.dept_breakdown?.length) return;
    const labels  = this.data.dept_breakdown.map((d: any) => d.department);
    const present = this.data.dept_breakdown.map((d: any) => d.present);
    const absent  = this.data.dept_breakdown.map((d: any) => d.absent);
    this.charts.push(new Chart(el, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          { label: 'Present', data: present, backgroundColor: 'rgba(59,130,246,0.75)', borderRadius: 6 },
          { label: 'Absent',  data: absent,  backgroundColor: 'rgba(239,68,68,0.55)',  borderRadius: 6 },
        ],
      },
      options: { ...this.barOpts(), indexAxis: 'y' as const },
    }));
  }

  private renderWeeklyEmployeeChart(): void {
    const el = document.getElementById('empWeekChart') as HTMLCanvasElement;
    if (!el || !this.data?.weekly) return;
    const labels = this.data.weekly.map((d: any) => d.day);
    const hours  = this.data.weekly.map((d: any) =>
      d.total_minutes ? +(d.total_minutes / 60).toFixed(1) : 0
    );
    const colors = this.data.weekly.map((d: any) => {
      if (d.status === 'present') return 'rgba(16,185,129,0.75)';
      if (d.status === 'late')    return 'rgba(245,158,11,0.75)';
      if (d.status === 'absent')  return 'rgba(239,68,68,0.55)';
      return 'rgba(72,79,88,0.4)';
    });
    this.charts.push(new Chart(el, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Hours worked',
          data:  hours,
          backgroundColor: colors,
          borderRadius: 8,
        }],
      },
      options: {
        ...this.barOpts(),
        scales: {
          y: { ...this.axisStyle(), title: { display: true, text: 'Hours', color: '#484f58', font: { size: 11 } } },
          x: { ...this.axisStyle() },
        },
      },
    }));
  }

  private barOpts(): any {
    return {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { labels: { color: '#8b949e', font: { size: 11 }, boxWidth: 12 } },
        tooltip: { backgroundColor: '#161b22', titleColor: '#e6edf3', bodyColor: '#8b949e', borderColor: '#21262d', borderWidth: 1 },
      },
      scales: {
        y: this.axisStyle(),
        x: this.axisStyle(),
      },
    };
  }

  private axisStyle(): any {
    return {
      grid:  { color: 'rgba(33,38,45,0.8)' },
      ticks: { color: '#484f58', font: { size: 11 } },
    };
  }

  private destroyCharts(): void {
    this.charts.forEach(c => { try { c.destroy(); } catch (_) {} });
    this.charts = [];
  }

  ngOnDestroy(): void { this.destroyCharts(); this.destroy$.next(); this.destroy$.complete(); }
}
