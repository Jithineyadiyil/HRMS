import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';

@Component({
  standalone: false,
  selector: 'app-separation-list',
  templateUrl: './separation-list.component.html',
  styleUrls: ['./separation-list.component.scss'],
})
export class SeparationListComponent implements OnInit {

  // ── Tabs ────────────────────────────────────────────────────────────────
  activeTab    = 'all';
  activeStatus = '';
  loading      = false;
  submitting   = false;

  // ── Data ─────────────────────────────────────────────────────────────────
  separations:  any[] = [];
  templates:    any[] = [];
  stats:        any   = {};
  statItems:    any[] = [];
  pagination:   any   = null;
  currentPage         = 1;

  // ── Filters ──────────────────────────────────────────────────────────────
  filterSearch = '';
  filterType   = '';

  // ── Panels ───────────────────────────────────────────────────────────────
  showNewForm      = false;
  showDetail       = false;
  showReject       = false;
  showApproveHR    = false;  // HR approval with settlement
  showExitInterview= false;
  showComplete     = false;
  showTemplateForm = false;
  selectedSep: any = null;
  rejectTarget:any = null;
  rejectReason     = '';

  // ── New separation form ──────────────────────────────────────────────────
  employees:   any[] = [];
  form: any = {
    employee_id:'', type:'resignation', reason:'', reason_category:'personal',
    last_working_day:'', notice_waived: false, notice_waived_reason:'', hr_notes:''
  };
  formError     = '';
  settlementPreview: any = null;

  // ── HR Approval form ──────────────────────────────────────────────────────
  hrApprovalForm: any = { other_additions: 0, other_deductions: 0, hr_notes: '' };

  // ── Exit interview form ───────────────────────────────────────────────────
  exitForm: any = { date: '', notes: '' };

  // ── Complete form ─────────────────────────────────────────────────────────
  completeForm: any = { settlement_paid: false, settlement_notes: '' };

  // ── Template form ─────────────────────────────────────────────────────────
  templateForm: any = { title:'', category:'hr', description:'', is_required:true, is_active:true, sort_order:0 };
  templateEditId: number | null = null;

  // ── Table columns ─────────────────────────────────────────────────────────
  displayedColumns = ['ref','employee','type','last_working_day','notice','status','actions'];
  templateColumns  = ['title','category','required','actions'];

  tabs = [
    { id:'all',       label:'All',          icon:'list_alt'     },
    { id:'pending',   label:'Pending',      icon:'hourglass_empty' },
    { id:'offboarding',label:'Offboarding', icon:'checklist'    },
    { id:'completed', label:'Completed',    icon:'task_alt'     },
    { id:'templates', label:'Checklist Setup', icon:'tune'      },
  ];

  statusTabs = [
    { id:'',               label:'All'              },
    { id:'pending_manager',label:'Pending Manager'  },
    { id:'pending_hr',     label:'Pending HR'       },
    { id:'approved',       label:'Approved'         },
    { id:'offboarding',    label:'Offboarding'      },
    { id:'completed',      label:'Completed'        },
    { id:'rejected',       label:'Rejected'         },
    { id:'cancelled',      label:'Cancelled'        },
  ];

  separationTypes = [
    { id:'resignation',      label:'Resignation',       icon:'exit_to_app',     color:'#f59e0b' },
    { id:'termination',      label:'Termination',       icon:'block',           color:'#ef4444' },
    { id:'end_of_contract',  label:'End of Contract',   icon:'event_busy',      color:'#6366f1' },
    { id:'retirement',       label:'Retirement',        icon:'elderly',         color:'#10b981' },
    { id:'abandonment',      label:'Abandonment',       icon:'person_off',      color:'#8b5cf6' },
    { id:'mutual_agreement', label:'Mutual Agreement',  icon:'handshake',       color:'#0ea5e9' },
  ];

  reasonCategories = [
    { id:'personal',          label:'Personal Reasons'     },
    { id:'better_opportunity',label:'Better Opportunity'   },
    { id:'relocation',        label:'Relocation'           },
    { id:'health',            label:'Health Reasons'       },
    { id:'misconduct',        label:'Misconduct'           },
    { id:'performance',       label:'Poor Performance'     },
    { id:'restructuring',     label:'Restructuring'        },
    { id:'contract_end',      label:'Contract Ended'       },
    { id:'other',             label:'Other'                },
  ];

