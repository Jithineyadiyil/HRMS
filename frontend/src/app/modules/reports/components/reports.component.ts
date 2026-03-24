import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';

interface ReportDef {
  id:          string;
  label:       string;
  icon:        string;
  color:       string;
  description: string;
  filters:     string[];  // which filter fields apply
  columns:     string[];  // preview column keys
  headers:     string[];  // human-readable headers
}

@Component({
  standalone: false,
  selector: 'app-reports',
  templateUrl: './reports.component.html',
  styleUrls: ['./reports.component.scss'],
})
export class ReportsComponent implements OnInit {

  // ── State ──────────────────────────────────────────────────────────────
  activeReport: ReportDef | null = null;
  previewData:  any[]  = [];
  previewTotal  = 0;
  loading       = false;
  downloading   = false;
  previewLoaded = false;
  error         = '';

  // ── Filters ────────────────────────────────────────────────────────────
  filters: any = {
    month:          this.currentMonth(),
    year:           new Date().getFullYear(),
    from:           '',
    to:             '',
    department_id:  '',
    status:         '',
    leave_type_id:  '',
    annual_only:    true,
  };

  // ── Lookups ────────────────────────────────────────────────────────────
  departments: any[] = [];
  leaveTypes:  any[] = [];

  readonly YEARS = Array.from({length: 5}, (_, i) => new Date().getFullYear() - i);

  readonly REPORTS: ReportDef[] = [
    {
      id:          'employees',
      label:       'Employee Report',
      icon:        'people',
      color:       '#3b82f6',
      description: 'Full employee roster with department, designation, hire date, nationality and salary details.',
      filters:     ['department', 'status', 'from', 'to'],
      columns:     ['code','name','department','designation','hire_date','nationality','status','salary'],
      headers:     ['Code','Name','Department','Designation','Hire Date','Nationality','Status','Basic Salary'],
    },
    {
      id:          'payroll',
      label:       'Payroll Report',
      icon:        'payments',
      color:       '#10b981',
      description: 'Monthly salary register with breakdown of basic, allowances, GOSI and net pay.',
      filters:     ['month', 'department'],
      columns:     ['code','name','department','basic','housing','transport','gross','gosi','deductions','net'],
      headers:     ['Code','Name','Dept','Basic','Housing','Transport','Gross','GOSI','Deductions','Net'],
    },
    {
      id:          'leave-balance',
      label:       'Leave Balance Report',
      icon:        'event_available',
      color:       '#6366f1',
      description: 'Annual Leave balance per employee — entitlement, days used, pending and remaining.',
      filters:     ['year', 'department'],
      columns:     ['code','name','department','entitlement','used','pending','remaining'],
      headers:     ['Code','Name','Department','Entitlement','Used','Pending','Remaining'],
    },
    {
      id:          'leave-requests',
      label:       'Leave Requests Report',
      icon:        'event_note',
      color:       '#f59e0b',
      description: 'All leave requests in the selected period, filterable by status, type and department.',
      filters:     ['from', 'to', 'department', 'status', 'leave_type'],
      columns:     ['code','name','department','leave_type','from','to','days','status'],
      headers:     ['Code','Name','Dept','Type','From','To','Days','Status'],
    },
    {
      id:          'attendance',
      label:       'Attendance Report',
      icon:        'fingerprint',
      color:       '#0ea5e9',
      description: 'Daily attendance log for the selected month — check-in/out times, hours worked and status.',
      filters:     ['month', 'department', 'status'],
      columns:     ['code','name','department','date','check_in','check_out','hours','status'],
      headers:     ['Code','Name','Dept','Date','In','Out','Hours','Status'],
    },
    {
      id:          'loans',
      label:       'Loan Report',
      icon:        'account_balance',
      color:       '#f97316',
      description: 'Active and historical employee loans — principal, outstanding balance and repayment progress.',
      filters:     ['department', 'status', 'from', 'to'],
      columns:     ['code','name','department','loan_type','amount','outstanding','installment','installments','status'],
      headers:     ['Code','Name','Dept','Type','Amount','Outstanding','Installment','Progress','Status'],
    },
  ];

  readonly EMP_STATUSES    = ['active','inactive','probation','on_leave','terminated'];
  readonly LEAVE_STATUSES  = ['pending','approved','rejected','cancelled'];
  readonly ATT_STATUSES    = ['present','absent','late','holiday','off'];
  readonly LOAN_STATUSES   = ['pending','active','settled','rejected'];

  constructor(private http: HttpClient) {}

  ngOnInit() {
    this.loadDepts();
    this.loadLeaveTypes();
    // Select first report by default
    this.selectReport(this.REPORTS[0]);
  }

