import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { AuthService } from '../../../core/services/auth.service';

@Component({
  standalone: false,
  selector: 'app-leave-list',
  templateUrl: './leave-list.component.html',
  styleUrls: ['./leave-list.component.scss'],
})
export class LeaveListComponent implements OnInit {

  // ── State ────────────────────────────────────────────────────────────────
  activeTab     = 'requests';   // requests | calendar | balances | types
  activeStatus  = 'needs_action';
  loading       = false;
  submitting    = false;

  // Data
  requests:   any[] = [];
  leaveTypes: any[] = [];
  myBalances: any[] = [];
  allBalances:any[] = [];
  stats:      any   = {};
  calendarEvents: any[] = [];
  holidays:   any[] = [];
  pagination: any   = null;
  balancePagination: any = null;

  // Modals / drawers
  showNewRequest  = false;
  showReject      = false;
  showDetail      = false;
  showTypeForm    = false;
  showHolidayForm = false;

  selectedRequest: any  = null;
  rejectTarget:   any   = null;
  rejectReason          = '';

  // Filters
  filterSearch  = '';
  filterType    = '';
  filterDept    = '';
  currentPage   = 1;

  // Calendar
  calYear   = new Date().getFullYear();
  calMonth  = new Date().getMonth(); // 0-based
  calDays:  any[] = [];

  // Leave type form
  typeForm: any = { name:'', code:'', days_allowed:0, is_paid:true, carry_forward:false, max_carry_forward:0, requires_document:false, description:'' };
  typeEditId: number | null = null;
  typeError = '';
  typeSaving = false;

  // Holiday form
  holidayForm = { name: '', date: '', is_recurring: false };
  holidaySaving = false;

  // New request form
  form = { leave_type_id: '', start_date: '', end_date: '', start_time: '08:00', end_time: '09:00', reason: '', employee_id: '' };
  selectedFile: File | null = null;
  fileError = '';
  formError = '';

  // Department limits panel
  showLimitsPanel   = false;
  limitsLeaveType:  any   = null;
  deptLimits:       any[] = [];
  limitsLoading     = false;
  limitsSaving      = false;
  limitsError       = '';
  limitsDirty       = false;

  // Stat cards
  statItems:    any[] = [];
  excuseUsage:  any   = null;   // monthly business excuse usage
  loadingUsage  = false;

  // Table columns
  displayedColumns = ['employee', 'type', 'dates', 'days', 'reason', 'status', 'actions'];
  isHR  = false;
  isMgr = false;
  balanceColumns   = ['employee', 'leave_type', 'allocated', 'used', 'pending', 'remaining', 'bar'];
  typeColumns      = ['name', 'code', 'days', 'paid', 'carry', 'actions'];

  tabs = [
    { id: 'requests',  label: 'Requests',   icon: 'event_note'    },
    { id: 'calendar',  label: 'Calendar',   icon: 'calendar_month' },
    { id: 'balances',  label: 'Balances',   icon: 'account_balance_wallet' },
    { id: 'types',     label: 'Leave Types',icon: 'tune'          },
  ];

  statusTabs = [
    { id: 'needs_action',     label: 'Needs Action'      },
    { id: 'pending',          label: 'Awaiting Manager'  },
    { id: 'manager_approved', label: 'Awaiting HR'       },
    { id: 'approved',         label: 'Approved'          },
    { id: 'rejected',         label: 'Rejected'          },
    { id: 'cancelled',        label: 'Cancelled'         },
    { id: '',                 label: 'All'               },
  ];

  constructor(private http: HttpClient, private auth: AuthService) {}

  ngOnInit() {
    this.loadStats();
    this.loadTypes();
    this.loadMyBalance();
    this.load();
  }

  // ── Stats ─────────────────────────────────────────────────────────────────
  loadStats() {
    // Role detection
    this.isHR  = this.auth.isHRRole();
    this.isMgr = this.auth.isManagerRole();

    this.http.get<any>('/api/v1/leave/stats').subscribe({
      next: r => {
        this.stats = r;
        this.statItems = [
          { label: 'Pending Requests',    value: r.pending_count,  icon: 'pending_actions',   color: '#f59e0b' },
          { label: 'On Leave Today',       value: r.on_leave_today, icon: 'beach_access',      color: '#6366f1' },
          { label: 'Approved This Month',  value: r.approved_month, icon: 'check_circle',      color: '#10b981' },
          { label: 'Cancelled',            value: r.cancelled_count,icon: 'cancel',            color: '#ef4444' },
        ];
      }
    });
  }

