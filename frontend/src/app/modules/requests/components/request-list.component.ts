import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { AuthService } from '../../../core/services/auth.service';

@Component({
  standalone: false,
  selector: 'app-request-list',
  templateUrl: './request-list.component.html',
  styleUrls: ['./request-list.component.scss'],
})
export class RequestListComponent implements OnInit {

  // ── Tabs ────────────────────────────────────────────────────────────────
  activeTab    = 'mine';   // mine | all | types
  activeStatus = '';
  loading      = false;
  submitting   = false;

  // ── Data ─────────────────────────────────────────────────────────────────
  requests:   any[] = [];
  requestTypes: any[] = [];
  allRequestTypes: any[] = [];
  stats:      any   = {};
  statItems:  any[] = [];
  pagination: any   = null;
  currentPage       = 1;

  // ── Filters ──────────────────────────────────────────────────────────────
  filterSearch   = '';
  filterCategory = '';
  filterTypeId   = '';

  // ── Panels ───────────────────────────────────────────────────────────────
  showNew        = false;
  showDetail     = false;
  showReject     = false;
  showComplete   = false;
  showTypeForm   = false;
  selectedReq: any = null;
  rejectTarget:any = null;
  rejectReason     = '';
  newComment       = '';
  sendingComment   = false;

  // ── New request form ──────────────────────────────────────────────────────
  form: any = { request_type_id: '', details: '', required_by: '', copies_needed: 1 };
  formError   = '';
  selectedType: any = null;

  // ── Complete form ─────────────────────────────────────────────────────────
  completeForm: any = { completion_notes: '', hr_notes: '' };

  // ── Type form ─────────────────────────────────────────────────────────────
  typeForm: any = { name:'', code:'', category:'documents', description:'', instructions:'',
    sla_days:3, requires_attachment:false, requires_manager_approval:false, is_active:true,
    sort_order:0, icon:'description', color:'#6366f1' };
  typeEditId: number | null = null;
  typeSaving  = false;

  // ── Table & display ───────────────────────────────────────────────────────
  displayedColumns = ['ref','employee','type','details','required_by','status','sla','actions'];
  mineColumns      = ['ref','type','details','required_by','status','sla','actions'];
  typeColumns      = ['name','category','sla','approval','actions'];

  tabs = [
    { id:'mine', label:'My Requests',   icon:'person'      },
    { id:'all',  label:'All Requests',  icon:'list_alt'    },
    { id:'types',label:'Request Types', icon:'tune'        },
  ];

  statusTabs = [
    { id:'',               label:'All'             },
    { id:'pending',        label:'Pending'         },
    { id:'pending_manager',label:'Pending Manager' },
    { id:'in_progress',    label:'In Progress'     },
    { id:'completed',      label:'Completed'       },
    { id:'rejected',       label:'Rejected'        },
    { id:'cancelled',      label:'Cancelled'       },
  ];

  categories = [
    { id:'visa',      label:'Visa',       icon:'flight_takeoff',  color:'#3b82f6' },
    { id:'travel',    label:'Travel',     icon:'airplane_ticket', color:'#f59e0b' },
    { id:'documents', label:'Documents',  icon:'description',     color:'#10b981' },
    { id:'hr',        label:'HR',         icon:'badge',           color:'#8b5cf6' },
    { id:'it',        label:'IT',         icon:'computer',        color:'#ef4444' },
    { id:'admin',     label:'Admin',      icon:'admin_panel_settings', color:'#ec4899' },
    { id:'finance',   label:'Finance',    icon:'payments',        color:'#0ea5e9' },
    { id:'other',     label:'Other',      icon:'help_outline',    color:'#6b7280' },
  ];

  materialIcons = ['description','flight_takeoff','airplane_ticket','family_restroom','payments',
    'badge','account_balance','verified','mail','computer','lock_open','email',
    'admin_panel_settings','local_parking','contact_page','inventory_2','monetization_on',
    'manage_accounts','home_work','school','workspace_premium','health_and_safety',
    'business_center','swap_horiz','help_outline'];

  constructor(private http: HttpClient, private auth: AuthService) {}

  ngOnInit() {
    this.loadStats();
    this.loadRequestTypes();
    this.load();
  }

  loadStats() {
    this.http.get<any>('/api/v1/requests/stats').subscribe({ next: r => {
      this.stats = r;
      this.statItems = [
        { label:'Pending',     value: r.pending,     icon:'hourglass_empty',  color:'#f59e0b' },
        { label:'In Progress', value: r.in_progress, icon:'sync',             color:'#3b82f6' },
        { label:'Completed',   value: r.completed,   icon:'check_circle',     color:'#10b981' },
        { label:'Overdue',     value: r.overdue,     icon:'warning',          color:'#ef4444' },
      ];
    }});
  }

