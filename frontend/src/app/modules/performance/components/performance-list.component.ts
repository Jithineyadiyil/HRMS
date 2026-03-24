import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { AuthService } from '../../../core/services/auth.service';

@Component({
  standalone: false, selector: 'app-performance-list',
  templateUrl: './performance-list.component.html',
  styleUrls: ['./performance-list.component.scss'],
})
export class PerformanceListComponent implements OnInit {

  activeTab: 'overview' | 'reviews' | 'cycles' | 'kpis' = 'overview';

  // ── Stats ─────────────────────────────────────────────────────────────
  stats: any      = {};
  statsLoading    = true;

  // ── Cycles ───────────────────────────────────────────────────────────
  cycles: any[]   = [];
  cyclesLoading   = false;
  cycleColumns    = ['name','type','period','reviews','status','actions'];

  // ── Reviews ───────────────────────────────────────────────────────────
  reviews: any[]     = [];
  reviewPagination: any = null;
  reviewsLoading     = false;
  reviewPage         = 1;
  reviewStatusFilter = '';
  reviewColumns      = ['employee','cycle','self','manager','final','band','status','actions'];

  // ── KPIs ─────────────────────────────────────────────────────────────
  kpis: any[]     = [];
  kpisLoading     = false;
  kpiYear         = new Date().getFullYear();
  kpiColumns      = ['title','category','weight','target','status','actions'];

  // ── Cycle form ────────────────────────────────────────────────────────
  showCycleForm   = false;
  cycleFormError  = '';
  submitting      = false;
  cycleEditId: number | null = null;
  cycleForm: any  = this.blankCycle();

  // ── KPI form ─────────────────────────────────────────────────────────
  showKpiForm     = false;
  kpiFormError    = '';
  kpiEditId: number | null = null;
  kpiForm: any    = this.blankKpi();

  // ── Review detail drawer ──────────────────────────────────────────────
  showReviewDetail = false;
  selectedReview: any = null;
  reviewDetailLoading = false;

  // ── Lookups ───────────────────────────────────────────────────────────
  departments: any[] = [];
  employees: any[]   = [];

  readonly CYCLE_TYPES = [
    { value:'annual',      label:'Annual'      },
    { value:'semi_annual', label:'Semi-Annual' },
    { value:'quarterly',   label:'Quarterly'   },
  ];

  readonly CYCLE_STATUSES = [
    { value:'draft',     label:'Draft',     color:'#8b949e' },
    { value:'active',    label:'Active',    color:'#10b981' },
    { value:'completed', label:'Completed', color:'#3b82f6' },
    { value:'archived',  label:'Archived',  color:'#484f58' },
  ];

  readonly REVIEW_STATUSES = [
    { value:'pending',          label:'Pending',          color:'#8b949e' },
    { value:'self_submitted',   label:'Self Submitted',   color:'#6366f1' },
    { value:'manager_reviewed', label:'Manager Reviewed', color:'#f59e0b' },
    { value:'hr_calibrated',    label:'HR Calibrated',    color:'#0ea5e9' },
    { value:'finalized',        label:'Finalized',        color:'#10b981' },
  ];

  readonly PERF_BANDS = [
    { value:'excellent',     label:'Excellent',     color:'#10b981', min:4.5 },
    { value:'good',          label:'Good',          color:'#3b82f6', min:3.5 },
    { value:'average',       label:'Average',       color:'#f59e0b', min:2.5 },
    { value:'below_average', label:'Below Average', color:'#f97316', min:1.5 },
    { value:'poor',          label:'Poor',          color:'#ef4444', min:0   },
  ];

  readonly KPI_CATEGORIES = [
    { value:'quantitative', label:'Quantitative' },
    { value:'qualitative',  label:'Qualitative'  },
    { value:'behavioral',   label:'Behavioral'   },
    { value:'learning',     label:'Learning'     },
  ];

  constructor(private http: HttpClient, public auth: AuthService) {}

  ngOnInit() {
    this.loadStats();
    this.loadDepartments();
    this.loadEmployees();
  }

