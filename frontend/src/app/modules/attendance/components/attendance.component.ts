import {
  Component,
  OnInit,
  OnDestroy,
  ChangeDetectionStrategy,
  ChangeDetectorRef,
} from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { FormBuilder, FormGroup } from '@angular/forms';
import { Subject, interval } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { AuthService } from '../../../core/services/auth.service';

export interface AttendanceLog {
  id:            number;
  employee_id:   number;
  date:          string;
  check_in:      string | null;
  check_out:     string | null;
  total_minutes: number | null;
  status:        string;
  source:        string;
}

@Component({
  standalone:      false,
  selector:        'app-attendance',
  templateUrl:     './attendance.component.html',
  styleUrls:       ['./attendance.component.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AttendanceComponent implements OnInit, OnDestroy {
  todayLog:      AttendanceLog | null = null;
  reportRows:    any[]                = [];
  loading        = true;
  reportLoading  = false;
  checkingIn     = false;
  checkingOut    = false;
  errorMsg       = '';
  successMsg     = '';
  clock          = '';
  filterForm!:   FormGroup;
  isHR           = false;

  private readonly api      = '/api/v1/attendance';
  private readonly destroy$ = new Subject<void>();

  constructor(
    private readonly http: HttpClient,
    private readonly fb:   FormBuilder,
    private readonly auth: AuthService,
    private readonly cdr:  ChangeDetectorRef,
  ) {}

  ngOnInit(): void {
    this.isHR = this.auth.isHRRole();

    this.filterForm = this.fb.group({
      date_from:     [this.firstOfMonth()],
      date_to:       [this.todayStr()],
      department_id: [''],
    });

    this.loadToday();
    this.loadReport();

    interval(1000).pipe(takeUntil(this.destroy$)).subscribe(() => {
      this.clock = new Date().toLocaleTimeString('en-GB', {
        hour: '2-digit', minute: '2-digit', second: '2-digit',
      });
      this.cdr.markForCheck();
    });
  }

  // ── API calls ────────────────────────────────────────────────────────

  loadToday(): void {
    this.loading = true;
    this.http.get<{ log: AttendanceLog | null }>(`${this.api}/today`)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          this.todayLog = res.log ?? null;
          this.loading  = false;
          this.cdr.markForCheck();
        },
        error: () => {
          this.loading = false;
          this.cdr.markForCheck();
        },
      });
  }

  loadReport(): void {
    this.reportLoading = true;
    this.http.get<any>(`${this.api}/report`, { params: this.clean(this.filterForm.value) })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          this.reportRows    = res.data ?? [];
          this.reportLoading = false;
          this.cdr.markForCheck();
        },
        error: () => {
          this.reportLoading = false;
          this.cdr.markForCheck();
        },
      });
  }

  checkIn(): void {
    this.checkingIn = true;
    this.clearMessages();

    this.http.post<{ message: string; log: AttendanceLog }>(`${this.api}/checkin`, {})
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          this.todayLog   = res.log;
          this.successMsg = 'Check-in recorded successfully.';
          this.checkingIn = false;
          this.loadReport();
          this.cdr.markForCheck();
        },
        error: (err) => {
          /*
           * FIX: The backend now returns the existing log in the 422 body
           * when the employee has already checked in. Use it to populate
           * the Today card so it shows the correct times instead of "—".
           *
           * If the error body has no log (unexpected error), fall back to
           * re-fetching from GET /today to sync state.
           */
          if (err?.error?.log) {
            this.todayLog = err.error.log;
          } else {
            this.loadToday();
          }
          this.errorMsg   = err?.error?.message ?? 'Check-in failed.';
          this.checkingIn = false;
          this.cdr.markForCheck();
        },
      });
  }

  checkOut(): void {
    this.checkingOut = true;
    this.clearMessages();

    this.http.post<{ message: string; log: AttendanceLog }>(`${this.api}/checkout`, {})
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          this.todayLog    = res.log;
          this.successMsg  = 'Check-out recorded successfully.';
          this.checkingOut = false;
          this.loadReport();
          this.cdr.markForCheck();
        },
        error: (err) => {
          // Same pattern: use returned log if present, otherwise re-fetch
          if (err?.error?.log) {
            this.todayLog = err.error.log;
          } else {
            this.loadToday();
          }
          this.errorMsg    = err?.error?.message ?? 'Check-out failed.';
          this.checkingOut = false;
          this.cdr.markForCheck();
        },
      });
  }

  applyFilters(): void { this.loadReport(); }

  // ── Computed state ───────────────────────────────────────────────────

  get canCheckIn():  boolean { return !this.todayLog?.check_in; }
  get canCheckOut(): boolean { return !!this.todayLog?.check_in && !this.todayLog?.check_out; }
  get isCheckedIn(): boolean { return !!this.todayLog?.check_in && !this.todayLog?.check_out; }

  // ── Helpers ──────────────────────────────────────────────────────────

  formatMins(mins: number | null): string {
    if (!mins) return '—';
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
  }

  statusClass(status: string): string {
    const map: Record<string, string> = {
      present:  'badge-green',
      absent:   'badge-red',
      late:     'badge-yellow',
      half_day: 'badge-orange',
    };
    return map[status] ?? 'badge-gray';
  }

  private todayStr(): string {
    // Use local date parts — toISOString() is UTC and shifts date for UTC+N users
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  }

  private firstOfMonth(): string {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-01`;
  }

  private clean(obj: Record<string, any>): Record<string, string> {
    return Object.fromEntries(
      Object.entries(obj).filter(([, v]) => v !== null && v !== undefined && v !== '')
    ) as Record<string, string>;
  }

  private clearMessages(): void { this.errorMsg = ''; this.successMsg = ''; }

  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }
}