  // ── Report selection ───────────────────────────────────────────────────

  selectReport(r: ReportDef) {
    if (this.activeReport?.id === r.id) return;
    this.activeReport  = r;
    this.previewData   = [];
    this.previewLoaded = false;
    this.error         = '';
  }

  hasFilter(f: string): boolean {
    return this.activeReport?.filters.includes(f) ?? false;
  }

  // ── Preview ────────────────────────────────────────────────────────────

  runPreview() {
    if (!this.activeReport) return;
    this.loading       = true;
    this.previewLoaded = false;
    this.error         = '';

    this.http.get<any>(`/api/v1/reports/${this.activeReport.id}`, { params: this.buildParams() }).subscribe({
      next: r => {
        this.previewData   = r.data || [];
        this.previewTotal  = r.total || this.previewData.length;
        this.loading       = false;
        this.previewLoaded = true;
      },
      error: err => {
        this.loading = false;
        this.error   = err?.error?.message || 'Failed to load report. Please check filters.';
      },
    });
  }

  // ── Downloads ──────────────────────────────────────────────────────────

  download(format: 'csv' | 'pdf') {
    if (!this.activeReport) return;
    this.downloading = true;
    const params = this.buildParams();
    const qs     = Object.entries(params).filter(([,v]) => v !== '' && v !== null).map(([k,v]) => `${k}=${encodeURIComponent(v as string)}`).join('&');
    const url    = `/api/v1/reports/download/${this.activeReport.id}/${format}?${qs}`;

    // Use anchor + token for authenticated file download
    const token = localStorage.getItem('hrms_token') || sessionStorage.getItem('hrms_token');
    this.http.get(url, { responseType: 'blob', headers: { Authorization: `Bearer ${token}` } }).subscribe({
      next: blob => {
        const ext  = format === 'pdf' ? 'pdf' : 'csv';
        const name = `${this.activeReport!.id}_${this.yyyymmdd()}.${ext}`;
        const a    = document.createElement('a');
        a.href     = URL.createObjectURL(blob);
        a.download = name;
        a.click();
        URL.revokeObjectURL(a.href);
        this.downloading = false;
      },
      error: () => { this.downloading = false; this.error = 'Download failed. Try again.'; },
    });
  }

  // ── Helpers ────────────────────────────────────────────────────────────

  private buildParams(): any {
    const p: any = {};
    if (this.hasFilter('month')      && this.filters.month)         p.month          = this.filters.month;
    if (this.hasFilter('year')       && this.filters.year)          p.year           = this.filters.year;
    if (this.hasFilter('from')       && this.filters.from)          p.from           = this.filters.from;
    if (this.hasFilter('to')         && this.filters.to)            p.to             = this.filters.to;
    if (this.hasFilter('department') && this.filters.department_id) p.department_id  = this.filters.department_id;
    if (this.hasFilter('status')     && this.filters.status)        p.status         = this.filters.status;
    if (this.hasFilter('leave_type') && this.filters.leave_type_id) p.leave_type_id  = this.filters.leave_type_id;
    if (this.activeReport?.id === 'leave-balance')                  p.annual_only    = 1;
    return p;
  }

  private loadDepts() {
    this.http.get<any>('/api/v1/departments').subscribe({
      next: r => this.departments = Array.isArray(r) ? r : (r?.data ?? []),
    });
  }

  private loadLeaveTypes() {
    this.http.get<any>('/api/v1/leave/types').subscribe({
      next: r => this.leaveTypes = r?.types || r || [],
    });
  }

  private currentMonth(): string {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`;
  }

  private yyyymmdd(): string {
    return new Date().toISOString().slice(0,10).replace(/-/g,'');
  }

  statusCls(s: string): string {
    const m: any = {
      active:'badge-green', approved:'badge-green', present:'badge-green', settled:'badge-green',
      pending:'badge-yellow', late:'badge-yellow', on_leave:'badge-yellow', probation:'badge-yellow',
      rejected:'badge-red', absent:'badge-red', terminated:'badge-red',
      cancelled:'badge-gray', inactive:'badge-gray', draft:'badge-gray', off:'badge-gray',
    };
    return m[s?.toLowerCase()] ?? 'badge-gray';
  }

  get currentYear() { return new Date().getFullYear(); }

  getCellValue(row: any, col: string): any {
    const keys = Object.keys(row);
    // Try direct key match first
    if (row[col] !== undefined) return row[col];
    // Try index-based fallback using column position in report definition
    const idx = this.activeReport?.columns.indexOf(col) ?? -1;
    return idx > -1 ? Object.values(row)[idx] : '—';
  }
}