  loadRequestTypes() {
    this.http.get<any>('/api/v1/requests/types').subscribe({ next: r => this.requestTypes = r?.types || [] });
    this.http.get<any>('/api/v1/requests/types/all').subscribe({ next: r => this.allRequestTypes = r?.types || [] });
  }

  load(page = 1) {
    this.loading = true; this.currentPage = page;
    const params: any = { per_page: 15, page };
    if (this.activeTab === 'mine') params.scope = 'mine';
    if (this.activeStatus)   params.status       = this.activeStatus;
    if (this.filterCategory) params.category     = this.filterCategory;
    if (this.filterTypeId)   params.request_type_id = this.filterTypeId;
    if (this.filterSearch)   params.search       = this.filterSearch;
    this.http.get<any>('/api/v1/requests', { params }).subscribe({
      next: r => { this.requests = r?.data || []; this.pagination = r; this.loading = false; },
      error: () => this.loading = false
    });
  }

  switchTab(id: string) { this.activeTab = id; this.activeStatus = ''; this.filterCategory = ''; if (id !== 'types') this.load(); }
  switchStatus(id: string) { this.activeStatus = id; this.load(); }
  filterByCat(id: string) { this.filterCategory = this.filterCategory === id ? '' : id; this.load(); }

  // ── View detail ────────────────────────────────────────────────────────
  viewReq(r: any) {
    this.http.get<any>(`/api/v1/requests/${r.id}`).subscribe({ next: res => {
      this.selectedReq = res.request;
      this.showDetail  = true;
      this.newComment  = '';
    }});
  }

  reloadDetail() {
    if (this.selectedReq) {
      this.http.get<any>(`/api/v1/requests/${this.selectedReq.id}`).subscribe({ next: r => { this.selectedReq = r.request; }});
    }
  }

  // ── New request ─────────────────────────────────────────────────────────
  openNew() {
    this.form      = { request_type_id: '', details: '', required_by: '', copies_needed: 1 };
    this.formError = ''; this.selectedType = null;
    this.showNew   = true;
  }

  onTypeSelect() {
    this.selectedType = this.requestTypes.find(t => t.id == this.form.request_type_id) || null;
  }

  submitRequest() {
    if (!this.form.request_type_id || !this.form.details) {
      this.formError = 'Request type and details are required.'; return;
    }
    this.submitting = true; this.formError = '';
    this.http.post<any>('/api/v1/requests', this.form).subscribe({
      next: () => { this.submitting = false; this.showNew = false; this.load(); this.loadStats(); },
      error: err => { this.submitting = false; this.formError = err?.error?.message || 'Failed to submit.'; }
    });
  }

  // ── Manager approve ─────────────────────────────────────────────────────
  managerApprove(req: any) {
    this.http.post(`/api/v1/requests/${req.id}/manager-approve`, {}).subscribe({
      next: () => { this.load(this.currentPage); this.loadStats(); if (this.showDetail) this.reloadDetail(); }
    });
  }

  // ── Take / assign ───────────────────────────────────────────────────────
  takeRequest(req: any) {
    this.http.post(`/api/v1/requests/${req.id}/assign`, {}).subscribe({
      next: () => { this.load(this.currentPage); this.loadStats(); if (this.showDetail) this.reloadDetail(); }
    });
  }

  // ── Complete ─────────────────────────────────────────────────────────────
  openComplete(req: any) {
    this.selectedReq  = req; this.completeForm = { completion_notes:'', hr_notes:'' };
    this.showComplete = true;
  }

  submitComplete() {
    this.http.post(`/api/v1/requests/${this.selectedReq.id}/complete`, this.completeForm).subscribe({
      next: () => { this.showComplete = false; this.load(this.currentPage); this.loadStats(); if (this.showDetail) this.reloadDetail(); }
    });
  }

  // ── Reject ─────────────────────────────────────────────────────────────
  openReject(req: any) { this.rejectTarget = req; this.rejectReason = ''; this.showReject = true; }

  confirmReject() {
    if (!this.rejectReason.trim()) return;
    this.http.post(`/api/v1/requests/${this.rejectTarget.id}/reject`, { reason: this.rejectReason }).subscribe({
      next: () => { this.showReject = false; this.load(this.currentPage); this.loadStats(); if (this.showDetail) this.showDetail = false; }
    });
  }