  checklistCategories = ['it','hr','finance','admin','general'];

  constructor(private http: HttpClient) {}

  ngOnInit() {
    this.loadStats();
    this.load();
    this.loadEmployees();
    this.loadTemplates();
  }

  loadStats() {
    this.http.get<any>('/api/v1/separations/stats').subscribe({ next: r => {
      this.stats = r;
      this.statItems = [
        { label:'Pending Manager', value: r.pending_manager,  icon:'manage_accounts', color:'#f59e0b' },
        { label:'Pending HR',      value: r.pending_hr,       icon:'badge',           color:'#6366f1' },
        { label:'Offboarding',     value: r.offboarding,      icon:'checklist',       color:'#3b82f6' },
        { label:'Completed YTD',   value: r.completed_ytd,    icon:'task_alt',        color:'#10b981' },
      ];
    }});
  }

  load(page = 1) {
    this.loading = true; this.currentPage = page;
    const params: any = { per_page: 15, page };
    if (this.activeStatus) params.status = this.activeStatus;
    if (this.filterType)   params.type   = this.filterType;
    if (this.filterSearch) params.search = this.filterSearch;
    if (this.activeTab === 'offboarding') params.status = 'offboarding';
    if (this.activeTab === 'completed')   params.status = 'completed';
    if (this.activeTab === 'pending')     params.status = 'pending_manager,pending_hr,approved';
    this.http.get<any>('/api/v1/separations', { params }).subscribe({
      next: r => { this.separations = r?.data || []; this.pagination = r; this.loading = false; },
      error: () => this.loading = false
    });
  }

  loadEmployees() {
    this.http.get<any>('/api/v1/employees?per_page=500&status=active').subscribe({
      next: r => this.employees = r?.data || r?.employees || []
    });
  }

  loadTemplates() {
    this.http.get<any>('/api/v1/separations/templates').subscribe({ next: r => this.templates = r?.templates || [] });
  }

  switchTab(id: string) {
    this.activeTab = id; this.activeStatus = '';
    if (id !== 'templates') this.load();
  }

  switchStatus(id: string) { this.activeStatus = id; this.load(); }

  // ── View detail ────────────────────────────────────────────────────────
  viewSep(sep: any) {
    this.http.get<any>(`/api/v1/separations/${sep.id}`).subscribe({ next: r => {
      this.selectedSep = r.separation;
      this.showDetail  = true;
    }});
  }

  reloadDetail() {
    if (this.selectedSep) {
      this.http.get<any>(`/api/v1/separations/${this.selectedSep.id}`).subscribe({ next: r => this.selectedSep = r.separation });
    }
  }

  // ── New separation ──────────────────────────────────────────────────────
  openNew() {
    const d = new Date(); d.setDate(d.getDate() + 30);
    this.form = { employee_id:'', type:'resignation', reason:'', reason_category:'personal',
      last_working_day: d.toISOString().slice(0,10), notice_waived: false, notice_waived_reason:'', hr_notes:'' };
    this.formError = ''; this.settlementPreview = null;
    this.showNewForm = true;
  }

  onEmployeeOrTypeChange() {
    if (this.form.employee_id && this.form.last_working_day) {
      this.http.get<any>('/api/v1/separations/settlement-preview', { params: {
        employee_id: this.form.employee_id, type: this.form.type, last_working_day: this.form.last_working_day
      }}).subscribe({ next: r => this.settlementPreview = r });
    }
  }

  submitSeparation() {
    if (!this.form.employee_id || !this.form.reason || !this.form.last_working_day) {
      this.formError = 'Employee, reason, and last working day are required.'; return;
    }
    this.submitting = true; this.formError = '';
    this.http.post<any>('/api/v1/separations', this.form).subscribe({
      next: () => {
        this.submitting = false; this.showNewForm = false;
        this.load(); this.loadStats();
      },
      error: err => { this.submitting = false; this.formError = err?.error?.message || 'Failed to submit.'; }
    });
  }

