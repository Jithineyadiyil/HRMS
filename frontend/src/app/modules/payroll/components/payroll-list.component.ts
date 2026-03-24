import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { AuthService } from '../../../core/services/auth.service';

@Component({
  standalone: false,
  selector: 'app-payroll-list',
  templateUrl: './payroll-list.component.html',
  styleUrls: ['./payroll-list.component.scss'],
})
export class PayrollListComponent implements OnInit {
  loading       = true;
  payrolls: any[] = [];
  payslips: any[] = [];
  pagination: any = null;

  // Modal flags
  showRunForm    = false;
  showDetail     = false;
  showReject     = false;
  showEditSlip   = false;
  showSlipDetail = false;

  selectedPayroll: any  = null;
  selectedSlip: any     = null;
  rejectReason          = '';
  running               = false;
  detailLoading         = false;
  editSaving            = false;
  recalculating         = false;
  downloading           = false;
  slipIndex             = 0;
  runError              = '';
  editError             = '';

  displayedColumns = ['employee', 'basic', 'housing', 'transport', 'other_earn', 'gross', 'gosi', 'other_ded', 'net', 'days', 'actions'];

  // ── Settings tab ──────────────────────────────────────────────────────
  activeTab         = 'payrolls';  // payrolls | settings
  settings:         any[] = [];
  leaveTypes:       any[] = [];
  settingsLoading   = false;
  settingsSaving    = false;
  settingsError     = '';
  settingsDirty:    Record<string, any> = {};
  statItems: { label: string; value: string; icon: string; color: string }[] = [];

  // ── Employee self-service ─────────────────────────────────────────────
  myPayslips:     any[] = [];
  myPayslipsPag:  any   = null;
  mySlipsLoading  = false;
  selectedMySlip: any   = null;
  showMySlipDetail = false;

  runForm = { month: '', period_start: '', period_end: '' };

  // Edit form — manual override
  editForm: any = {};

  constructor(private http: HttpClient, public auth: AuthService) {}

  get isHR(): boolean {
    return this.auth.canAny(['payroll.view','payroll.run','payroll.approve']);
  }
  get isEmployee(): boolean { return !this.isHR; }

  ngOnInit() {
    this.setDefaultPeriod();
    if (this.isHR) {
      this.load();
    } else {
      this.activeTab = 'my-payslips';
      this.loadMyPayslips();
    }
  }

  switchTab(id: string) {
    this.activeTab = id;
    if (id === 'settings')    this.loadSettings();
    if (id === 'my-payslips') this.loadMyPayslips();
    if (id === 'payrolls' && !this.payrolls.length) this.load();
  }

  loadMyPayslips(page = 1) {
    this.mySlipsLoading = true;
    this.http.get<any>('/api/v1/payroll/my-payslips', { params: { page, per_page: 12 } }).subscribe({
      next: r => { this.myPayslips = r?.data || []; this.myPayslipsPag = r; this.mySlipsLoading = false; },
      error: () => this.mySlipsLoading = false,
    });
  }

  viewMySlip(ps: any) {
    this.selectedMySlip  = ps;
    this.showMySlipDetail = true;
  }

  downloadMyPayslip(ps: any) {
    this.downloading = true;
    this.http.get(`/api/v1/payroll/payslip/${ps.id}/download`, { responseType: 'blob' }).subscribe({
      next: (blob: Blob) => {
        this.downloading = false;
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = `payslip_${ps.payroll?.month ?? ps.id}.pdf`;
        a.click();
        URL.revokeObjectURL(a.href);
      },
      error: err => {
        this.downloading = false;
        if (err.error instanceof Blob) {
          err.error.text().then((t: string) => {
            try { alert(JSON.parse(t)?.message || 'Download failed.'); }
            catch { alert('Download failed.'); }
          });
        }
      },
    });
  }

  get myPayslipsPages(): number[] {
    if (!this.myPayslipsPag?.last_page) return [];
    return Array.from({ length: Math.min(this.myPayslipsPag.last_page, 10) }, (_, i) => i + 1);
  }

