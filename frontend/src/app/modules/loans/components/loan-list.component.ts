import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';

@Component({
  standalone: false,
  selector: 'app-loan-list',
  templateUrl: './loan-list.component.html',
  styleUrls: ['./loan-list.component.scss'],
})
export class LoanListComponent implements OnInit {

  // ── Tabs ────────────────────────────────────────────────────────────────
  activeTab    = 'all';   // all | mine | types
  activeStatus = '';

  // ── Data ────────────────────────────────────────────────────────────────
  loans:        any[] = [];
  myLoans:      any[] = [];
  loanTypes:    any[] = [];
  stats:        any   = {};
  pagination:   any   = null;
  statItems:    any[] = [];
  loading             = false;
  submitting          = false;

  // ── Filters ─────────────────────────────────────────────────────────────
  filterSearch  = '';
  filterType    = '';
  currentPage   = 1;

  // ── Panels ──────────────────────────────────────────────────────────────
  showNewLoan     = false;
  showDetail      = false;
  showReject      = false;
  showApprove     = false;   // finance approval with amount override
  showTypeForm    = false;
  showInstPanel   = false;   // installment schedule drawer

  selectedLoan:  any  = null;
  rejectTarget:  any  = null;
  rejectReason        = '';
  approveTarget: any  = null;
  instLoan:      any  = null; // loan whose schedule is open

  // ── Finance approve fields ───────────────────────────────────────────────
  financeForm = { approved_amount: 0, disbursed_date: '', first_installment_date: '' };

  // ── New Loan form ────────────────────────────────────────────────────────
  form: any = { loan_type_id: '', requested_amount: '', installments: 12, purpose: '', notes: '' };
  formError   = '';

  // ── Loan Type form ───────────────────────────────────────────────────────
  typeForm: any = { name:'', code:'', max_amount:0, max_installments:12, interest_rate:0, requires_guarantor:false, is_active:true, description:'' };
  typeEditId: number | null = null;
  typeError = '';
  typeSaving = false;

  // ── Installment actions ──────────────────────────────────────────────────
  showPayInst   = false;
  showSkipInst  = false;
  activeInst:   any  = null;
  instPayDate         = '';
  instNotes           = '';

  // ── Table columns ────────────────────────────────────────────────────────
  displayedColumns = ['ref','employee','type','amount','installments','status','progress','actions'];
  typeColumns      = ['name','code','max_amount','installments','interest','actions'];
  instColumns      = ['no','due_date','amount','status','actions'];

  tabs = [
    { id:'all',   label:'All Loans',   icon:'list_alt' },
    { id:'mine',  label:'My Loans',    icon:'person'   },
    { id:'types', label:'Loan Types',  icon:'tune'     },
  ];

  statusTabs = [
    { id:'',               label:'All'             },
    { id:'pending_manager',label:'Pending Manager' },
    { id:'pending_hr',     label:'Pending HR'      },
    { id:'pending_finance',label:'Pending Finance' },
    { id:'disbursed',      label:'Active'          },
    { id:'completed',      label:'Completed'       },
    { id:'rejected',       label:'Rejected'        },
  ];

  constructor(private http: HttpClient) {}

  ngOnInit() {
    this.loadStats();
    this.loadLoanTypes();
    this.load();
    this.loadMyLoans();
  }

  // ── Stats ─────────────────────────────────────────────────────────────
  loadStats() {
    this.http.get<any>('/api/v1/loans/stats').subscribe({ next: r => {
      this.stats = r;
      this.statItems = [
        { label:'Pending Manager', value: r.pending_manager,   icon:'manage_accounts',  color:'#f59e0b' },
        { label:'Pending HR',      value: r.pending_hr,        icon:'badge',            color:'#6366f1' },
        { label:'Pending Finance', value: r.pending_finance,   icon:'account_balance',  color:'#3b82f6' },
        { label:'Active Loans',    value: r.active_loans,      icon:'credit_card',      color:'#10b981' },
        { label:'Total Outstanding', value: this.formatSAR(r.total_outstanding), icon:'payments', color:'#ec4899' },
        { label:'Completed',       value: r.completed,         icon:'check_circle',     color:'#8b949e' },
      ];
    }});
  }