  // ── Approve ────────────────────────────────────────────────────────────
  openApprove(sep: any) {
    if (sep.status === 'pending_hr') {
      this.selectedSep = sep; this.showApproveHR = true;
      this.hrApprovalForm = { other_additions: 0, other_deductions: 0, hr_notes: '' };
    } else {
      this.quickApprove(sep);
    }
  }

  quickApprove(sep: any) {
    this.http.post(`/api/v1/separations/${sep.id}/approve`, {}).subscribe({
      next: () => { this.load(this.currentPage); this.loadStats(); if (this.showDetail) this.reloadDetail(); }
    });
  }

  submitHRApproval() {
    this.http.post(`/api/v1/separations/${this.selectedSep.id}/approve`, this.hrApprovalForm).subscribe({
      next: () => { this.showApproveHR = false; this.load(this.currentPage); this.loadStats(); if (this.showDetail) this.reloadDetail(); }
    });
  }

  startOffboarding(sep: any) {
    this.http.post(`/api/v1/separations/${sep.id}/approve`, {}).subscribe({
      next: () => { this.load(this.currentPage); this.loadStats(); if (this.showDetail) this.reloadDetail(); }
    });
  }

  // ── Reject ─────────────────────────────────────────────────────────────
  openReject(sep: any) { this.rejectTarget = sep; this.rejectReason = ''; this.showReject = true; }

  confirmReject() {
    if (!this.rejectReason.trim()) return;
    this.http.post(`/api/v1/separations/${this.rejectTarget.id}/reject`, { reason: this.rejectReason }).subscribe({
      next: () => { this.showReject = false; this.load(this.currentPage); this.loadStats(); if (this.showDetail) this.showDetail = false; }
    });
  }

  cancel(sep: any) {
    if (!confirm('Cancel this separation request?')) return;
    this.http.post(`/api/v1/separations/${sep.id}/cancel`, {}).subscribe({
      next: () => { this.load(this.currentPage); this.loadStats(); if (this.showDetail) this.showDetail = false; }
    });
  }

  // ── Checklist item ──────────────────────────────────────────────────────
  toggleChecklistItem(item: any, status: string) {
    this.http.put(`/api/v1/separations/${this.selectedSep.id}/checklist/${item.id}`, { status }).subscribe({
      next: () => this.reloadDetail()
    });
  }

  checklistProgress(sep: any): number {
    if (!sep?.checklist_items?.length) return 0;
    const done = sep.checklist_items.filter((i: any) => ['completed','skipped','na'].includes(i.status)).length;
    return Math.round((done / sep.checklist_items.length) * 100);
  }

  checklistByCategory(items: any[]): any[] {
    if (!items) return [];
    const cats: any = {};
    for (const item of items) {
      if (!cats[item.category]) cats[item.category] = { category: item.category, items: [] };
      cats[item.category].items.push(item);
    }
    return Object.values(cats);
  }

  // ── Exit interview ──────────────────────────────────────────────────────
  openExitInterview(sep: any) {
    this.selectedSep = sep; this.exitForm = { date: new Date().toISOString().slice(0,10), notes: '' };
    this.showExitInterview = true;
  }

  submitExitInterview() {
    this.http.post(`/api/v1/separations/${this.selectedSep.id}/exit-interview`, this.exitForm).subscribe({
      next: () => { this.showExitInterview = false; this.reloadDetail(); }
    });
  }

  // ── Complete ────────────────────────────────────────────────────────────
  openComplete(sep: any) {
    this.selectedSep = sep; this.completeForm = { settlement_paid: false, settlement_notes: '' };
    this.showComplete = true;
  }

  submitComplete() {
    this.http.post(`/api/v1/separations/${this.selectedSep.id}/complete`, this.completeForm).subscribe({
      next: () => { this.showComplete = false; this.load(this.currentPage); this.loadStats(); this.showDetail = false; }
    });
  }

  // ── Templates ───────────────────────────────────────────────────────────
  openTemplateForm(t?: any) {
    if (t) { this.templateEditId = t.id; this.templateForm = { ...t }; }
    else   { this.templateEditId = null; this.templateForm = { title:'', category:'hr', description:'', is_required:true, is_active:true, sort_order:0 }; }
    this.showTemplateForm = true;
  }