  // ── Payroll Settings ───────────────────────────────────────────────────
  // Default settings shown when DB table is empty or not yet seeded
  private readonly DEFAULT_SETTINGS = [
    { key:'deduct_unpaid_leave',        value:'1',      type:'boolean', label:'Deduct Unpaid Leave from Salary',        group:'leave',      description:'When ON, approved leaves of types marked Unpaid are deducted from basic salary at the daily rate.' },
    { key:'deduct_absences',            value:'1',      type:'boolean', label:'Deduct Unrecorded Absences',             group:'leave',      description:'When ON, days marked Absent in attendance with no approved leave are deducted.' },
    { key:'deduct_allowances_on_leave', value:'0',      type:'boolean', label:'Deduct Allowances on Unpaid Leave',      group:'leave',      description:'When ON, housing and transport allowances are also pro-rated for unpaid leave days.' },
    { key:'daily_rate_basis',           value:'monthly',type:'string',  label:'Daily Rate Calculation Basis',           group:'deductions', description:'monthly = salary ÷ working days | fixed = salary ÷ 26 | annual = salary × 12 ÷ 260' },
    { key:'working_days_per_month',     value:'26',     type:'integer', label:'Working Days Per Month (Fixed Basis)',   group:'deductions', description:'Used when daily_rate_basis = fixed. Saudi standard is 26.' },
    { key:'gosi_apply_saudi_only',      value:'1',      type:'boolean', label:'Apply GOSI to Saudi Nationals Only',     group:'gosi',       description:'When ON, GOSI deductions only apply to Saudi national employees.' },
    { key:'gosi_employee_rate',         value:'0.09',   type:'decimal', label:'GOSI Employee Contribution Rate',        group:'gosi',       description:'Employee-side GOSI rate (default 9% = 0.09).' },
    { key:'gosi_employer_rate',         value:'0.1175', type:'decimal', label:'GOSI Employer Contribution Rate',        group:'gosi',       description:'Employer-side GOSI rate (default 11.75% = 0.1175).' },
    { key:'overtime_rate',              value:'1.5',    type:'decimal', label:'Overtime Rate Multiplier',               group:'overtime',   description:'Daily rate multiplier for overtime (1.5 = 150% of daily rate).' },
  ];

  loadSettings() {
    this.settingsLoading = true;
    this.http.get<any>('/api/v1/payroll-settings').subscribe({
      next: r => {
        const loaded = r?.settings || [];
        // If DB has no settings yet, use defaults so the UI always shows
        this.settings   = loaded.length ? loaded : this.DEFAULT_SETTINGS;
        this.leaveTypes = r?.leave_types || [];
        this.settingsDirty = {};
        this.settingsLoading = false;

        // If DB was empty, auto-save defaults to seed the table
        if (!loaded.length) {
          const seedPayload: Record<string, any> = {};
          this.DEFAULT_SETTINGS.forEach(s => seedPayload[s.key] = s.value);
          this.http.post('/api/v1/payroll-settings', { settings: seedPayload }).subscribe();
        }
      },
      error: () => {
        // Even on API error, show defaults so page isn't blank
        this.settings   = this.DEFAULT_SETTINGS;
        this.settingsLoading = false;
      },
    });
  }

  getSettingVal(key: string): any {
    if (key in this.settingsDirty) return this.settingsDirty[key];
    return this.settings.find(s => s.key === key)?.value ?? '';
  }

  setSettingVal(key: string, value: any) {
    this.settingsDirty[key] = value;
  }

  settingsByGroup(group: string): any[] {
    return this.settings.filter(s => s.group === group);
  }

  get settingGroups(): string[] {
    return [...new Set(this.settings.map(s => s.group))];
  }

  get hasDirtySettings(): boolean {
    return Object.keys(this.settingsDirty).length > 0;
  }

  saveSettings() {
    this.settingsSaving = true;
    this.settingsError  = '';
    this.http.post<any>('/api/v1/payroll-settings', { settings: this.settingsDirty }).subscribe({
      next: () => {
        this.settingsSaving = false;
        this.settingsDirty  = {};
        this.loadSettings();
      },
      error: err => { this.settingsSaving = false; this.settingsError = err?.error?.message || 'Save failed.'; },
    });
  }

  toggleLeaveTypePaid(lt: any, isPaid: boolean) {
    this.http.patch<any>(`/api/v1/payroll-settings/leave-types/${lt.id}`, { is_paid: isPaid }).subscribe({
      next: r => {
        const idx = this.leaveTypes.findIndex(t => t.id === lt.id);
        if (idx > -1) this.leaveTypes[idx] = { ...this.leaveTypes[idx], is_paid: isPaid };
      },
    });
  }

  groupLabel(group: string): string {
    return ({ deductions:'Deduction Rules', leave:'Leave & Absence', gosi:'GOSI Settings', overtime:'Overtime', general:'General' } as any)[group] ?? group;
  }

  setDefaultPeriod() {
    const now = new Date();
    const y = now.getFullYear();
    const m = now.getMonth();
    this.runForm.month        = `${y}-${String(m + 1).padStart(2, '0')}`;
    this.runForm.period_start = new Date(y, m, 1).toISOString().slice(0, 10);
    this.runForm.period_end   = new Date(y, m + 1, 0).toISOString().slice(0, 10);
  }