  // ── Load loans list ───────────────────────────────────────────────────
  load(page = 1) {
    this.loading = true; this.currentPage = page;
    const params: any = { per_page: 15, page };
    if (this.activeStatus) params.status       = this.activeStatus;
    if (this.filterType)   params.loan_type_id = this.filterType;
    if (this.filterSearch) params.search       = this.filterSearch;
    this.http.get<any>('/api/v1/loans', { params }).subscribe({
      next: r => { this.loans = r?.data || []; this.pagination = r; this.loading = false; },
      error: () => this.loading = false
    });
  }

  loadMyLoans() {
    this.http.get<any>('/api/v1/loans/my').subscribe({ next: r => this.myLoans = r?.loans || [] });
  }

  loadLoanTypes() {
    this.http.get<any>('/api/v1/loans/types/all').subscribe({ next: r => this.loanTypes = r?.types || [] });
  }

  switchTab(id: string) {
    this.activeTab = id;
    if (id === 'all') this.load();
  }

  switchStatus(id: string) { this.activeStatus = id; this.load(); }

  // ── View detail ───────────────────────────────────────────────────────
  viewLoan(loan: any) {
    this.http.get<any>(`/api/v1/loans/${loan.id}`).subscribe({ next: r => {
      this.selectedLoan = r.loan;
      this.showDetail   = true;
    }});
  }

  // ── New loan request ──────────────────────────────────────────────────
  openNewLoan() {
    this.form      = { loan_type_id:'', requested_amount:'', installments:12, purpose:'', notes:'' };
    this.formError = '';
    this.showNewLoan = true;
  }

  submitLoan() {
    if (!this.form.loan_type_id || !this.form.requested_amount || !this.form.purpose) {
      this.formError = 'Loan type, amount and purpose are required.'; return;
    }
    this.submitting = true; this.formError = '';
    this.http.post<any>('/api/v1/loans', this.form).subscribe({
      next: () => {
        this.submitting = false; this.showNewLoan = false;
        this.load(); this.loadMyLoans(); this.loadStats();
      },
      error: err => { this.submitting = false; this.formError = err?.error?.message || 'Submit failed.'; }
    });
  }

  // ── Approve ───────────────────────────────────────────────────────────
  openApprove(loan: any) {
    if (loan.status === 'pending_finance') {
      this.approveTarget  = loan;
      this.financeForm    = {
        approved_amount:        loan.requested_amount,
        disbursed_date:         new Date().toISOString().slice(0,10),
        first_installment_date: ''
      };
      this.showApprove = true;
    } else {
      this.quickApprove(loan);
    }
  }

  quickApprove(loan: any) {
    this.http.post(`/api/v1/loans/${loan.id}/approve`, {}).subscribe({
      next: () => { this.load(this.currentPage); this.loadStats(); if (this.showDetail) this.reloadDetail(); }
    });
  }

  submitFinanceApproval() {
    this.http.post(`/api/v1/loans/${this.approveTarget.id}/approve`, this.financeForm).subscribe({
      next: () => {
        this.showApprove = false;
        this.load(this.currentPage); this.loadStats(); if (this.showDetail) this.reloadDetail();
      }
    });
  }

  // ── Reject ────────────────────────────────────────────────────────────
  openReject(loan: any) { this.rejectTarget = loan; this.rejectReason = ''; this.showReject = true; }

  confirmReject() {
    if (!this.rejectReason.trim()) return;
    this.http.post(`/api/v1/loans/${this.rejectTarget.id}/reject`, { reason: this.rejectReason }).subscribe({
      next: () => {
        this.showReject = false; this.load(this.currentPage); this.loadStats();
        if (this.showDetail) this.showDetail = false;
      }
    });
  }

