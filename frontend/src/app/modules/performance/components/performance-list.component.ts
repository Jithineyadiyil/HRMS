import {
  Component, OnInit, OnDestroy,
  ChangeDetectionStrategy, ChangeDetectorRef,
} from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { FormControl } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil, debounceTime, distinctUntilChanged } from 'rxjs/operators';
import { AuthService } from '../../../core/services/auth.service';

@Component({
  standalone: false, selector: 'app-performance-list',
  templateUrl: './performance-list.component.html',
  styleUrls:   ['./performance-list.component.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PerformanceListComponent implements OnInit, OnDestroy {

  // ── Data ──────────────────────────────────────────────────────────────
  cycles:    any[] = [];
  reviews:   any[] = [];
  kpis:      any[] = [];
  stats:     any   = {};
  pagination: any  = null;
  currentPage = 1;

  // ── View ──────────────────────────────────────────────────────────────
  activeTab         = 'cycles';
  loading           = false;
  statsLoading      = true;
  submitting        = false;
  successMsg        = '';
  errorMsg          = '';

  showCycleForm     = false;
  showDetail        = false;
  showSelfForm      = false;
  showManagerForm   = false;
  showFinalizeForm  = false;
  showKpiForm       = false;

  editCycleId: number | null = null;
  selectedCycle:  any        = null;
  selectedReview: any        = null;
  editKpiId: number | null   = null;

  isHR  = false;
  isMgr = false;

  // ── Filters ───────────────────────────────────────────────────────────
  reviewStatus  = '';
  searchControl = new FormControl('');

  // ── Forms ─────────────────────────────────────────────────────────────
  cycleForm!: FormGroup;
  selfForm!:  FormGroup;
  mgrForm!:   FormGroup;
  finalForm!: FormGroup;
  kpiForm!:   FormGroup;

  // ── Options ───────────────────────────────────────────────────────────
  readonly cycleTypes = [
    { value: 'annual',     label: 'Annual'      },
    { value: 'mid_year',   label: 'Mid-Year'    },
    { value: 'quarterly',  label: 'Quarterly'   },
    { value: 'probation',  label: 'Probation'   },
  ];
  readonly bands = [
    { value: 'excellent',      label: 'Excellent',      color: '#10b981' },
    { value: 'good',           label: 'Good',           color: '#3b82f6' },
    { value: 'average',        label: 'Average',        color: '#f59e0b' },
    { value: 'below_average',  label: 'Below Average',  color: '#f97316' },
    { value: 'poor',           label: 'Poor',           color: '#ef4444' },
  ];
  readonly statuses = ['pending','self_submitted','manager_reviewed','finalized'];
  readonly reviewCols = ['employee','cycle','status','self_rating','manager_rating','final_rating','band','actions'];
  readonly cycleCols  = ['name','type','period','reviews','status','actions'];
  readonly kpiCols    = ['title','category','target','weight','actions'];

  private readonly api      = '/api/v1/performance';
  private readonly destroy$ = new Subject<void>();

  constructor(
    private readonly http: HttpClient,
    private readonly fb:   FormBuilder,
    private readonly auth: AuthService,
    private readonly cdr:  ChangeDetectorRef,
  ) {}

  ngOnInit(): void {
    this.isHR  = this.auth.isHRRole();
    this.isMgr = this.auth.isManagerRole();

    this.cycleForm = this.fb.group({
      name:                     ['', Validators.required],
      type:                     ['annual', Validators.required],
      start_date:               ['', Validators.required],
      end_date:                 ['', Validators.required],
      self_assessment_deadline: [''],
      manager_review_deadline:  [''],
      status:                   ['draft'],
    });

    this.selfForm = this.fb.group({
      rating:   [null, [Validators.required, Validators.min(1), Validators.max(5)]],
      comments: ['', [Validators.required, Validators.minLength(10)]],
    });

    this.mgrForm = this.fb.group({
      rating:   [null, [Validators.required, Validators.min(1), Validators.max(5)]],
      comments: ['', [Validators.required, Validators.minLength(10)]],
    });

    this.finalForm = this.fb.group({
      final_rating:     [null, [Validators.required, Validators.min(1), Validators.max(5)]],
      performance_band: ['', Validators.required],
      development_plan: [''],
      hr_notes:         [''],
    });

    this.kpiForm = this.fb.group({
      title:         ['', Validators.required],
      category:      ['', Validators.required],
      year:          [new Date().getFullYear(), Validators.required],
      target_value:  [''],
      unit:          [''],
      weight:        [null],
      description:   [''],
    });

    this.loadStats();
    this.loadCycles();
    this.loadKpis();

    this.searchControl.valueChanges.pipe(
      debounceTime(400), distinctUntilChanged(), takeUntil(this.destroy$),
    ).subscribe(() => this.loadReviews(1));
  }

  // ── Data loading ──────────────────────────────────────────────────────

  loadStats(): void {
    this.http.get<any>(`${this.api}/stats`).pipe(takeUntil(this.destroy$)).subscribe({
      next: s => { this.stats = s; this.statsLoading = false; this.cdr.markForCheck(); },
      error: () => { this.statsLoading = false; this.cdr.markForCheck(); },
    });
  }

  loadCycles(page = 1): void {
    this.loading = true; this.currentPage = page;
    this.http.get<any>(this.api, { params: { page, per_page: 12 } }).pipe(takeUntil(this.destroy$)).subscribe({
      next: r => { this.cycles = r.data ?? []; this.pagination = r.meta ?? null; this.loading = false; this.cdr.markForCheck(); },
      error: () => { this.loading = false; this.cdr.markForCheck(); },
    });
  }

  loadReviews(page = 1): void {
    this.loading = true; this.currentPage = page;
    const params: any = { view: 'reviews', page, per_page: 20 };
    if (this.reviewStatus)         params.status = this.reviewStatus;
    if (this.searchControl.value)  params.search = this.searchControl.value;

    this.http.get<any>(this.api, { params }).pipe(takeUntil(this.destroy$)).subscribe({
      next: r => { this.reviews = r.data ?? []; this.pagination = r.meta ?? null; this.loading = false; this.cdr.markForCheck(); },
      error: () => { this.loading = false; this.cdr.markForCheck(); },
    });
  }

  loadKpis(): void {
    this.http.get<any>(`${this.api}/kpis`, { params: { year: new Date().getFullYear() } })
      .pipe(takeUntil(this.destroy$)).subscribe({
        next: r => { this.kpis = r.kpis ?? []; this.cdr.markForCheck(); },
        error: () => {},
      });
  }

  // ── Cycle CRUD ────────────────────────────────────────────────────────

  openCycleForm(cycle?: any): void {
    this.editCycleId = cycle?.id ?? null;
    if (cycle) {
      this.cycleForm.patchValue({
        name: cycle.name, type: cycle.type, status: cycle.status,
        start_date: cycle.start_date, end_date: cycle.end_date,
        self_assessment_deadline: cycle.self_assessment_deadline ?? '',
        manager_review_deadline:  cycle.manager_review_deadline ?? '',
      });
    } else {
      this.cycleForm.reset({ type: 'annual', status: 'draft' });
    }
    this.showCycleForm = true; this.cdr.markForCheck();
  }

  saveCycle(): void {
    if (this.cycleForm.invalid) { this.cycleForm.markAllAsTouched(); return; }
    this.submitting = true;
    const req = this.editCycleId
      ? this.http.put(`${this.api}/${this.editCycleId}`, this.cycleForm.value)
      : this.http.post(this.api, this.cycleForm.value);
    req.pipe(takeUntil(this.destroy$)).subscribe({
      next: () => {
        this.submitting = false; this.showCycleForm = false;
        this.successMsg = this.editCycleId ? 'Cycle updated.' : 'Cycle created.';
        this.loadCycles(); this.loadStats();
        this._clearSuccess();
        this.cdr.markForCheck();
      },
      error: (e: any) => { this.submitting = false; this.errorMsg = e?.error?.message ?? 'Save failed.'; this.cdr.markForCheck(); },
    });
  }

  initiateCycle(cycle: any): void {
    if (!confirm(`Initiate "${cycle.name}"? This will create review records for all active employees.`)) return;
    this.http.post<any>(`${this.api}/${cycle.id}/initiate`, {}).pipe(takeUntil(this.destroy$)).subscribe({
      next: r => { this.successMsg = r.message; this.loadCycles(); this.loadStats(); this._clearSuccess(); this.cdr.markForCheck(); },
      error: (e: any) => { this.errorMsg = e?.error?.message ?? 'Initiation failed.'; this.cdr.markForCheck(); },
    });
  }

  closeCycle(cycle: any): void {
    this.http.put(`${this.api}/${cycle.id}`, { status: 'closed' }).pipe(takeUntil(this.destroy$)).subscribe({
      next: () => { cycle.status = 'closed'; this.cdr.markForCheck(); },
      error: () => {},
    });
  }

  viewCycle(cycle: any): void {
    this.selectedCycle = cycle;
    this.http.get<any>(`${this.api}/${cycle.id}`).pipe(takeUntil(this.destroy$)).subscribe({
      next: r => {
        this.selectedCycle = r.cycle;
        this.showDetail    = true;
        this.cdr.markForCheck();
      },
      error: () => {},
    });
  }

  // ── Review actions ────────────────────────────────────────────────────

  openSelfAssess(review: any): void {
    this.selectedReview = review;
    this.selfForm.reset({ rating: review.self_rating ?? null, comments: review.self_comments ?? '' });
    this.showSelfForm = true; this.cdr.markForCheck();
  }

  submitSelf(): void {
    if (this.selfForm.invalid) { this.selfForm.markAllAsTouched(); return; }
    this.submitting = true;
    this.http.post<any>(`${this.api}/review/${this.selectedReview.id}/self`, this.selfForm.value)
      .pipe(takeUntil(this.destroy$)).subscribe({
        next: () => { this.submitting = false; this.showSelfForm = false; this.loadReviews(this.currentPage); this.loadStats(); this.successMsg = 'Self assessment submitted.'; this._clearSuccess(); this.cdr.markForCheck(); },
        error: (e: any) => { this.submitting = false; this.errorMsg = e?.error?.message ?? 'Failed.'; this.cdr.markForCheck(); },
      });
  }

  openManagerReview(review: any): void {
    this.selectedReview = review;
    this.mgrForm.reset({ rating: review.manager_rating ?? null, comments: review.manager_comments ?? '' });
    this.showManagerForm = true; this.cdr.markForCheck();
  }

  submitManager(): void {
    if (this.mgrForm.invalid) { this.mgrForm.markAllAsTouched(); return; }
    this.submitting = true;
    this.http.post<any>(`${this.api}/review/${this.selectedReview.id}/manager`, this.mgrForm.value)
      .pipe(takeUntil(this.destroy$)).subscribe({
        next: () => { this.submitting = false; this.showManagerForm = false; this.loadReviews(this.currentPage); this.loadStats(); this.successMsg = 'Manager review submitted.'; this._clearSuccess(); this.cdr.markForCheck(); },
        error: (e: any) => { this.submitting = false; this.errorMsg = e?.error?.message ?? 'Failed.'; this.cdr.markForCheck(); },
      });
  }

  openFinalize(review: any): void {
    this.selectedReview = review;
    this.finalForm.reset({
      final_rating: review.final_rating ?? null,
      performance_band: review.performance_band ?? '',
      development_plan: review.development_plan ?? '',
      hr_notes: review.hr_notes ?? '',
    });
    this.showFinalizeForm = true; this.cdr.markForCheck();
  }

  submitFinalize(): void {
    if (this.finalForm.invalid) { this.finalForm.markAllAsTouched(); return; }
    this.submitting = true;
    this.http.post<any>(`${this.api}/review/${this.selectedReview.id}/finalize`, this.finalForm.value)
      .pipe(takeUntil(this.destroy$)).subscribe({
        next: () => { this.submitting = false; this.showFinalizeForm = false; this.loadReviews(this.currentPage); this.loadStats(); this.successMsg = 'Review finalized.'; this._clearSuccess(); this.cdr.markForCheck(); },
        error: (e: any) => { this.submitting = false; this.errorMsg = e?.error?.message ?? 'Failed.'; this.cdr.markForCheck(); },
      });
  }

  // ── KPIs ──────────────────────────────────────────────────────────────

  openKpiForm(kpi?: any): void {
    this.editKpiId = kpi?.id ?? null;
    if (kpi) { this.kpiForm.patchValue(kpi); }
    else      { this.kpiForm.reset({ year: new Date().getFullYear() }); }
    this.showKpiForm = true; this.cdr.markForCheck();
  }

  saveKpi(): void {
    if (this.kpiForm.invalid) { this.kpiForm.markAllAsTouched(); return; }
    this.submitting = true;
    const req = this.editKpiId
      ? this.http.put(`${this.api}/kpis/${this.editKpiId}`, this.kpiForm.value)
      : this.http.post(`${this.api}/kpis`, this.kpiForm.value);
    req.pipe(takeUntil(this.destroy$)).subscribe({
      next: () => { this.submitting = false; this.showKpiForm = false; this.loadKpis(); this.cdr.markForCheck(); },
      error: () => { this.submitting = false; this.cdr.markForCheck(); },
    });
  }

  deleteKpi(id: number, event: Event): void {
    event.stopPropagation();
    if (!confirm('Delete this KPI?')) return;
    this.http.delete(`${this.api}/kpis/${id}`).pipe(takeUntil(this.destroy$)).subscribe({
      next: () => { this.loadKpis(); this.cdr.markForCheck(); },
      error: () => {},
    });
  }

  // ── Tab + helpers ─────────────────────────────────────────────────────

  switchTab(id: string): void {
    this.activeTab = id;
    if (id === 'reviews') this.loadReviews(1);
    if (id === 'kpis')    this.loadKpis();
    this.cdr.markForCheck();
  }

  canSelfAssess(r: any):    boolean { return r.status === 'pending'; }
  canMgrReview(r: any):     boolean { return ['pending','self_submitted'].includes(r.status) && (this.isMgr || this.isHR); }
  canFinalize(r: any):      boolean { return r.status === 'manager_reviewed' && this.isHR; }

  statusLabel(s: string): string {
    return ({ pending: 'Pending', self_submitted: 'Self Submitted', manager_reviewed: 'Manager Reviewed', finalized: 'Finalized' } as any)[s] ?? s;
  }
  statusCls(s: string): string {
    return ({ pending:'badge-gray', self_submitted:'badge-yellow', manager_reviewed:'badge-blue', finalized:'badge-green' } as any)[s] ?? 'badge-gray';
  }
  bandCls(b: string): string {
    return ({ excellent:'band-excellent', good:'band-good', average:'band-average', below_average:'band-below', poor:'band-poor' } as any)[b] ?? '';
  }
  cycleStatusCls(s: string): string {
    return ({ draft:'badge-gray', active:'badge-green', closed:'badge-gray' } as any)[s] ?? 'badge-gray';
  }
  ratingColor(r: number | null): string {
    if (!r) return 'var(--text3)';
    if (r >= 4.5) return '#10b981';
    if (r >= 3.5) return '#3b82f6';
    if (r >= 2.5) return '#f59e0b';
    if (r >= 1.5) return '#f97316';
    return '#ef4444';
  }
  stars(r: number | null): number[] { return [1,2,3,4,5]; }
  avatarColor(n?: string|null): string { const p=['#3b82f6','#10b981','#f59e0b','#ef4444','#6366f1']; return p[(n?.charCodeAt(0)??0)%p.length]; }
  initial(n?: string|null): string { return n?.charAt(0)?.toUpperCase() ?? '?'; }
  get pages(): number[] { if(!this.pagination?.last_page) return []; return Array.from({length:Math.min(this.pagination.last_page,8)},(_,i)=>i+1); }

  private _clearSuccess(): void { setTimeout(() => { this.successMsg = ''; this.errorMsg = ''; this.cdr.markForCheck(); }, 3500); }
  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }
}
