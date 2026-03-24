import {
  Component, OnInit, OnDestroy,
  ChangeDetectionStrategy, ChangeDetectorRef,
} from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
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
  notes?:        string;
}

export interface AttendanceSettings {
  work_start:          string;   // HH:MM
  late_after_minutes:  number;
  half_day_hours:      number;
  full_day_hours:      number;
  grace_minutes:       number;
  weekend_days:        number[];
}

@Component({
  standalone:      false,
  selector:        'app-attendance',
  templateUrl:     './attendance.component.html',
  styleUrls:       ['./attendance.component.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AttendanceComponent implements OnInit, OnDestroy {

  // ── State ─────────────────────────────────────────────────────────────
  todayLog:    AttendanceLog | null = null;
  reportRows:  any[] = [];
  departments: any[] = [];

  loading       = true;
  reportLoading = false;
  checkingIn    = false;
  checkingOut   = false;
  errorMsg      = '';
  successMsg    = '';
  clock         = '';

  // ── Views ─────────────────────────────────────────────────────────────
  isHR             = false;
  showSettings     = false;
  showManualEntry  = false;
  editingRow: any  = null;   // the row being inline-edited
  savingEdit       = false;
  savingManual     = false;
  settingsSaving   = false;
  settingsSaved    = false;

  // ── Settings ──────────────────────────────────────────────────────────
  settings: AttendanceSettings = {
    work_start:         '08:00',
    late_after_minutes: 15,
    half_day_hours:     4,
    full_day_hours:     8,
    grace_minutes:      5,
    weekend_days:       [5, 6],
  };
  settingsForm!: FormGroup;

  // ── Filters ───────────────────────────────────────────────────────────
  filterForm!:   FormGroup;
  manualForm!:   FormGroup;
  editForm!:     FormGroup;

  readonly statusOptions = [
    { value: 'present',  label: 'Present',  color: '#10b981' },
    { value: 'late',     label: 'Late',     color: '#f59e0b' },
    { value: 'absent',   label: 'Absent',   color: '#ef4444' },
    { value: 'half_day', label: 'Half Day', color: '#fb923c' },
    { value: 'on_leave', label: 'On Leave', color: '#6366f1' },
    { value: 'holiday',  label: 'Holiday',  color: '#8b949e' },
  ];

  readonly weekDays = [
    { value: 0, label: 'Sun' }, { value: 1, label: 'Mon' },
    { value: 2, label: 'Tue' }, { value: 3, label: 'Wed' },
    { value: 4, label: 'Thu' }, { value: 5, label: 'Fri' },
    { value: 6, label: 'Sat' },
  ];

  private readonly api      = '/api/v1/attendance';
  private readonly destroy$ = new Subject<void>();

  constructor(
    private readonly http: HttpClient,
    private readonly fb:   FormBuilder,
    private readonly auth: AuthService,
    private readonly cdr:  ChangeDetectorRef,
  ) {}

  ngOnInit(): void {
    // Multi-strategy role check — guards against Collection serialisation issues
    // from older login tokens where roles may be {"0":"super_admin"} instead of ["super_admin"]
    const user = this.auth.getUser();
    const roles       = user?.roles       ?? {};
    const permissions = user?.permissions ?? {};

    const roleValues  = Array.isArray(roles)       ? roles       : Object.values(roles);
    const permValues  = Array.isArray(permissions) ? permissions : Object.values(permissions);

    const hrRoles = ['super_admin','hr_manager','hr_staff'];
    const hrPerms = ['manage_attendance','view_attendance'];

    this.isHR = hrRoles.some((r: string) => roleValues.includes(r))
             || hrPerms.some((p: string) => permValues.includes(p));

    this.filterForm = this.fb.group({
      date_from:     [this.firstOfMonth()],
      date_to:       [this.todayStr()],
      department_id: [''],
      status:        [''],
    });

    this.settingsForm = this.fb.group({
      work_start:         ['08:00', Validators.required],
      late_after_minutes: [15,  [Validators.required, Validators.min(0), Validators.max(120)]],
      half_day_hours:     [4,   [Validators.required, Validators.min(1), Validators.max(12)]],
      full_day_hours:     [8,   [Validators.required, Validators.min(4), Validators.max(24)]],
      grace_minutes:      [5,   [Validators.required, Validators.min(0), Validators.max(60)]],
      weekend_days:       [[5, 6]],
    });

    this.manualForm = this.fb.group({
      employee_id: ['', Validators.required],
      date:        [this.todayStr(), Validators.required],
      check_in:    [''],
      check_out:   [''],
      status:      ['present', Validators.required],
      notes:       [''],
    });

    this.editForm = this.fb.group({
      check_in:  [''],
      check_out: [''],
      status:    ['', Validators.required],
      notes:     [''],
    });

    this.loadToday();
    this.loadReport();
    this.loadSettings();
    if (this.isHR) {
      this.loadDepartments();
    }

    interval(1000).pipe(takeUntil(this.destroy$)).subscribe(() => {
      this.clock = new Date().toLocaleTimeString('en-GB', {
        hour: '2-digit', minute: '2-digit', second: '2-digit',
      });
      this.cdr.markForCheck();
    });
  }

  // ── Today ──────────────────────────────────────────────────────────────

  loadToday(): void {
    this.loading = true;
    this.http.get<any>(`${this.api}/today`).pipe(takeUntil(this.destroy$)).subscribe({
      next:  (r) => { this.todayLog = r.log ?? null; this.loading = false; this.cdr.markForCheck(); },
      error: () => { this.loading = false; this.cdr.markForCheck(); },
    });
  }

  checkIn(): void {
    this.checkingIn = true; this.clearMessages();
    this.http.post<any>(`${this.api}/checkin`, {}).pipe(takeUntil(this.destroy$)).subscribe({
      next: (r) => {
        this.todayLog   = r.log;
        this.successMsg = `Checked in at ${r.log?.check_in}`;
        this.checkingIn = false;
        this.loadReport(); this.cdr.markForCheck();
      },
      error: (err) => {
        if (err?.error?.log) this.todayLog = err.error.log;
        this.errorMsg   = err?.error?.message ?? 'Check-in failed.';
        this.checkingIn = false; this.cdr.markForCheck();
      },
    });
  }

  checkOut(): void {
    this.checkingOut = true; this.clearMessages();
    this.http.post<any>(`${this.api}/checkout`, {}).pipe(takeUntil(this.destroy$)).subscribe({
      next: (r) => {
        this.todayLog    = r.log;
        this.successMsg  = `Checked out at ${r.log?.check_out} · ${this.fmt(r.log?.total_minutes)}`;
        this.checkingOut = false;
        this.loadReport(); this.cdr.markForCheck();
      },
      error: (err) => {
        if (err?.error?.log) this.todayLog = err.error.log;
        this.errorMsg    = err?.error?.message ?? 'Check-out failed.';
        this.checkingOut = false; this.cdr.markForCheck();
      },
    });
  }

  // ── Report ─────────────────────────────────────────────────────────────

  loadReport(): void {
    this.reportLoading = true;
    this.http.get<any>(`${this.api}/report`, { params: this.clean(this.filterForm.value) })
      .pipe(takeUntil(this.destroy$)).subscribe({
        next:  (r) => { this.reportRows = r.data ?? []; this.reportLoading = false; this.cdr.markForCheck(); },
        error: () => { this.reportLoading = false; this.cdr.markForCheck(); },
      });
  }

  applyFilters(): void { this.loadReport(); }

  loadDepartments(): void {
    this.http.get<any>('/api/v1/departments').pipe(takeUntil(this.destroy$)).subscribe({
      next: (r) => { this.departments = r?.data ?? r ?? []; this.cdr.markForCheck(); },
      error: () => {},
    });
  }

  // ── Edit record ────────────────────────────────────────────────────────

  openEdit(row: any): void {
    this.editingRow = row;
    this.editForm.patchValue({
      check_in:  row.check_in  ?? '',
      check_out: row.check_out ?? '',
      status:    row.status    ?? 'present',
      notes:     row.notes     ?? '',
    });
    this.cdr.markForCheck();
  }

  cancelEdit(): void { this.editingRow = null; this.cdr.markForCheck(); }

  saveEdit(): void {
    if (this.editForm.invalid || !this.editingRow) return;
    this.savingEdit = true;
    this.http.put<any>(`${this.api}/${this.editingRow.id}`, this.editForm.value)
      .pipe(takeUntil(this.destroy$)).subscribe({
        next: (r) => {
          // Update row in place
          const idx = this.reportRows.findIndex(x => x.id === this.editingRow.id);
          if (idx > -1) this.reportRows[idx] = { ...this.reportRows[idx], ...r.log };
          this.editingRow = null;
          this.savingEdit = false;
          this.successMsg = 'Record updated.';
          setTimeout(() => { this.successMsg = ''; this.cdr.markForCheck(); }, 3000);
          this.cdr.markForCheck();
        },
        error: (err) => {
          this.errorMsg   = err?.error?.message ?? 'Update failed.';
          this.savingEdit = false; this.cdr.markForCheck();
        },
      });
  }

  // ── Manual entry ───────────────────────────────────────────────────────

  submitManual(): void {
    if (this.manualForm.invalid) return;
    this.savingManual = true;
    this.http.post<any>(`${this.api}/manual`, this.manualForm.value)
      .pipe(takeUntil(this.destroy$)).subscribe({
        next: () => {
          this.showManualEntry = false;
          this.savingManual    = false;
          this.successMsg      = 'Manual entry saved.';
          this.loadReport();
          setTimeout(() => { this.successMsg = ''; this.cdr.markForCheck(); }, 3000);
          this.cdr.markForCheck();
        },
        error: (err) => {
          this.errorMsg    = err?.error?.message ?? 'Manual entry failed.';
          this.savingManual = false; this.cdr.markForCheck();
        },
      });
  }

  // ── Settings ───────────────────────────────────────────────────────────

  loadSettings(): void {
    this.http.get<AttendanceSettings>(`${this.api}/settings`)
      .pipe(takeUntil(this.destroy$)).subscribe({
        next: (s) => {
          this.settings = s;
          this.settingsForm.patchValue(s);
          this.cdr.markForCheck();
        },
        error: () => {},
      });
  }

  saveSettings(): void {
    if (this.settingsForm.invalid) return;
    this.settingsSaving = true;
    this.http.post<any>(`${this.api}/settings`, this.settingsForm.value)
      .pipe(takeUntil(this.destroy$)).subscribe({
        next: (r) => {
          this.settings    = r.settings;
          this.settingsSaving = false;
          this.settingsSaved  = true;
          setTimeout(() => { this.settingsSaved = false; this.cdr.markForCheck(); }, 3000);
          this.cdr.markForCheck();
        },
        error: () => { this.settingsSaving = false; this.cdr.markForCheck(); },
      });
  }

  toggleWeekend(day: number): void {
    const curr: number[] = this.settingsForm.value.weekend_days ?? [];
    const updated = curr.includes(day) ? curr.filter(d => d !== day) : [...curr, day];
    this.settingsForm.patchValue({ weekend_days: updated });
  }

  isWeekend(day: number): boolean {
    return (this.settingsForm.value.weekend_days ?? []).includes(day);
  }

  // ── Helpers ────────────────────────────────────────────────────────────

  get canCheckIn():  boolean { return !this.todayLog?.check_in; }
  get canCheckOut(): boolean { return !!this.todayLog?.check_in && !this.todayLog?.check_out; }

  fmt(mins: number | null | undefined): string {
    if (!mins) return '—';
    const h = Math.floor(mins / 60), m = mins % 60;
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
  }

  statusClass(s: string): string {
    return ({ present: 'badge-green', late: 'badge-yellow', absent: 'badge-red',
              half_day: 'badge-orange', on_leave: 'badge-purple', holiday: 'badge-gray' } as any)[s] ?? 'badge-gray';
  }

  /** Determine if a check-in time would be considered late given current settings. */
  isLate(checkIn: string | null): boolean {
    if (!checkIn) return false;
    const [sh, sm] = this.settings.work_start.split(':').map(Number);
    const threshold = sh * 60 + sm + this.settings.late_after_minutes;
    const [ch, cm] = checkIn.split(':').map(Number);
    return ch * 60 + cm > threshold;
  }

  todayStr(): string {
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