  // ── Cancel ────────────────────────────────────────────────────────────
  cancel(loan: any) {
    if (!confirm('Cancel this loan request?')) return;
    this.http.post(`/api/v1/loans/${loan.id}/cancel`, {}).subscribe({
      next: () => { this.load(this.currentPage); this.loadStats(); if (this.showDetail) this.showDetail = false; }
    });
  }

  // ── Disburse ──────────────────────────────────────────────────────────
  disburse(loan: any) {
    if (!confirm(`Mark loan ${loan.reference} as disbursed?`)) return;
    this.http.post(`/api/v1/loans/${loan.id}/disburse`, {}).subscribe({
      next: () => { this.load(this.currentPage); this.loadStats(); if (this.showDetail) this.reloadDetail(); }
    });
  }

  // ── Installment schedule ──────────────────────────────────────────────
  openInstallments(loan: any) {
    this.http.get<any>(`/api/v1/loans/${loan.id}`).subscribe({ next: r => {
      this.instLoan    = r.loan;
      this.showInstPanel = true;
    }});
  }

  openPayInst(inst: any) {
    this.activeInst  = inst;
    this.instPayDate = new Date().toISOString().slice(0,10);
    this.instNotes   = '';
    this.showPayInst = true;
  }

  confirmPayInst() {
    this.http.post(`/api/v1/loans/${this.instLoan.id}/installments/${this.activeInst.id}/pay`, {
      paid_date: this.instPayDate, notes: this.instNotes
    }).subscribe({ next: () => { this.showPayInst = false; this.reloadInstLoan(); this.loadStats(); }});
  }

  openSkipInst(inst: any) { this.activeInst = inst; this.instNotes = ''; this.showSkipInst = true; }

  confirmSkipInst() {
    this.http.post(`/api/v1/loans/${this.instLoan.id}/installments/${this.activeInst.id}/skip`, {
      notes: this.instNotes
    }).subscribe({ next: () => { this.showSkipInst = false; this.reloadInstLoan(); }});
  }

  reloadInstLoan() {
    this.http.get<any>(`/api/v1/loans/${this.instLoan.id}`).subscribe({ next: r => this.instLoan = r.loan });
  }

  reloadDetail() {
    this.http.get<any>(`/api/v1/loans/${this.selectedLoan.id}`).subscribe({ next: r => this.selectedLoan = r.loan });
  }

  // ── Loan Types CRUD ───────────────────────────────────────────────────
  openTypeForm(t?: any) {
    if (t) { this.typeEditId = t.id; this.typeForm = { ...t }; }
    else   { this.typeEditId = null; this.typeForm = { name:'', code:'', max_amount:0, max_installments:12, interest_rate:0, requires_guarantor:false, is_active:true, description:'' }; }
    this.typeError = ''; this.showTypeForm = true;
  }

  saveType() {
    if (!this.typeForm.name || !this.typeForm.code) { this.typeError = 'Name and code required.'; return; }
    this.typeSaving = true; this.typeError = '';
    const req = this.typeEditId
      ? this.http.put(`/api/v1/loans/types/${this.typeEditId}`, this.typeForm)
      : this.http.post('/api/v1/loans/types', this.typeForm);
    req.subscribe({
      next: () => { this.typeSaving = false; this.showTypeForm = false; this.loadLoanTypes(); },
      error: err => { this.typeSaving = false; this.typeError = err?.error?.message || 'Save failed.'; }
    });
  }

  // ── Helpers ───────────────────────────────────────────────────────────
  get pages(): number[] {
    if (!this.pagination?.last_page) return [];
    return Array.from({ length: Math.min(this.pagination.last_page, 8) }, (_, i) => i + 1);
  }

  formatSAR(v: any): string {
    const n = parseFloat(v) || 0;
    if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M';
    if (n >= 1000)    return (n / 1000).toFixed(1) + 'K';
    return n.toLocaleString();
  }