  // ── Requests ──────────────────────────────────────────────────────────────
  load(page = 1) {
    this.loading     = true;
    this.currentPage = page;
    const params: any = { per_page: 15, page };
    if (this.activeStatus === 'needs_action') {
      params.needs_action = '1';
    } else if (this.activeStatus) {
      params.status = this.activeStatus;
    }
    if (this.filterType)   params.leave_type_id  = this.filterType;
    if (this.filterSearch) params.search         = this.filterSearch;

    this.http.get<any>('/api/v1/leave/requests', { params }).subscribe({
      next: r => { this.requests = r?.data || []; this.pagination = r; this.loading = false; },
      error: () => this.loading = false
    });
  }

  switchStatus(id: string) { this.activeStatus = id; this.currentPage = 1; this.load(); }

  viewRequest(r: any) { this.selectedRequest = r; this.showDetail = true; }

  approve(r: any) {
    if (!confirm(`Approve ${r.total_days} day(s) leave for ${r.employee?.first_name}?`)) return;
    this.http.post(`/api/v1/leave/requests/${r.id}/approve`, {}).subscribe({
      next: () => { this.load(this.currentPage); this.loadStats(); this.loadMyBalance(); if (this.showDetail) this.showDetail = false; }
    });
  }

  openReject(r: any) { this.rejectTarget = r; this.rejectReason = ''; this.showReject = true; }

  confirmReject() {
    if (!this.rejectReason.trim()) return;
    this.http.post(`/api/v1/leave/requests/${this.rejectTarget.id}/reject`, { reason: this.rejectReason }).subscribe({
      next: () => { this.showReject = false; this.load(this.currentPage); this.loadStats(); if (this.showDetail) this.showDetail = false; }
    });
  }

  cancel(r: any) {
    if (!confirm('Cancel this leave request?')) return;
    this.http.delete(`/api/v1/leave/requests/${r.id}`).subscribe({
      next: () => { this.load(this.currentPage); this.loadStats(); this.loadMyBalance(); if (this.showDetail) this.showDetail = false; }
    });
  }

  // ── New Request ───────────────────────────────────────────────────────────
  openNewRequest() {
    this.form         = { leave_type_id: '', start_date: '', end_date: '', start_time: '08:00', end_time: '09:00', reason: '', employee_id: '' };
    this.formError    = '';
    this.selectedFile = null;
    this.fileError    = '';
    this.showNewRequest = true;
  }

  submitRequest() {
    const isExcuse = this.isBusinessExcuse;
    if (!this.form.leave_type_id || !this.form.start_date || !this.form.reason) {
      this.formError = 'All fields are required.'; return;
    }
    if (!isExcuse && !this.form.end_date) {
      this.formError = 'End date is required.'; return;
    }
    const minChars = isExcuse ? 5 : 10;
    if (this.form.reason.length < minChars) {
      this.formError = `Reason must be at least ${minChars} characters.`; return;
    }
    // Check required document
    const lt = this.selectedType;
    if (lt?.requires_document && !this.selectedFile) {
      this.formError = `A supporting document is required for "${lt.name}" leave.`; return;
    }
    this.submitting = true; this.formError = '';

    // Build multipart FormData so file is included
    const fd = new FormData();
    Object.entries(this.form).forEach(([k, v]) => { if (v) fd.append(k, String(v)); });
    if (this.selectedFile) fd.append('document', this.selectedFile, this.selectedFile.name);

    this.http.post<any>('/api/v1/leave/requests', fd).subscribe({
      next: () => {
        this.submitting = false; this.showNewRequest = false;
        this.form = { leave_type_id: '', start_date: '', end_date: '', start_time: '08:00', end_time: '09:00', reason: '', employee_id: '' };
        this.selectedFile = null; this.excuseUsage = null;
        this.load(1); this.loadStats(); this.loadMyBalance();
      },
      error: err => { this.submitting = false; this.formError = err?.error?.message || 'Submission failed.'; }
    });
  }

