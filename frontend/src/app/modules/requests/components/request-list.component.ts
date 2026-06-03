import { Component, OnInit, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { AuthService } from '../../../core/services/auth.service';

@Component({
  standalone: false,
  selector: 'app-request-list',
  templateUrl: './request-list.component.html',
  styleUrls: ['./request-list.component.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class RequestListComponent implements OnInit {

  // ── Tabs ────────────────────────────────────────────────────────────────
  activeTab    = 'mine';
  activeStatus = '';
  loading      = false;
  submitting   = false;

  // ── Data ─────────────────────────────────────────────────────────────────
  requests:       any[] = [];
  requestTypes:   any[] = [];
  allRequestTypes:any[] = [];
  stats:          any   = {};
  statItems:      any[] = [];
  pagination:     any   = null;
  currentPage           = 1;

  // ── Filters ──────────────────────────────────────────────────────────────
  filterSearch   = '';
  filterCategory = '';
  filterTypeId   = '';

  // ── Role flags ───────────────────────────────────────────────────────────
  isHR           = false;
  isMgr          = false;
  currentUserId: number | null = null;

  // ── Panels ───────────────────────────────────────────────────────────────
  showNew      = false;
  showDetail   = false;
  showReject   = false;
  showComplete = false;
  showTypeForm = false;
  showAssign   = false;

  selectedReq:   any = null;
  rejectTarget:  any = null;
  assignTarget:  any = null;
  rejectReason       = '';
  newComment         = '';
  sendingComment     = false;

  // ── New request form ──────────────────────────────────────────────────────
  form: any    = { request_type_id: '', details: '', required_by: '', copies_needed: 1 };
  formError    = '';
  selectedType:any = null;
  selectedFile: File | null = null;
  fileError    = '';

  // ── Complete form ─────────────────────────────────────────────────────────
  completeForm:    any  = { completion_notes: '', hr_notes: '' };
  completionFile:  File | null = null;

  // ── Assign form ───────────────────────────────────────────────────────────
  assignForm       = { assigned_to: '', notes: '' };
  assignableGroups: any[] = [];
  assigning        = false;

  // ── Type form ─────────────────────────────────────────────────────────────
  typeForm: any = {
    name: '', code: '', category: 'documents', description: '', instructions: '',
    sla_days: 3, requires_attachment: false, requires_manager_approval: false,
    is_active: true, sort_order: 0, icon: 'description', color: '#6366f1',
  };
  typeEditId:   number | null = null;
  typeSaving    = false;
  typeToDelete: any  = null;
  typeDeleting  = false;

  // ── Table columns ─────────────────────────────────────────────────────────
  displayedColumns = ['ref','employee','type','details','required_by','status','sla','actions'];
  mineColumns      = ['ref','type','details','required_by','status','sla','actions'];
  typeColumns      = ['name','category','sla','approval','actions'];

  tabs = [
    { id: 'mine',  label: 'My Requests',   icon: 'person'   },
    { id: 'all',   label: 'All Requests',  icon: 'list_alt' },
    { id: 'types', label: 'Request Types', icon: 'tune'     },
  ];

  statusTabs = [
    { id: '',            label: 'All'         },
    { id: 'pending',     label: 'Pending'     },
    { id: 'in_progress', label: 'In Progress' },
    { id: 'completed',   label: 'Completed'   },
    { id: 'rejected',    label: 'Rejected'    },
  ];

  categories = [
    { id: 'visa',      label: 'Visa',       icon: 'flight_takeoff',       color: '#3b82f6' },
    { id: 'travel',    label: 'Travel',     icon: 'airplane_ticket',      color: '#f59e0b' },
    { id: 'documents', label: 'Documents',  icon: 'description',          color: '#10b981' },
    { id: 'hr',        label: 'HR',         icon: 'badge',                color: '#8b5cf6' },
    { id: 'it',        label: 'IT',         icon: 'computer',             color: '#ef4444' },
    { id: 'admin',     label: 'Admin',      icon: 'admin_panel_settings', color: '#ec4899' },
    { id: 'finance',   label: 'Finance',    icon: 'payments',             color: '#0ea5e9' },
    { id: 'other',     label: 'Other',      icon: 'help_outline',         color: '#6b7280' },
  ];

  materialIcons = [
    'description','flight_takeoff','airplane_ticket','family_restroom','payments',
    'badge','account_balance','verified','mail','computer','lock_open','email',
    'admin_panel_settings','local_parking','contact_page','inventory_2',
    'monetization_on','manage_accounts','home_work','school','workspace_premium',
    'health_and_safety','business_center','swap_horiz','help_outline',
  ];

  /** Category → department keyword mapping for smart assignment */
  private categoryDeptMap: Record<string, string> = {
    it:        'IT',
    finance:   'Finance',
    admin:     'Admin',
    hr:        'Human Resources',
    travel:    'Admin',
    visa:      'Admin',
    documents: 'Human Resources',
    other:     '',
  };

  constructor(
    private http: HttpClient,
    private auth: AuthService,
    private cdr:  ChangeDetectorRef,
  ) {}

  ngOnInit(): void {
    this.isHR          = this.auth.isHRRole();
    this.isMgr         = this.auth.isManagerRole();
    this.currentUserId = this.auth.getUser()?.id ?? null;
    this.loadStats();
    this.loadRequestTypes();
    this.load();
    if (this.isHR || this.isMgr) this.loadAssignableUsers();
  }

  // ── Data loaders ────────────────────────────────────────────────────────

  loadStats(): void {
    this.http.get<any>('/api/v1/requests/stats').subscribe({
      next: r => {
        this.stats     = r;
        this.statItems = [
          { label: 'Pending',     value: r.pending,     icon: 'hourglass_empty', color: '#f59e0b' },
          { label: 'In Progress', value: r.in_progress, icon: 'sync',            color: '#3b82f6' },
          { label: 'Completed',   value: r.completed,   icon: 'check_circle',    color: '#10b981' },
          { label: 'Overdue',     value: r.overdue,     icon: 'warning',         color: '#ef4444' },
        ];
        this.cdr.markForCheck();
      },
    });
  }

  loadRequestTypes(): void {
    this.http.get<any>('/api/v1/requests/types').subscribe({
      next: r => { this.requestTypes = r?.types || []; this.cdr.markForCheck(); },
    });
    this.http.get<any>('/api/v1/requests/types/all').subscribe({
      next: r => { this.allRequestTypes = r?.types || []; this.cdr.markForCheck(); },
    });
  }

  load(page = 1): void {
    this.loading = true;
    this.currentPage = page;
    const params: any = { per_page: 15, page };
    if (this.activeTab === 'mine') params.scope           = 'mine';
    if (this.activeStatus)        params.status          = this.activeStatus;
    if (this.filterCategory)      params.category        = this.filterCategory;
    if (this.filterTypeId)        params.request_type_id = this.filterTypeId;
    if (this.filterSearch)        params.search          = this.filterSearch;

    this.http.get<any>('/api/v1/requests', { params }).subscribe({
      next: r => {
        this.requests   = r?.data || [];
        this.pagination = r;
        this.loading    = false;
        this.cdr.markForCheck();
      },
      error: () => { this.loading = false; this.cdr.markForCheck(); },
    });
  }

  loadAssignableUsers(): void {
    this.http.get<any>('/api/v1/requests/assignable-users').subscribe({
      next: r => { this.assignableGroups = r?.groups || []; this.cdr.markForCheck(); },
    });
  }

  // ── Tab / filter ────────────────────────────────────────────────────────

  switchTab(id: string): void {
    this.activeTab    = id;
    this.activeStatus = '';
    this.filterCategory = '';
    if (id !== 'types') this.load();
  }

  switchStatus(id: string): void { this.activeStatus = id; this.load(); }

  // ── Detail view ─────────────────────────────────────────────────────────

  viewReq(r: any): void {
    this.http.get<any>(`/api/v1/requests/${r.id}`).subscribe({
      next: res => {
        this.selectedReq = res.request;
        this.showDetail  = true;
        this.newComment  = '';
        this.cdr.markForCheck();
      },
    });
  }

  reloadDetail(): void {
    if (!this.selectedReq) return;
    this.http.get<any>(`/api/v1/requests/${this.selectedReq.id}`).subscribe({
      next: r => { this.selectedReq = r.request; this.cdr.markForCheck(); },
    });
  }

  // ── New request ─────────────────────────────────────────────────────────

  openNew(): void {
    this.form         = { request_type_id: '', details: '', required_by: '', copies_needed: 1 };
    this.formError    = '';
    this.selectedType = null;
    this.selectedFile = null;
    this.fileError    = '';
    this.showNew      = true;
    this.cdr.markForCheck();
  }

  onTypeSelect(): void {
    this.selectedType = this.requestTypes.find(t => t.id == this.form.request_type_id) || null;
    this.cdr.markForCheck();
  }

  submitRequest(): void {
    if (!this.form.request_type_id || !this.form.details) {
      this.formError = 'Request type and details are required.'; return;
    }
    if (this.selectedType?.requires_attachment && !this.selectedFile) {
      this.formError = 'A supporting document is required.'; return;
    }
    this.submitting = true;
    this.formError  = '';
    const fd = new FormData();
    Object.entries(this.form).forEach(([k, v]) => { if (v) fd.append(k, String(v)); });
    if (this.selectedFile) fd.append('attachment', this.selectedFile, this.selectedFile.name);

    this.http.post<any>('/api/v1/requests', fd).subscribe({
      next: () => {
        this.submitting   = false;
        this.showNew      = false;
        this.selectedFile = null;
        this.load();
        this.loadStats();
        this.cdr.markForCheck();
      },
      error: err => {
        this.submitting = false;
        this.formError  = err?.error?.message || 'Failed to submit.';
        this.cdr.markForCheck();
      },
    });
  }

  onFileSelected(event: Event): void {
    const f = (event.target as HTMLInputElement).files?.[0] ?? null;
    this.fileError = '';
    if (!f) { this.selectedFile = null; return; }
    if (f.size > 10 * 1024 * 1024) { this.fileError = 'Max 10 MB.'; return; }
    this.selectedFile = f;
  }

  onCompletionFileSelected(event: Event): void {
    this.completionFile = (event.target as HTMLInputElement).files?.[0] ?? null;
  }

  // ── Manager approve ─────────────────────────────────────────────────────

  managerApprove(req: any): void {
    this.http.post(`/api/v1/requests/${req.id}/manager-approve`, {}).subscribe({
      next: () => {
        this.load(this.currentPage);
        this.loadStats();
        if (this.showDetail) this.reloadDetail();
        this.cdr.markForCheck();
      },
      error: (e: any) => alert('Could not approve: ' + (e?.error?.message || 'Server error.')),
    });
  }

  // ── Assign ───────────────────────────────────────────────────────────────

  openAssign(req: any): void {
    this.assignTarget = req;
    this.assignForm   = { assigned_to: '', notes: '' };
    this.assigning    = false;
    this.showAssign   = true;
    this.cdr.markForCheck();
  }

  submitAssign(): void {
    if (!this.assignTarget) return;
    this.assigning = true;
    const body: any = { hr_notes: this.assignForm.notes };
    if (this.assignForm.assigned_to) body.assigned_to = this.assignForm.assigned_to;

    this.http.post(`/api/v1/requests/${this.assignTarget.id}/assign`, body).subscribe({
      next: () => {
        this.assigning  = false;
        this.showAssign = false;
        this.load(this.currentPage);
        this.loadStats();
        if (this.showDetail) this.reloadDetail();
        this.cdr.markForCheck();
      },
      error: (e: any) => {
        this.assigning = false;
        this.cdr.markForCheck();
        alert('Could not assign: ' + (e?.error?.message || 'Server error.'));
      },
    });
  }

  /** Returns staff in the recommended department based on request category */
  recommendedUsers(req: any): any[] {
    const cat     = req?.request_type?.category || '';
    const keyword = (this.categoryDeptMap[cat] || '').toLowerCase();
    if (!keyword) return [];
    return this.assignableGroups
      .filter(g => g.department.toLowerCase().includes(keyword))
      .flatMap(g => g.users);
  }

  // ── Complete ─────────────────────────────────────────────────────────────

  openComplete(req: any): void {
    this.selectedReq  = req;
    this.completeForm = { completion_notes: '', hr_notes: '' };
    this.completionFile = null;
    this.showComplete = true;
    this.cdr.markForCheck();
  }

  submitComplete(): void {
    const fd = new FormData();
    if (this.completeForm.completion_notes) fd.append('completion_notes', this.completeForm.completion_notes);
    if (this.completeForm.hr_notes)         fd.append('hr_notes',         this.completeForm.hr_notes);
    if (this.completionFile) fd.append('completion_file', this.completionFile, this.completionFile.name);

    this.http.post(`/api/v1/requests/${this.selectedReq.id}/complete`, fd).subscribe({
      next: () => {
        this.showComplete   = false;
        this.completionFile = null;
        this.load(this.currentPage);
        this.loadStats();
        if (this.showDetail) this.reloadDetail();
        this.cdr.markForCheck();
      },
      error: (e: any) => {
        alert('Could not complete: ' + (e?.error?.message || 'Server error.'));
        this.cdr.markForCheck();
      },
    });
  }

  // ── Reject ──────────────────────────────────────────────────────────────

  openReject(req: any): void {
    this.rejectTarget = req;
    this.rejectReason = '';
    this.showReject   = true;
    this.cdr.markForCheck();
  }

  confirmReject(): void {
    if (!this.rejectReason.trim()) return;
    this.http.post(`/api/v1/requests/${this.rejectTarget.id}/reject`, { reason: this.rejectReason }).subscribe({
      next: () => {
        this.showReject = false;
        this.load(this.currentPage);
        this.loadStats();
        if (this.showDetail) this.showDetail = false;
        this.cdr.markForCheck();
      },
      error: (e: any) => alert('Could not reject: ' + (e?.error?.message || 'Server error.')),
    });
  }

  // ── Cancel ──────────────────────────────────────────────────────────────

  cancelReq(req: any): void {
    if (!confirm('Cancel this request?')) return;
    this.http.post(`/api/v1/requests/${req.id}/cancel`, {}).subscribe({
      next: () => {
        this.load(this.currentPage);
        this.loadStats();
        if (this.showDetail) this.showDetail = false;
        this.cdr.markForCheck();
      },
      error: (e: any) => alert('Could not cancel: ' + (e?.error?.message || 'Server error.')),
    });
  }

  // ── Comments ────────────────────────────────────────────────────────────

  sendComment(): void {
    if (!this.newComment.trim() || !this.selectedReq) return;
    this.sendingComment = true;
    this.http.post(`/api/v1/requests/${this.selectedReq.id}/comments`, { comment: this.newComment }).subscribe({
      next: () => {
        this.sendingComment = false;
        this.newComment     = '';
        this.reloadDetail();
        this.cdr.markForCheck();
      },
      error: () => { this.sendingComment = false; this.cdr.markForCheck(); },
    });
  }

  // ── Type CRUD ────────────────────────────────────────────────────────────

  openTypeForm(t?: any): void {
    if (t) {
      this.typeEditId = t.id;
      this.typeForm   = { ...t };
    } else {
      this.typeEditId = null;
      this.typeForm   = {
        name: '', code: '', category: 'documents', description: '', instructions: '',
        sla_days: 3, requires_attachment: false, requires_manager_approval: false,
        is_active: true, sort_order: 0, icon: 'description', color: '#6366f1',
      };
    }
    this.showTypeForm = true;
    this.cdr.markForCheck();
  }

  saveType(): void {
    if (!this.typeForm.name || !this.typeForm.code) return;
    this.typeSaving = true;
    const req = this.typeEditId
      ? this.http.put(`/api/v1/requests/types/${this.typeEditId}`, this.typeForm)
      : this.http.post('/api/v1/requests/types', this.typeForm);

    req.subscribe({
      next: () => {
        this.typeSaving   = false;
        this.showTypeForm = false;
        this.loadRequestTypes();
        this.cdr.markForCheck();
      },
      error: (e: any) => {
        this.typeSaving = false;
        alert('Could not save: ' + (e?.error?.message || e?.error?.errors?.code?.[0] || 'Server error.'));
        this.cdr.markForCheck();
      },
    });
  }

  openDeleteFromEdit(): void {
    this.typeToDelete = { id: this.typeEditId, ...this.typeForm };
    this.showTypeForm = false;
    this.cdr.markForCheck();
  }

  confirmDeleteType(): void {
    if (!this.typeToDelete) return;
    this.typeDeleting = true;
    this.http.delete(`/api/v1/requests/types/${this.typeToDelete.id}`).subscribe({
      next: () => {
        this.typeToDelete = null;
        this.typeDeleting = false;
        this.loadRequestTypes();
        this.cdr.markForCheck();
      },
      error: (e: any) => {
        this.typeDeleting = false;
        this.typeToDelete = null;
        alert('Cannot delete: ' + (e?.error?.message || 'The type may have existing requests.'));
        this.cdr.markForCheck();
      },
    });
  }

  // ── Helpers ──────────────────────────────────────────────────────────────

  get pages(): number[] {
    if (!this.pagination?.last_page) return [];
    return Array.from({ length: Math.min(this.pagination.last_page, 8) }, (_, i) => i + 1);
  }

  get columns(): string[] {
    return this.activeTab === 'mine' ? this.mineColumns : this.displayedColumns;
  }

  catInfo(id: string): any {
    return this.categories.find(c => c.id === id) || { label: id, icon: 'help_outline', color: '#8b949e' };
  }

  groupedTypes(): any[] {
    const grouped: any = {};
    for (const t of this.requestTypes) {
      if (!grouped[t.category]) grouped[t.category] = { ...this.catInfo(t.category), types: [] };
      grouped[t.category].types.push(t);
    }
    return Object.values(grouped);
  }

  statusLabel(s: string): string {
    const map: any = {
      pending: 'Pending', pending_manager: 'Pending Manager',
      in_progress: 'In Progress', completed: 'Completed',
      rejected: 'Rejected', cancelled: 'Cancelled',
    };
    return map[s] || s;
  }

  statusCls(s: string): string {
    const map: any = {
      pending: 'badge-yellow', pending_manager: 'badge-orange',
      in_progress: 'badge-blue', completed: 'badge-green',
      rejected: 'badge-red', cancelled: 'badge-draft',
    };
    return map[s] || 'badge-draft';
  }

  statusIcon(s: string): string {
    const map: any = {
      pending: 'hourglass_empty', pending_manager: 'manage_accounts',
      in_progress: 'sync', completed: 'check_circle',
      rejected: 'cancel', cancelled: 'block',
    };
    return map[s] || 'help';
  }

  slaStatus(req: any): { label: string; cls: string } | null {
    if (!req.due_date || ['completed', 'rejected', 'cancelled'].includes(req.status)) return null;
    const days = Math.ceil((new Date(req.due_date).getTime() - Date.now()) / 86400000);
    if (days < 0)   return { label: Math.abs(days) + 'd overdue', cls: 'sla-red'    };
    if (days === 0) return { label: 'Due today',                   cls: 'sla-red'    };
    if (days <= 1)  return { label: days + 'd left',               cls: 'sla-orange' };
    return { label: days + 'd left', cls: 'sla-green' };
  }

  avatarColor(name: string): string {
    const colors = ['#3b82f6','#6366f1','#8b5cf6','#ec4899','#10b981','#f59e0b','#ef4444','#0ea5e9'];
    return colors[(name?.charCodeAt(0) || 0) % colors.length];
  }

  // ── Permission helpers ────────────────────────────────────────────────────

  canMgrApprove(req: any): boolean {
    return req.status === 'pending_manager' && (this.isMgr || this.isHR);
  }

  canAssign(req: any): boolean {
    if (this.isHR) return ['pending', 'in_progress'].includes(req.status);
    return req.status === 'pending' && this.isMgr;
  }

  canComplete(req: any): boolean {
    if (req.status !== 'in_progress') return false;
    return this.isHR || req.assigned_to?.id === this.currentUserId;
  }

  canReject(req: any): boolean {
    return ['pending', 'pending_manager', 'in_progress'].includes(req.status) && (this.isHR || this.isMgr);
  }

  canCancel(req: any): boolean {
    return ['pending', 'pending_manager'].includes(req.status);
  }
}