  // ── Cancel ─────────────────────────────────────────────────────────────
  cancelReq(req: any) {
    if (!confirm('Cancel this request?')) return;
    this.http.post(`/api/v1/requests/${req.id}/cancel`, {}).subscribe({
      next: () => { this.load(this.currentPage); this.loadStats(); if (this.showDetail) this.showDetail = false; }
    });
  }

  // ── Comments ───────────────────────────────────────────────────────────
  sendComment() {
    if (!this.newComment.trim() || !this.selectedReq) return;
    this.sendingComment = true;
    this.http.post(`/api/v1/requests/${this.selectedReq.id}/comments`, { comment: this.newComment }).subscribe({
      next: () => { this.sendingComment = false; this.newComment = ''; this.reloadDetail(); },
      error: () => this.sendingComment = false
    });
  }

  // ── Types CRUD ──────────────────────────────────────────────────────────
  openTypeForm(t?: any) {
    if (t) { this.typeEditId = t.id; this.typeForm = { ...t }; }
    else   { this.typeEditId = null; this.typeForm = { name:'', code:'', category:'documents', description:'', instructions:'',
      sla_days:3, requires_attachment:false, requires_manager_approval:false, is_active:true, sort_order:0, icon:'description', color:'#6366f1' }; }
    this.showTypeForm = true;
  }

  saveType() {
    if (!this.typeForm.name || !this.typeForm.code) return;
    this.typeSaving = true;
    const req = this.typeEditId
      ? this.http.put(`/api/v1/requests/types/${this.typeEditId}`, this.typeForm)
      : this.http.post('/api/v1/requests/types', this.typeForm);
    req.subscribe({ next: () => { this.typeSaving = false; this.showTypeForm = false; this.loadRequestTypes(); }, error: () => this.typeSaving = false });
  }

  // ── Letter generation ────────────────────────────────────────────────
  generatingLetter  = false;
  letterSuccess     = '';
  letterError       = '';

  // Reads employee ID reliably from the auth user stored at login
  get myEmployeeId(): number | null {
    return this.auth.getUser()?.employee?.id ?? null;
  }

  readonly LETTER_TYPES = [
    { code:'DOC_SALARY',    label:'Salary Certificate',       icon:'payments'          },
    { code:'DOC_EMPLOY',    label:'Employment Certificate',   icon:'badge'             },
    { code:'DOC_EXP',       label:'Experience Letter',        icon:'workspace_premium' },
    { code:'DOC_NOC',       label:'NOC Letter',               icon:'verified'          },
    { code:'DOC_BANK',      label:'Bank Letter',              icon:'account_balance'   },
    { code:'DOC_SALARY_TR', label:'Salary Transfer Letter',   icon:'swap_horiz'        },
  ];

  generateLetterFromRequest(req: any) {
    this.generatingLetter = true;
    this.letterSuccess    = '';
    this.letterError      = '';
    const token = this.auth.getToken() ?? '';
    this.http.get(`/api/v1/requests/${req.id}/generate-letter`, {
      responseType: 'blob',
      headers: { Authorization: `Bearer ${token}` },
    }).subscribe({
      next: (blob: Blob) => {
        this.generatingLetter = false;
        const typeName = req.request_type?.name ?? req.requestType?.name ?? 'Letter';
        this.letterSuccess = `${typeName} generated!`;
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = `${typeName.replace(/\s+/g,'_')}_${req.reference ?? req.id}_${new Date().toISOString().slice(0,10)}.pdf`;
        a.click();
        URL.revokeObjectURL(a.href);
        setTimeout(() => this.letterSuccess = '', 4000);
      },
      error: err => {
        this.generatingLetter = false;
        if (err.error instanceof Blob) {
          err.error.text().then((t: string) => {
            try { this.letterError = JSON.parse(t)?.message || 'Generation failed.'; }
            catch { this.letterError = 'Generation failed. Check server logs.'; }
          });
        } else {
          this.letterError = err?.error?.message || 'Generation failed.';
        }
      },
    });
  }