  // ── My balance ───────────────────────────────────────────────────────────
  loadMyBalance() {
    const user  = JSON.parse(localStorage.getItem('hrms_user') || '{}');
    const empId = user?.employee?.id || user?.employee_id;
    if (!empId) return;
    this.http.get<any>(`/api/v1/leave/balance/${empId}`).subscribe({
      next: r => this.myBalances = r?.balances || []
    });
  }

  // ── Leave Types ──────────────────────────────────────────────────────────
  loadTypes() {
    this.http.get<any>('/api/v1/leave/types').subscribe({
      next: r => this.leaveTypes = r?.types || r || []
    });
  }

  openTypeForm(t?: any) {
    if (t) {
      this.typeEditId = t.id;
      this.typeForm   = { ...t };
    } else {
      this.typeEditId = null;
      this.typeForm   = { name:'', code:'', days_allowed:0, is_paid:true, carry_forward:false, max_carry_forward:0, requires_document:false, description:'', skip_manager_approval: false };
    }
    this.typeError   = '';
    this.showTypeForm = true;
  }

  saveType() {
    if (!this.typeForm.name || !this.typeForm.code) { this.typeError = 'Name and code are required.'; return; }
    this.typeSaving = true; this.typeError = '';
    const req = this.typeEditId
      ? this.http.put(`/api/v1/leave/types/${this.typeEditId}`, this.typeForm)
      : this.http.post('/api/v1/leave/types', this.typeForm);
    req.subscribe({
      next: () => { this.typeSaving = false; this.showTypeForm = false; this.loadTypes(); },
      error: err => { this.typeSaving = false; this.typeError = err?.error?.message || 'Save failed.'; }
    });
  }

  // ── All Balances tab ─────────────────────────────────────────────────────
  loadAllBalances(page = 1) {
    const params: any = { page, per_page: 25 };
    if (this.filterSearch) params.search = this.filterSearch;
    if (this.filterDept)   params.department_id = this.filterDept;
    this.http.get<any>('/api/v1/leave/all-balances', { params }).subscribe({
      next: r => { this.allBalances = r?.data || []; this.balancePagination = r; }
    });
  }

  // ── Calendar ──────────────────────────────────────────────────────────────
  loadCalendar() {
    const month = String(this.calMonth + 1).padStart(2, '0');
    this.http.get<any>('/api/v1/leave/calendar', { params: { month: this.calMonth + 1, year: this.calYear }}).subscribe({
      next: r => { this.calendarEvents = r?.leaves || []; this.buildCalendar(); }
    });
    this.http.get<any>('/api/v1/leave/holidays', { params: { year: this.calYear }}).subscribe({
      next: r => { this.holidays = r?.holidays || []; this.buildCalendar(); }
    });
  }