  progressPct(loan: any): number {
    if (!loan.installments || !loan.installments_paid) return 0;
    return Math.round((loan.installments_paid / loan.installments) * 100);
  }

  statusLabel(s: string): string {
    const map: any = {
      pending_manager:'Pending Manager', pending_hr:'Pending HR', pending_finance:'Pending Finance',
      approved:'Approved', disbursed:'Active', completed:'Completed', rejected:'Rejected', cancelled:'Cancelled'
    };
    return map[s] || s;
  }

  statusCls(s: string): string {
    const map: any = {
      pending_manager:'badge-yellow', pending_hr:'badge-purple', pending_finance:'badge-blue',
      approved:'badge-green', disbursed:'badge-teal', completed:'badge-gray',
      rejected:'badge-red',  cancelled:'badge-gray'
    };
    return map[s] || 'badge-gray';
  }

  statusIcon(s: string): string {
    const map: any = {
      pending_manager:'manage_accounts', pending_hr:'badge', pending_finance:'account_balance',
      approved:'check_circle', disbursed:'payments', completed:'task_alt',
      rejected:'cancel', cancelled:'block'
    };
    return map[s] || 'help';
  }

  canApprove(loan: any): boolean {
    return ['pending_manager','pending_hr','pending_finance'].includes(loan.status);
  }

  canReject(loan: any): boolean {
    return ['pending_manager','pending_hr','pending_finance'].includes(loan.status);
  }

  canCancel(loan: any): boolean {
    return ['pending_manager','pending_hr','pending_finance'].includes(loan.status);
  }

  avatarColor(name: string): string {
    const colors = ['#3b82f6','#6366f1','#8b5cf6','#ec4899','#10b981','#f59e0b','#ef4444','#0ea5e9'];
    return colors[(name?.charCodeAt(0) || 0) % colors.length];
  }

  typeColor(name: string): string {
    const map: any = { 'Personal Loan':'#3b82f6','Housing Loan':'#10b981','Emergency Loan':'#ef4444',
      'Education Loan':'#8b5cf6','Vehicle Loan':'#f59e0b' };
    return map[name] || '#6366f1';
  }

  instStatusCls(s: string): string {
    return ({ pending:'badge-yellow', paid:'badge-green', skipped:'badge-gray', overdue:'badge-red' } as any)[s] || 'badge-gray';
  }

  get selectedType(): any {
    return this.loanTypes.find(t => t.id == this.form.loan_type_id) || null;
  }

  monthlyPreview(): number {
    if (!this.form.requested_amount || !this.form.installments) return 0;
    const amt  = parseFloat(this.form.requested_amount) || 0;
    const inst = parseInt(this.form.installments) || 1;
    const rate = (this.selectedType?.interest_rate || 0) / 100 / 12;
    if (rate <= 0) return Math.round((amt / inst) * 100) / 100;
    const payment = amt * (rate * Math.pow(1 + rate, inst)) / (Math.pow(1 + rate, inst) - 1);
    return Math.round(payment * 100) / 100;
  }

  approvalSteps(loan: any): any[] {
    return [
      { label:'Employee',label2:'Request Submitted', done: true, active: false,
        by: loan.employee?.first_name + ' ' + loan.employee?.last_name, date: loan.created_at },
      { label:'Manager', label2:'Manager Approval',
        done: !!loan.manager_approved_at, active: loan.status === 'pending_manager',
        by: loan.managerApprover?.name, date: loan.manager_approved_at },
      { label:'HR',      label2:'HR Approval',
        done: !!loan.hr_approved_at,      active: loan.status === 'pending_hr',
        by: loan.hrApprover?.name, date: loan.hr_approved_at },
      { label:'Finance', label2:'Finance & Disburse',
        done: !!loan.finance_approved_at, active: loan.status === 'pending_finance',
        by: loan.financeApprover?.name, date: loan.finance_approved_at },
    ];
  }
}
