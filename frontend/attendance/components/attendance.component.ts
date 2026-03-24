import { Component, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { AuthService } from '../../../core/services/auth.service';
import { interval, Subscription } from 'rxjs';

/**
 * AttendanceComponent
 *
 * Main attendance management component providing:
 *  - Employee self check-in / check-out with live clock
 *  - Monthly personal attendance calendar with summary
 *  - HR daily snapshot (who is in/out/absent)
 *  - HR attendance report with filters, pagination, export
 *  - HR manual entry / edit / delete of records
 *
 * @example
 * <app-attendance></app-attendance>
 */
@Component({
  standalone: false,
  selector: 'app-attendance',
  templateUrl: './attendance.component.html',
  styleUrls: ['./attendance.component.scss'],
})
export class AttendanceComponent implements OnInit, OnDestroy {

  // ── Tabs ─────────────────────────────────────────────────────────────
  activeTab = 'today';

  /** Tab definitions — visibility controlled by role */
  tabs = [
    { id: 'today',    label: 'Today',       icon: 'today'          },
    { id: 'my-log',   label: 'My Attendance',icon: 'calendar_month' },
    { id: 'daily',    label: 'Daily View',  icon: 'groups',        hrOnly: true },
    { id: 'report',   label: 'Report',      icon: 'assessment',    hrOnly: true },
  ];

  // ── Live clock ───────────────────────────────────────────────────────
  /** Current time string displayed on the check-in card */
  currentTime = '';
  currentDate = '';
  private clockSub?: Subscription;

  // ── Today tab ────────────────────────────────────────────────────────
  todayLog:     any   = null;
  todayLoading        = false;
  checkingIn          = false;
  checkingOut         = false;
  todayError          = '';

  // ── My Log tab ───────────────────────────────────────────────────────
  myLogs:       any[] = [];
  mySummary:    any   = {};
  myLogLoading        = false;
  myLogMonth          = new Date().getMonth() + 1;
  myLogYear           = new Date().getFullYear();

  /** Full calendar grid (null = filler day, object = log or empty day) */
  calendarDays: (any | null)[] = [];

  // ── Daily tab ────────────────────────────────────────────────────────
  dailyRecords: any[] = [];
  dailyStats:   any   = {};
  dailyDate           = new Date().toISOString().slice(0, 10);
  dailyDeptFilter     = '';
  dailyLoading        = false;
  dailySearch         = '';

  // ── Report tab ───────────────────────────────────────────────────────
  reportLogs:   any[] = [];
  reportPagination: any = null;
  reportLoading       = false;
  reportPage          = 1;

  reportFilters = {
    date_from:     new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10),
    date_to:       new Date().toISOString().slice(0, 10),
    department_id: '',
    employee_id:   '',
    status:        '',
  };

  reportColumns = ['employee', 'date', 'check_in', 'check_out', 'duration', 'status', 'source', 'actions'];

  // ── Manual entry modal ───────────────────────────────────────────────
  showManualForm      = false;
  manualSaving        = false;
  manualError         = '';
  manualEditId: number | null = null;
  manualForm = {
    employee_id: '',
    date:        new Date().toISOString().slice(0, 10),
    check_in:    '',
    check_out:   '',
    status:      'present',
    notes:       '',
  };

  // ── Reference data ───────────────────────────────────────────────────
  departments: any[] = [];
  employees:   any[] = [];

  readonly statusOptions = [
    { value: 'present',  label: 'Present',  color: '#10b981' },
    { value: 'late',     label: 'Late',     color: '#f59e0b' },
    { value: 'absent',   label: 'Absent',   color: '#ef4444' },
    { value: 'half_day', label: 'Half Day', color: '#6366f1' },
    { value: 'on_leave', label: 'On Leave', color: '#0ea5e9' },
    { value: 'holiday',  label: 'Holiday',  color: '#8b949e' },
  ];

  readonly months = [
    'January','February','March','April','May','June',
    'July','August','September','October','November','December',
  ];

  constructor(
    private http: HttpClient,
    public  auth: AuthService,
  ) {}

  // ── Lifecycle ─────────────────────────────────────────────────────────

  ngOnInit(): void {
    this.startClock();
    this.loadToday();
    this.loadDepartments();
    this.loadEmployees();
  }

  ngOnDestroy(): void {
    this.clockSub?.unsubscribe();
  }

  // ── Clock ─────────────────────────────────────────────────────────────

  /** Start the 1-second interval that updates the live clock display. */
  private startClock(): void {
    const tick = () => {
      const now = new Date();
      this.currentTime = now.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
      this.currentDate = now.toLocaleDateString('en-GB', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    };
    tick();
    this.clockSub = interval(1000).subscribe(tick);
  }

  // ── Tab switching ─────────────────────────────────────────────────────

  /**
   * Switch to a tab and lazy-load its data if not yet loaded.
   * @param id  Tab identifier
   */
  switchTab(id: string): void {
    this.activeTab = id;
    if (id === 'my-log' && !this.myLogs.length) this.loadMyLog();
    if (id === 'daily')                          this.loadDaily();
    if (id === 'report' && !this.reportLogs.length) this.loadReport();
  }

  // ── Today ─────────────────────────────────────────────────────────────

  /** Load today's attendance status for the current user. */
  loadToday(): void {
    this.todayLoading = true;
    this.todayError   = '';
    this.http.get<any>('/api/v1/attendance/today').subscribe({
      next:  r => { this.todayLog = r.log; this.todayLoading = false; },
      error: e => { this.todayError = e?.error?.message || 'Could not load attendance.'; this.todayLoading = false; },
    });
  }

  /** Record check-in for the authenticated user. */
  checkIn(): void {
    this.checkingIn = true;
    this.todayError = '';
    this.http.post<any>('/api/v1/attendance/checkin', {}).subscribe({
      next:  r => { this.todayLog = r.log; this.checkingIn = false; },
      error: e => { this.todayError = e?.error?.message || 'Check-in failed.'; this.checkingIn = false; },
    });
  }

  /** Record check-out for the authenticated user. */
  checkOut(): void {
    this.checkingOut = true;
    this.todayError  = '';
    this.http.post<any>('/api/v1/attendance/checkout', {}).subscribe({
      next:  r => { this.todayLog = r.log; this.checkingOut = false; },
      error: e => { this.todayError = e?.error?.message || 'Check-out failed.'; this.checkingOut = false; },
    });
  }

  // ── My Log ────────────────────────────────────────────────────────────

  /** Load current user's monthly attendance log. */
  loadMyLog(): void {
    this.myLogLoading = true;
    this.http.get<any>('/api/v1/attendance/my-log', {
      params: { month: this.myLogMonth, year: this.myLogYear },
    }).subscribe({
      next: r => {
        this.myLogs    = r.logs;
        this.mySummary = r.summary;
        this.buildCalendar(r.logs, this.myLogMonth, this.myLogYear);
        this.myLogLoading = false;
      },
      error: () => this.myLogLoading = false,
    });
  }

  /** Navigate to previous month. */
  prevMonth(): void {
    if (this.myLogMonth === 1) { this.myLogMonth = 12; this.myLogYear--; }
    else this.myLogMonth--;
    this.loadMyLog();
  }

  /** Navigate to next month (cannot go into the future). */
  nextMonth(): void {
    const now = new Date();
    if (this.myLogYear > now.getFullYear() || (this.myLogYear === now.getFullYear() && this.myLogMonth >= now.getMonth() + 1)) return;
    if (this.myLogMonth === 12) { this.myLogMonth = 1; this.myLogYear++; }
    else this.myLogMonth++;
    this.loadMyLog();
  }

  /**
   * Build a 7-column calendar grid from log data.
   * Null slots represent empty cells before the 1st of the month.
   */
  private buildCalendar(logs: any[], month: number, year: number): void {
    const firstDay = new Date(year, month - 1, 1).getDay(); // 0=Sun
    const daysInMonth = new Date(year, month, 0).getDate();
    const logMap: Record<string, any> = {};
    logs.forEach(l => logMap[l.date] = l);

    this.calendarDays = [];

    // Filler for offset (Mon-start)
    const offset = (firstDay === 0 ? 6 : firstDay - 1);
    for (let i = 0; i < offset; i++) this.calendarDays.push(null);

    for (let d = 1; d <= daysInMonth; d++) {
      const dateStr = `${year}-${String(month).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
      const dayOfWeek = new Date(year, month - 1, d).getDay();
      this.calendarDays.push({
        day:     d,
        date:    dateStr,
        isToday: dateStr === new Date().toISOString().slice(0, 10),
        isWeekend: dayOfWeek === 0 || dayOfWeek === 6,
        isFuture: dateStr > new Date().toISOString().slice(0, 10),
        log:     logMap[dateStr] || null,
      });
    }
  }

  // ── Daily view ────────────────────────────────────────────────────────

  /** Load today's daily snapshot or a specific date. */
  loadDaily(): void {
    this.dailyLoading = true;
    const params: any = { date: this.dailyDate };
    if (this.dailyDeptFilter) params.department_id = this.dailyDeptFilter;
    this.http.get<any>('/api/v1/attendance/daily', { params }).subscribe({
      next: r => {
        this.dailyRecords = r.records;
        this.dailyStats   = { total: r.total, present: r.present, late: r.late, absent: r.absent, on_leave: r.on_leave };
        this.dailyLoading = false;
      },
      error: () => this.dailyLoading = false,
    });
  }

  /** Filtered daily records by search query. */
  get filteredDailyRecords(): any[] {
    if (!this.dailySearch) return this.dailyRecords;
    const q = this.dailySearch.toLowerCase();
    return this.dailyRecords.filter(r =>
      r.employee?.first_name?.toLowerCase().includes(q) ||
      r.employee?.last_name?.toLowerCase().includes(q)  ||
      r.employee?.employee_code?.toLowerCase().includes(q)
    );
  }

  // ── Report ────────────────────────────────────────────────────────────

  /** Load paginated HR attendance report. */
  loadReport(page = 1): void {
    this.reportLoading = true;
    this.reportPage    = page;
    const params: any  = { ...this.reportFilters, per_page: 50, page };
    // Remove empty keys
    Object.keys(params).forEach(k => !params[k] && delete params[k]);
    this.http.get<any>('/api/v1/attendance/report', { params }).subscribe({
      next: r => {
        this.reportLogs       = r.data;
        this.reportPagination = r;
        this.reportLoading    = false;
      },
      error: () => this.reportLoading = false,
    });
  }

  applyReportFilters(): void { this.loadReport(1); }

  clearReportFilters(): void {
    this.reportFilters = {
      date_from:     new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10),
      date_to:       new Date().toISOString().slice(0, 10),
      department_id: '',
      employee_id:   '',
      status:        '',
    };
    this.loadReport(1);
  }

  get reportPages(): number[] {
    if (!this.reportPagination?.last_page) return [];
    return Array.from({ length: Math.min(this.reportPagination.last_page, 8) }, (_, i) => i + 1);
  }

  // ── Manual entry ─────────────────────────────────────────────────────

  /** Open the manual entry form (create or edit). */
  openManualForm(log?: any): void {
    this.manualEditId = log?.id ?? null;
    this.manualError  = '';
    this.manualForm   = log ? {
      employee_id: log.employee_id,
      date:        log.date?.slice(0, 10) ?? log.date,
      check_in:    log.check_in?.slice(0, 5) ?? '',
      check_out:   log.check_out?.slice(0, 5) ?? '',
      status:      log.status,
      notes:       log.notes ?? '',
    } : {
      employee_id: '',
      date:        new Date().toISOString().slice(0, 10),
      check_in:    '',
      check_out:   '',
      status:      'present',
      notes:       '',
    };
    this.showManualForm = true;
  }

  /** Submit the manual entry form. */
  saveManual(): void {
    if (!this.manualForm.employee_id) { this.manualError = 'Employee is required.'; return; }
    if (!this.manualForm.date)        { this.manualError = 'Date is required.'; return; }

    this.manualSaving = true;
    this.manualError  = '';

    const req = this.manualEditId
      ? this.http.put<any>(`/api/v1/attendance/logs/${this.manualEditId}`, this.manualForm)
      : this.http.post<any>('/api/v1/attendance/manual', this.manualForm);

    req.subscribe({
      next: () => {
        this.manualSaving   = false;
        this.showManualForm = false;
        // Refresh whichever tab is active
        if (this.activeTab === 'daily')  this.loadDaily();
        if (this.activeTab === 'report') this.loadReport(this.reportPage);
      },
      error: err => {
        this.manualSaving = false;
        const errors = err?.error?.errors;
        this.manualError = errors
          ? Object.values(errors).flat().join(' ')
          : err?.error?.message || 'Failed to save record.';
      },
    });
  }

  /** Delete an attendance log record. */
  deleteLog(log: any): void {
    if (!confirm(`Delete attendance record for ${log.employee?.first_name ?? 'this employee'} on ${log.date}?`)) return;
    this.http.delete(`/api/v1/attendance/logs/${log.id}`).subscribe({
      next: () => {
        this.reportLogs   = this.reportLogs.filter(l => l.id !== log.id);
        this.dailyRecords = this.dailyRecords.map(r => r.log_id === log.id ? { ...r, status:'absent', check_in: null, check_out: null, log_id: null } : r);
      },
      error: err => alert(err?.error?.message || 'Delete failed.'),
    });
  }

  // ── Reference data ────────────────────────────────────────────────────

  /** Load all departments for filter dropdowns. */
  private loadDepartments(): void {
    this.http.get<any>('/api/v1/departments').subscribe({
      next: r => this.departments = Array.isArray(r) ? r : (r?.data ?? []),
    });
  }

  /** Load employees for manual entry dropdown. */
  private loadEmployees(): void {
    this.http.get<any>('/api/v1/employees?per_page=500').subscribe({
      next: r => this.employees = r?.data ?? [],
    });
  }

  // ── UI Helpers ────────────────────────────────────────────────────────

  /** CSS class for a status badge. */
  statusCls(status: string): string {
    return ({
      present:  'badge-green',
      late:     'badge-yellow',
      absent:   'badge-red',
      half_day: 'badge-purple',
      on_leave: 'badge-blue',
      holiday:  'badge-gray',
    } as Record<string, string>)[status] ?? 'badge-gray';
  }

  /** Material icon for a status. */
  statusIcon(status: string): string {
    return ({
      present:  'check_circle',
      late:     'schedule',
      absent:   'cancel',
      half_day: 'timelapse',
      on_leave: 'beach_access',
      holiday:  'celebration',
    } as Record<string, string>)[status] ?? 'help';
  }

  /** Human-readable status label. */
  statusLabel(status: string): string {
    return ({
      present:  'Present',
      late:     'Late',
      absent:   'Absent',
      half_day: 'Half Day',
      on_leave: 'On Leave',
      holiday:  'Holiday',
    } as Record<string, string>)[status] ?? status;
  }

  /** Source icon for audit trail display. */
  sourceIcon(source: string): string {
    return ({ api: 'phone_android', manual: 'edit', biometric: 'fingerprint', import: 'upload_file' } as any)[source] ?? 'device_unknown';
  }

  /** Avatar background colour derived from name. */
  avatarColor(name: string): string {
    const colors = ['#3b82f6','#6366f1','#8b5cf6','#ec4899','#10b981','#f59e0b','#ef4444','#0ea5e9'];
    return colors[(name?.charCodeAt(0) ?? 0) % colors.length];
  }

  /** Check whether the current user has HR-level access. */
  isHR(): boolean {
    return this.auth.hasAnyRole(['super_admin','hr_manager','hr_staff','department_manager']);
  }

  /** Visible tabs filtered by role. */
  get visibleTabs(): typeof this.tabs {
    return this.tabs.filter(t => !t.hrOnly || this.isHR());
  }

  /** Current year for next-month boundary check. */
  get currentYear():  number { return new Date().getFullYear(); }
  /** Current month (1-based) for next-month boundary check. */
  get currentMonth(): number { return new Date().getMonth() + 1; }

  /** Calendar week-day header labels. */
  readonly weekDays = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];

  /** Current month label for the calendar header. */
  get monthLabel(): string {
    return `${this.months[this.myLogMonth - 1]} ${this.myLogYear}`;
  }

  readonly months = ['January','February','March','April','May','June','July','August','September','October','November','December'];

  /** Progress percentage for summary stat bars. */
  pct(value: number, total: number): number {
    return total ? Math.round((value / total) * 100) : 0;
  }
}