  saveTemplate() {
    if (!this.templateForm.title) return;
    const req = this.templateEditId
      ? this.http.put(`/api/v1/separations/templates/${this.templateEditId}`, this.templateForm)
      : this.http.post('/api/v1/separations/templates', this.templateForm);
    req.subscribe({ next: () => { this.showTemplateForm = false; this.loadTemplates(); }});
  }

  deleteTemplate(id: number) {
    if (!confirm('Delete this template?')) return;
    this.http.delete(`/api/v1/separations/templates/${id}`).subscribe({ next: () => this.loadTemplates() });
  }

  // ── Helpers ─────────────────────────────────────────────────────────────
  get pages(): number[] {
    if (!this.pagination?.last_page) return [];
    return Array.from({ length: Math.min(this.pagination.last_page, 8) }, (_, i) => i + 1);
  }

  typeInfo(type: string): any {
    return this.separationTypes.find(t => t.id === type) || { label: type, icon: 'help', color: '#8b949e' };
  }

  statusLabel(s: string): string {
    const map: any = {
      draft:'Draft', submitted:'Submitted', pending_manager:'Pending Manager', pending_hr:'Pending HR',
      approved:'Approved', offboarding:'Offboarding', completed:'Completed', cancelled:'Cancelled', rejected:'Rejected'
    };
    return map[s] || s;
  }

  statusCls(s: string): string {
    const map: any = {
      draft:'badge-gray', submitted:'badge-blue', pending_manager:'badge-yellow', pending_hr:'badge-purple',
      approved:'badge-green', offboarding:'badge-teal', completed:'badge-green-solid',
      cancelled:'badge-gray', rejected:'badge-red'
    };
    return map[s] || 'badge-gray';
  }

  statusIcon(s: string): string {
    const map: any = {
      draft:'draft', submitted:'send', pending_manager:'manage_accounts', pending_hr:'badge',
      approved:'check_circle', offboarding:'checklist', completed:'task_alt',
      cancelled:'block', rejected:'cancel'
    };
    return map[s] || 'help';
  }

  noticeDaysRemaining(sep: any): number {
    if (!sep.last_working_day) return 0;
    return Math.max(0, Math.ceil((new Date(sep.last_working_day).getTime() - Date.now()) / 86400000));
  }

  canApprove(sep: any): boolean {
    return ['pending_manager','pending_hr','approved'].includes(sep.status);
  }

  canReject(sep: any): boolean {
    return ['pending_manager','pending_hr'].includes(sep.status);
  }

  avatarColor(name: string): string {
    const colors = ['#3b82f6','#6366f1','#8b5cf6','#ec4899','#10b981','#f59e0b','#ef4444','#0ea5e9'];
    return colors[(name?.charCodeAt(0) || 0) % colors.length];
  }

  categoryLabel(c: string): string {
    return ({ it:'IT', hr:'HR', finance:'Finance', admin:'Admin', general:'General' } as any)[c] || c;
  }

  categoryColor(c: string): string {
    return ({ it:'#3b82f6', hr:'#6366f1', finance:'#10b981', admin:'#f59e0b', general:'#8b949e' } as any)[c] || '#8b949e';
  }

  itemStatusCls(s: string): string {
    return ({ pending:'item-pending', completed:'item-done', skipped:'item-skip', na:'item-na' } as any)[s] || '';
  }

  approvalSteps(sep: any): any[] {
    const isTermination = sep.type === 'termination' || sep.type === 'abandonment';
    const steps = [
      { label:'Request', done: true, active: false, by: sep.initiated_by?.name, date: sep.created_at },
    ];
    if (!isTermination) {
      steps.push({
        label:'Manager', done: !!sep.manager_approved_at, active: sep.status === 'pending_manager',
        by: sep.manager_approver?.name, date: sep.manager_approved_at
      });
    }
    steps.push(
      { label:'HR', done: !!sep.hr_approved_at, active: sep.status === 'pending_hr', by: sep.hr_approver?.name, date: sep.hr_approved_at },
      { label:'Offboarding', done: sep.status === 'offboarding' || sep.status === 'completed', active: sep.status === 'approved', by: '', date: null },
      { label:'Completed', done: sep.status === 'completed', active: false, by: '', date: null }
    );
    return steps;
  }
}