  load(page = 1) {
    this.loading = true;
    this.http.get<any>('/api/v1/payroll', { params: { page, per_page: 12 } }).subscribe({
      next: r => { this.payrolls = r?.data || []; this.pagination = r; this.loading = false; this.buildStats(); },
      error: () => this.loading = false
    });
  }

  buildStats() {
    const latest = this.payrolls[0];
    this.statItems = [
      { label: 'Latest Net Payroll', value: latest ? this.fmtSAR(latest.total_net)   : '—', icon: 'payments',        color: '#10b981' },
      { label: 'Latest Gross',       value: latest ? this.fmtSAR(latest.total_gross) : '—', icon: 'account_balance', color: '#3b82f6' },
      { label: 'Pending Approval',   value: String(this.payrolls.filter((p: any) => p.status === 'pending_approval').length), icon: 'pending_actions', color: '#f59e0b' },
      { label: 'Total Runs',         value: String(this.pagination?.total || this.payrolls.length), icon: 'receipt_long', color: '#6366f1' },
    ];
  }

  // ── Run Payroll ────────────────────────────────────────────────────────────
  runPayroll() {
    if (!this.runForm.month || !this.runForm.period_start || !this.runForm.period_end) {
      this.runError = 'All fields are required.'; return;
    }
    this.running = true; this.runError = '';
    this.http.post<any>('/api/v1/payroll/run', this.runForm).subscribe({
      next: () => { this.running = false; this.showRunForm = false; this.load(); },
      error: err => {
        this.running  = false;
        const msg     = err?.error?.message || 'Payroll run failed.';
        this.runError = msg;
      }
    });
  }

  // ── View Payslips ──────────────────────────────────────────────────────────
  viewDetail(p: any) {
    this.selectedPayroll = p;
    this.showDetail      = true;
    this.detailLoading   = true;
    this.payslips        = [];
    this.http.get<any>(`/api/v1/payroll/${p.id}/payslips`, { params: { per_page: 100 } }).subscribe({
      next: r => { this.payslips = r?.data || r || []; this.detailLoading = false; },
      error: () => this.detailLoading = false
    });
  }

  // ── View single payslip breakdown (drawer) ───────────────────────────────
  viewSlip(ps: any) {
    this.slipIndex      = this.payslips.findIndex(p => p.id === ps.id);
    this.selectedSlip   = ps;
    this.showSlipDetail = true;
  }

  navigateSlip(dir: number) {
    const next = this.slipIndex + dir;
    if (next < 0 || next >= this.payslips.length) return;
    this.slipIndex    = next;
    this.selectedSlip = this.payslips[next];
  }

  downloadPayslip(ps: any) {
    this.downloading = true;
    this.http.get(`/api/v1/payroll/payslip/${ps.id}/download`, { responseType: 'blob' }).subscribe({
      next: blob => {
        // Guard: if the response is JSON (an error), read and show it
        if (blob.type === 'application/json') {
          const reader = new FileReader();
          reader.onload = () => {
            try {
              const err = JSON.parse(reader.result as string);
              alert('PDF Error: ' + (err.message || 'Unknown error'));
            } catch { alert('PDF generation failed.'); }
          };
          reader.readAsText(blob);
          this.downloading = false;
          return;
        }
        // Valid PDF — trigger download
        const url = window.URL.createObjectURL(new Blob([blob], { type: 'application/pdf' }));
        const a   = document.createElement('a');
        a.style.display = 'none';
        a.href     = url;
        a.download = `Payslip_${ps.employee?.employee_code || ps.employee_id}_${this.selectedPayroll?.month}.pdf`;
        document.body.appendChild(a);
        a.click();
        setTimeout(() => { window.URL.revokeObjectURL(url); document.body.removeChild(a); }, 500);
        this.downloading = false;
      },
      error: err => {
        this.downloading = false;
        // Try to read the JSON body of the error blob
        if (err.error instanceof Blob) {
          const reader = new FileReader();
          reader.onload = () => {
            try {
              const e = JSON.parse(reader.result as string);
              alert('Download failed: ' + (e.message || JSON.stringify(e)));
            } catch { alert('Download failed. Check Laravel logs.'); }
          };
          reader.readAsText(err.error);
        } else {
          alert('Download failed: ' + (err?.error?.message || err?.message || 'Unknown error'));
        }
      }
    });
  }

  // ── Edit payslip ──────────────────────────────────────────────────────────
  openEdit(ps: any) {
    this.selectedSlip = ps;
    this.editForm = {
      basic_salary:        ps.basic_salary        || 0,
      housing_allowance:   ps.housing_allowance   || 0,
      transport_allowance: ps.transport_allowance || 0,
      other_allowances:    ps.other_allowances    || 0,
      gosi_employee:       ps.gosi_employee       || 0,
      other_deductions:    ps.other_deductions    || 0,
      absent_days:         ps.absent_days         || 0,
    };
    this.editError    = '';
    this.showEditSlip = true;
  }