  buildCalendar() {
    const firstDay = new Date(this.calYear, this.calMonth, 1).getDay();
    const daysInMonth = new Date(this.calYear, this.calMonth + 1, 0).getDate();
    // Shift: week starts Sunday (0)
    const startPad = firstDay;
    const cells: any[] = [];

    for (let i = 0; i < startPad; i++) cells.push(null);

    for (let d = 1; d <= daysInMonth; d++) {
      const dateStr = `${this.calYear}-${String(this.calMonth+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
      const dow = new Date(dateStr).getDay();
      const isWeekend = dow === 5 || dow === 6; // Fri=5, Sat=6
      const holiday   = this.holidays.find(h => h.date?.slice(0,10) === dateStr);
      const leaves    = this.calendarEvents.filter(e => e.start_date?.slice(0,10) <= dateStr && e.end_date?.slice(0,10) >= dateStr);
      const isToday   = dateStr === new Date().toISOString().slice(0,10);
      cells.push({ d, dateStr, isWeekend, holiday, leaves, isToday });
    }
    this.calDays = cells;
  }

  prevMonth() { if (this.calMonth === 0) { this.calMonth = 11; this.calYear--; } else this.calMonth--; this.loadCalendar(); }
  nextMonth() { if (this.calMonth === 11) { this.calMonth = 0;  this.calYear++; } else this.calMonth++; this.loadCalendar(); }

  // ── Tab switch ────────────────────────────────────────────────────────────
  switchTab(id: string) {
    this.activeTab = id;
    if (id === 'calendar')  this.loadCalendar();
    if (id === 'balances')  this.loadAllBalances();
  }

  // ── Holidays ──────────────────────────────────────────────────────────────
  saveHoliday() {
    if (!this.holidayForm.name || !this.holidayForm.date) return;
    this.holidaySaving = true;
    this.http.post('/api/v1/leave/holidays', this.holidayForm).subscribe({
      next: () => { this.holidaySaving = false; this.showHolidayForm = false; this.loadCalendar(); },
      error: () => this.holidaySaving = false
    });
  }

  deleteHoliday(id: number) {
    if (!confirm('Delete this holiday?')) return;
    this.http.delete(`/api/v1/leave/holidays/${id}`).subscribe({
      next: () => this.loadCalendar()
    });
  }

  // ── Helpers ───────────────────────────────────────────────────────────────
  get selectedType(): any {
    return this.leaveTypes.find(t => t.id == this.form.leave_type_id) || null;
  }

  get isBusinessExcuse(): boolean {
    return this.selectedType?.is_hourly === true;
  }

  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file  = input.files?.[0] ?? null;
    this.fileError = '';
    if (!file) { this.selectedFile = null; return; }
    if (file.size > 5 * 1024 * 1024) {
      this.fileError = 'File must be under 5 MB.'; this.selectedFile = null; return;
    }
    const allowed = ['application/pdf', 'image/jpeg', 'image/png'];
    if (!allowed.includes(file.type)) {
      this.fileError = 'Only PDF, JPG, or PNG files are allowed.'; this.selectedFile = null; return;
    }
    this.selectedFile = file;
  }

  excuseHoursPreview(): number {
    if (!this.form.start_time || !this.form.end_time) return 0;
    const [sh, sm] = this.form.start_time.split(':').map(Number);
    const [eh, em] = this.form.end_time.split(':').map(Number);
    const diff = (eh * 60 + em) - (sh * 60 + sm);
    return diff > 0 ? Math.round(diff / 60 * 100) / 100 : 0;
  }

  onLeaveTypeChange() {
    if (this.isBusinessExcuse) {
      this.loadExcuseUsage();
    } else {
      this.excuseUsage = null;
    }
  }

  loadExcuseUsage() {
    const user  = JSON.parse(localStorage.getItem('hrms_user') || '{}');
    const empId = user?.employee?.id || user?.employee_id;
    if (!empId) return;
    this.loadingUsage = true;
    const now = new Date();
    this.http.get<any>('/api/v1/leave/excuse-usage', {
      params: { employee_id: empId, year: now.getFullYear(), month: now.getMonth() + 1 }
    }).subscribe({
      next: r => { this.excuseUsage = r; this.loadingUsage = false; },
      error: () => this.loadingUsage = false
    });
  }

  workingDaysPreview(): number {
    if (!this.form.start_date || !this.form.end_date) return 0;
    const start = new Date(this.form.start_date);
    const end   = new Date(this.form.end_date);
    if (end < start) return 0;
    let count = 0;
    const cur = new Date(start);
    while (cur <= end) {
      const d = cur.getDay();
      if (d !== 5 && d !== 6) count++;
      cur.setDate(cur.getDate() + 1);
    }
    return count;
  }

  balancePct(b: any): number {
    if (!b?.allocated_days) return 0;
    return Math.min(100, Math.round(((b.allocated_days - b.remaining_days) / b.allocated_days) * 100));
  }

  balanceColor(pct: number): string {
    if (pct >= 80) return 'var(--danger)';
    if (pct >= 50) return 'var(--warning)';
    return 'var(--success)';
  }

  selectedTypeBalance(): any {
    if (!this.form.leave_type_id) return null;
    return this.myBalances.find(b => b.leave_type_id == this.form.leave_type_id);
  }

  get pages(): number[] {
    if (!this.pagination?.last_page) return [];
    return Array.from({ length: Math.min(this.pagination.last_page, 8) }, (_, i) => i + 1);
  }

  get calMonthLabel(): string {
    return new Date(this.calYear, this.calMonth, 1).toLocaleString('en', { month: 'long', year: 'numeric' });
  }

  get calWeekDays() { return ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']; }

  canApprove(r: any): boolean {
    if (r.status === 'pending')          return this.isMgr;
    if (r.status === 'manager_approved') return this.isHR;
    return false;
  }

  canReject(r: any): boolean { return this.canApprove(r); }

  approveLabel(r: any): string {
    if (r.status === 'pending')          return 'Approve (Manager Level)';
    if (r.status === 'manager_approved') return 'Approve (HR Level)';
    return 'Approve';
  }

  approveIcon(r: any): string {
    return r.status === 'pending' ? 'supervisor_account' : 'admin_panel_settings';
  }

  stageLabel(r: any): string {
    if (r.status === 'pending')          return 'Awaiting Manager';
    if (r.status === 'manager_approved') return 'Awaiting HR';
    if (r.status === 'approved')         return 'Approved';
    if (r.status === 'rejected')         return `Rejected (${r.rejected_stage ?? ''})`;
    return r.status;
  }

  statusCls(s: string): string {
    const m: Record<string,string> = {
      pending:          'badge-yellow',
      manager_approved: 'badge-blue',
      approved:         'badge-green',
      rejected:         'badge-red',
      cancelled:        'badge-gray',
    };
    return m[s] ?? 'badge-gray';
  }

  statusIcon(s: string): string {
    const m: Record<string,string> = {
      pending:          'pending_actions',
      manager_approved: 'supervisor_account',
      approved:         'check_circle',
      rejected:         'cancel',
      cancelled:        'block',
    };
    return m[s] ?? 'help';
  }

  avatarColor(name: string): string {
    const colors = ['#3b82f6','#6366f1','#8b5cf6','#ec4899','#10b981','#f59e0b','#ef4444','#0ea5e9'];
    const idx = (name?.charCodeAt(0) || 0) % colors.length;
    return colors[idx];
  }

  leaveTypeColor(name: string): string {
    const map: any = {
      'Annual Leave': '#10b981', 'Sick Leave': '#ef4444', 'Emergency': '#f59e0b',
      'Maternity': '#ec4899', 'Paternity': '#3b82f6', 'Unpaid': '#6b7280',
    };
    return map[name] || '#6366f1';
  }

  // ── Department Limits Panel ───────────────────────────────────────────
  openLimitsPanel(t: any) {
    this.limitsLeaveType = t;
    this.showLimitsPanel = true;
    this.limitsError     = '';
    this.limitsDirty     = false;
    this.loadDeptLimits(t.id);
  }

  loadDeptLimits(leaveTypeId: number) {
    this.limitsLoading = true;
    this.http.get<any>('/api/v1/leave/excuse-limits', { params: { leave_type_id: leaveTypeId } }).subscribe({
      next: r => {
        this.deptLimits    = r?.limits || [];
        this.limitsLoading = false;
      },
      error: () => this.limitsLoading = false
    });
  }

  toggleDeptLimit(row: any) {
    row.is_limited = !row.is_limited;
    this.limitsDirty = true;
  }

  onLimitHoursChange(row: any) {
    this.limitsDirty = true;
  }

  saveLimits() {
    // Basic validation: limited rows must have a positive hours value
    for (const row of this.deptLimits) {
      if (row.is_limited && (!row.monthly_hours_limit || row.monthly_hours_limit <= 0)) {
        this.limitsError = `Please set a valid hour limit for ${row.department_name}.`;
        return;
      }
    }
    this.limitsSaving = true;
    this.limitsError  = '';
    this.http.post('/api/v1/leave/excuse-limits/bulk', {
      leave_type_id: this.limitsLeaveType.id,
      limits: this.deptLimits.map(r => ({
        department_id:       r.department_id,
        is_limited:          r.is_limited,
        monthly_hours_limit: r.is_limited ? r.monthly_hours_limit : null,
      }))
    }).subscribe({
      next: () => {
        this.limitsSaving = false;
        this.limitsDirty  = false;
        this.limitsError  = '';
      },
      error: err => {
        this.limitsSaving = false;
        this.limitsError  = err?.error?.message || 'Save failed.';
      }
    });
  }
}