  generateDirectLetter(empIdOverride: number | null, typeCode: string) {
    // Always prefer the auth-service employee ID (reliable) over passed-in value
    const empId = empIdOverride ?? this.myEmployeeId;
    if (!empId) {
      this.letterError = 'Employee record not linked to your account. Contact HR.';
      return;
    }
    this.generatingLetter = true;
    this.letterError  = '';
    this.letterSuccess = '';
    const token = this.auth.getToken() ?? '';
    this.http.get(`/api/v1/employees/${empId}/letter/${typeCode}`, {
      responseType: 'blob',
      headers: { Authorization: `Bearer ${token}` },
    }).subscribe({
      next: (blob: Blob) => {
        this.generatingLetter = false;
        const lt = this.LETTER_TYPES.find(l => l.code === typeCode);
        const a  = document.createElement('a');
        a.href   = URL.createObjectURL(blob);
        a.download = `${(lt?.label || typeCode).replace(/\s+/g,'_')}_${new Date().toISOString().slice(0,10)}.pdf`;
        a.click();
        URL.revokeObjectURL(a.href);
        this.letterSuccess = (lt?.label ?? 'Letter') + ' downloaded successfully!';
        setTimeout(() => this.letterSuccess = '', 4000);
      },
      error: err => {
        this.generatingLetter = false;
        // Try to read error message from blob response
        if (err.error instanceof Blob) {
          err.error.text().then((t: string) => {
            try { this.letterError = JSON.parse(t)?.message || 'Generation failed.'; }
            catch { this.letterError = 'Generation failed. Check server logs.'; }
          });
        } else {
          this.letterError = err?.error?.message || 'Generation failed. Check server logs.';
        }
      },
    });
  }

  isLetterRequest(req: any): boolean {
    const letterCodes = ['DOC_SALARY','DOC_EMPLOY','DOC_EXP','DOC_NOC','DOC_BANK','DOC_SALARY_TR','TRAVEL_LETTER'];
    // Laravel serialises the 'requestType' eager-load relationship as 'request_type' in JSON
    const code = req?.request_type?.code ?? req?.requestType?.code ?? '';
    return letterCodes.includes(code);
  }

  // ── Helpers ─────────────────────────────────────────────────────────────
  get pages(): number[] {
    if (!this.pagination?.last_page) return [];
    return Array.from({ length: Math.min(this.pagination.last_page, 8) }, (_, i) => i + 1);
  }

  get columns(): string[] { return this.activeTab === 'mine' ? this.mineColumns : this.displayedColumns; }

  catInfo(id: string): any { return this.categories.find(c => c.id === id) || { label: id, icon: 'help_outline', color: '#8b949e' }; }

  statusLabel(s: string): string {
    return ({ pending:'Pending', pending_manager:'Pending Manager', in_progress:'In Progress',
      completed:'Completed', rejected:'Rejected', cancelled:'Cancelled' } as any)[s] || s;
  }

  statusCls(s: string): string {
    return ({ pending:'badge-yellow', pending_manager:'badge-orange', in_progress:'badge-blue',
      completed:'badge-green', rejected:'badge-red', cancelled:'badge-gray' } as any)[s] || 'badge-gray';
  }

  statusIcon(s: string): string {
    return ({ pending:'hourglass_empty', pending_manager:'manage_accounts', in_progress:'sync',
      completed:'check_circle', rejected:'cancel', cancelled:'block' } as any)[s] || 'help';
  }

  slaStatus(req: any): any {
    if (!req.due_date || ['completed','rejected','cancelled'].includes(req.status)) return null;
    const days = Math.ceil((new Date(req.due_date).getTime() - Date.now()) / 86400000);
    if (days < 0)  return { label: Math.abs(days) + 'd overdue', cls: 'sla-red'    };
    if (days === 0) return { label: 'Due today',                  cls: 'sla-red'    };
    if (days <= 1)  return { label: days + 'd left',              cls: 'sla-orange' };
    return { label: days + 'd left', cls: 'sla-green' };
  }

  avatarColor(name: string): string {
    const colors = ['#3b82f6','#6366f1','#8b5cf6','#ec4899','#10b981','#f59e0b','#ef4444','#0ea5e9'];
    return colors[(name?.charCodeAt(0) || 0) % colors.length];
  }

  typesByCategory(cat: string): any[] { return this.requestTypes.filter(t => t.category === cat); }

  groupedTypes(): any[] {
    const grouped: any = {};
    for (const t of this.requestTypes) {
      if (!grouped[t.category]) grouped[t.category] = { ...this.catInfo(t.category), types: [] };
      grouped[t.category].types.push(t);
    }
    return Object.values(grouped);
  }

  canTake(req: any): boolean   { return req.status === 'pending'; }
  canComplete(req: any): boolean { return req.status === 'in_progress'; }
  canReject(req: any): boolean  { return ['pending','pending_manager','in_progress'].includes(req.status); }
  canCancel(req: any): boolean  { return ['pending','pending_manager'].includes(req.status); }
}