  saveEdit() {
    this.editSaving = true;
    this.editError  = '';
    this.http.put<any>(
      `/api/v1/payroll/${this.selectedPayroll.id}/payslips/${this.selectedSlip.id}`,
      this.editForm
    ).subscribe({
      next: r => {
        // Update payslip in list
        const idx = this.payslips.findIndex(p => p.id === this.selectedSlip.id);
        if (idx > -1) this.payslips[idx] = { ...this.payslips[idx], ...r.payslip };
        this.selectedSlip  = r.payslip;
        this.editSaving    = false;
        this.showEditSlip  = false;
        // Refresh payroll totals
        this.load();
      },
      error: err => { this.editSaving = false; this.editError = err?.error?.message || 'Save failed.'; }
    });
  }

  // ── Approve / Reject ──────────────────────────────────────────────────────
  approve(p: any) {
    if (!confirm(`Approve payroll for ${p.month}? This cannot be undone.`)) return;
    this.http.post(`/api/v1/payroll/${p.id}/approve`, {}).subscribe({
      next: () => { this.showDetail = false; this.load(); }
    });
  }

  openReject(p: any) {
    this.selectedPayroll = p;
    this.rejectReason    = '';
    this.showReject      = true;
  }

  confirmReject() {
    if (!this.rejectReason.trim()) return;
    this.http.post(`/api/v1/payroll/${this.selectedPayroll.id}/reject`, { reason: this.rejectReason }).subscribe({
      next: () => { this.showReject = false; this.load(); }
    });
  }

  reopen(p: any) {
    if (!confirm(`Reopen payroll for ${p.month}? This will reset the approval and allow editing.`)) return;
    this.http.post<any>(`/api/v1/payroll/${p.id}/reopen`, {}).subscribe({
      next: r => {
        this.showDetail = false;
        this.load();
      },
      error: err => alert(err?.error?.message || 'Failed to reopen payroll.')
    });
  }

  recalculate(p: any) {
    if (!confirm(`Recalculate all payslips for ${p.month}? This will delete existing payslips and recompute from current employee data.`)) return;
    this.recalculating = true;
    this.http.post<any>(`/api/v1/payroll/${p.id}/recalculate`, {}).subscribe({
      next: r => {
        this.recalculating = false;
        alert(r.message);
        this.viewDetail(p);   // reload payslips
        this.load();
      },
      error: err => {
        this.recalculating = false;
        alert(err?.error?.message || 'Recalculation failed.');
      }
    });
  }

  exportBank(p: any) {
    this.http.get(`/api/v1/payroll/${p.id}/export`, { responseType: 'blob' }).subscribe({
      next: blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = `bank_transfer_${p.month}.csv`; a.click();
        window.URL.revokeObjectURL(url);
      }
    });
  }

  // ── Computed helpers ──────────────────────────────────────────────────────
  editNetPreview(): number {
    const e = this.editForm;
    const earn = (+e.basic_salary||0) + (+e.housing_allowance||0) + (+e.transport_allowance||0) + (+e.other_allowances||0);
    const ded  = (+e.gosi_employee||0) + (+e.other_deductions||0);
    return Math.max(0, earn - ded);
  }

  editGrossPreview(): number {
    const e = this.editForm;
    return (+e.basic_salary||0) + (+e.housing_allowance||0) + (+e.transport_allowance||0) + (+e.other_allowances||0);
  }

  payrollTotalNet()   { return this.payslips.reduce((s, p) => s + (+p.net_salary   || 0), 0); }
  payrollTotalGross() { return this.payslips.reduce((s, p) => s + (+p.gross_salary || 0), 0); }
  payrollTotalDed()   { return this.payslips.reduce((s, p) => s + (+p.total_deductions || 0), 0); }

  get pages(): number[] {
    if (!this.pagination?.last_page) return [];
    return Array.from({ length: Math.min(this.pagination.last_page, 10) }, (_, i) => i + 1);
  }

  fmtSAR(v: any) {
    if (v === null || v === undefined || v === '') return '—';
    return 'SAR ' + Number(v).toLocaleString('en', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  canEdit(payroll: any) { return ['draft', 'pending_approval'].includes(payroll?.status); }

  statusCls(s: string) {
    return ({ draft:'badge-gray', pending_approval:'badge-yellow', approved:'badge-blue', paid:'badge-green', rejected:'badge-red' } as any)[s] || 'badge-gray';
  }

  statusLabel(s: string) {
    return ({ pending_approval:'Pending', approved:'Approved', paid:'Paid', rejected:'Rejected', draft:'Draft' } as any)[s] || s;
  }
}