  // ── Stats ─────────────────────────────────────────────────────────────

  loadStats() {
    this.statsLoading = true;
    this.http.get<any>('/api/v1/performance/stats').subscribe({
      next: r => { this.stats = r; this.statsLoading = false; },
      error: () => this.statsLoading = false,
    });
  }

  // ── Tab switching ─────────────────────────────────────────────────────

  switchTab(id: string) {
    this.activeTab = id as any;
    if (id === 'cycles')  this.loadCycles();
    if (id === 'reviews') this.loadReviews();
    if (id === 'kpis')    this.loadKpis();
    if (id === 'overview') this.loadStats();
  }

  // ── Cycles ────────────────────────────────────────────────────────────

  loadCycles() {
    this.cyclesLoading = true;
    this.http.get<any>('/api/v1/performance/cycles').subscribe({
      next: r => { this.cycles = r?.data || []; this.cyclesLoading = false; },
      error: () => this.cyclesLoading = false,
    });
  }

  openCycleForm(cycle?: any) {
    this.cycleEditId = cycle?.id ?? null; this.cycleFormError = '';
    this.cycleForm = cycle ? {
      name:                     cycle.name,
      type:                     cycle.type,
      start_date:               cycle.start_date?.slice(0, 10) ?? '',
      end_date:                 cycle.end_date?.slice(0, 10)   ?? '',
      self_assessment_deadline: cycle.self_assessment_deadline?.slice(0, 10) ?? '',
      manager_review_deadline:  cycle.manager_review_deadline?.slice(0, 10) ?? '',
      status:                   cycle.status,
    } : this.blankCycle();
    this.showCycleForm = true;
  }

  saveCycle() {
    if (!this.cycleForm.name || !this.cycleForm.type || !this.cycleForm.start_date || !this.cycleForm.end_date) {
      this.cycleFormError = 'Name, type, start and end dates are required.'; return;
    }
    this.submitting = true; this.cycleFormError = '';
    const body = { ...this.cycleForm,
      self_assessment_deadline: this.cycleForm.self_assessment_deadline || null,
      manager_review_deadline:  this.cycleForm.manager_review_deadline  || null,
    };
    const req = this.cycleEditId
      ? this.http.put<any>(`/api/v1/performance/cycles/${this.cycleEditId}`, body)
      : this.http.post<any>('/api/v1/performance/cycles', body);
    req.subscribe({
      next: () => { this.submitting = false; this.showCycleForm = false; this.loadCycles(); this.loadStats(); },
      error: err => {
        this.submitting = false;
        const errs = err?.error?.errors;
        this.cycleFormError = errs ? Object.values(errs).flat().join(' ') : err?.error?.message || 'Save failed.';
      },
    });
  }

  // ── Reviews ───────────────────────────────────────────────────────────

  loadReviews(page = 1) {
    this.reviewsLoading = true; this.reviewPage = page;
    const params: any = { page, per_page: 20 };
    if (this.reviewStatusFilter) params.status = this.reviewStatusFilter;
    this.http.get<any>('/api/v1/performance/reviews', { params }).subscribe({
      next: r => { this.reviews = r?.data || []; this.reviewPagination = r; this.reviewsLoading = false; },
      error: () => this.reviewsLoading = false,
    });
  }

  viewReview(review: any) {
    this.selectedReview     = review;
    this.reviewDetailLoading = true;
    this.showReviewDetail   = true;
    this.http.get<any>(`/api/v1/performance/reviews/${review.id}`).subscribe({
      next: r => { this.selectedReview = r.review; this.reviewDetailLoading = false; },
      error: () => this.reviewDetailLoading = false,
    });
  }

  // ── KPIs ─────────────────────────────────────────────────────────────

  loadKpis() {
    this.kpisLoading = true;
    this.http.get<any>('/api/v1/performance/kpis', { params: { year: this.kpiYear } }).subscribe({
      next: r => { this.kpis = r || []; this.kpisLoading = false; },
      error: () => this.kpisLoading = false,
    });
  }

  openKpiForm(kpi?: any) {
    this.kpiEditId = kpi?.id ?? null; this.kpiFormError = '';
    this.kpiForm = kpi ? {
      title:         kpi.title,
      description:   kpi.description ?? '',
      category:      kpi.category,
      target_value:  kpi.target_value ?? '',
      unit:          kpi.unit ?? '',
      weight:        kpi.weight ?? 10,
      year:          kpi.year,
      department_id: kpi.department_id ?? '',
      employee_id:   kpi.employee_id ?? '',
      is_active:     kpi.is_active !== false,
    } : this.blankKpi();
    this.showKpiForm = true;
  }

  saveKpi() {
    if (!this.kpiForm.title || !this.kpiForm.category) {
      this.kpiFormError = 'Title and category are required.'; return;
    }
    this.submitting = true; this.kpiFormError = '';
    const body = { ...this.kpiForm,
      department_id: this.kpiForm.department_id || null,
      employee_id:   this.kpiForm.employee_id   || null,
      target_value:  this.kpiForm.target_value  || null,
    };
    const req = this.kpiEditId
      ? this.http.put<any>(`/api/v1/performance/kpis/${this.kpiEditId}`, body)
      : this.http.post<any>('/api/v1/performance/kpis', body);
    req.subscribe({
      next: () => { this.submitting = false; this.showKpiForm = false; this.loadKpis(); },
      error: err => {
        this.submitting = false;
        const errs = err?.error?.errors;
        this.kpiFormError = errs ? Object.values(errs).flat().join(' ') : err?.error?.message || 'Save failed.';
      },
    });
  }

  // ── Helpers ───────────────────────────────────────────────────────────

  private loadDepartments() {
    this.http.get<any>('/api/v1/departments').subscribe({
      next: r => this.departments = Array.isArray(r) ? r : (r?.data ?? []),
    });
  }

  private loadEmployees() {
    this.http.get<any>('/api/v1/employees?per_page=500').subscribe({
      next: r => this.employees = r?.data ?? [],
    });
  }

  private blankCycle() {
    return { name:'', type:'annual', start_date:'', end_date:'',
             self_assessment_deadline:'', manager_review_deadline:'', status:'draft' };
  }

  private blankKpi() {
    return { title:'', description:'', category:'quantitative', target_value:'',
             unit:'', weight:10, year: new Date().getFullYear(),
             department_id:'', employee_id:'', is_active:true };
  }

  // CSS helpers
  cycleCls(s: string)  { return ({ draft:'badge-gray', active:'badge-green', completed:'badge-blue', archived:'badge-gray' } as any)[s] ?? 'badge-gray'; }
  reviewCls(s: string) { return ({ pending:'badge-gray', self_submitted:'badge-purple', manager_reviewed:'badge-yellow', hr_calibrated:'badge-blue', finalized:'badge-green' } as any)[s] ?? 'badge-gray'; }
  cycleLabel(s: string) { return this.CYCLE_TYPES.find(t => t.value === s)?.label ?? s; }
  reviewLabel(s: string) { return this.REVIEW_STATUSES.find(x => x.value === s)?.label ?? s; }
  bandMeta(b: string)  { return this.PERF_BANDS.find(x => x.value === b) ?? { label:b, color:'#8b949e' }; }
  ratingColor(r: number) {
    if (!r) return '#8b949e';
    if (r >= 4.5) return '#10b981';
    if (r >= 3.5) return '#3b82f6';
    if (r >= 2.5) return '#f59e0b';
    if (r >= 1.5) return '#f97316';
    return '#ef4444';
  }
  stageCount(stage: string) { return this.stats?.by_status?.[stage] ?? 0; }

  get reviewPages(): number[] {
    if (!this.reviewPagination?.last_page) return [];
    return Array.from({ length: Math.min(this.reviewPagination.last_page, 8) }, (_,i) => i+1);
  }
  canManage(): boolean { return this.auth.canAny(['performance.manage']); }
  get currentYear(): number { return new Date().getFullYear(); }
}
