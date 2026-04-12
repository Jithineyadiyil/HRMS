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
  standalone: false, selector: 'app-recruitment-list',
  templateUrl: './recruitment-list.component.html',
  styleUrls: ['./recruitment-list.component.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class RecruitmentListComponent implements OnInit, OnDestroy {

  jobs: any[] = []; applications: any[] = []; departments: any[] = [];
  stats: any = {}; pagination: any = null; currentPage = 1;
  activeTab = 'jobs'; loading = false; appsLoading = false;
  statsLoading = true; submitting = false;
  successMsg = ''; errorMsg = '';
  showJobForm = false; showAppDetail = false; showHireForm = false;
  editJobId: number | null = null; selectedApp: any = null;
  selectedJobFilter: number | null = null; isHR = false;

  searchControl = new FormControl('');
  statusFilter = ''; stageFilter = '';

  jobForm!: FormGroup; hireForm!: FormGroup;

  readonly jobStatuses = [
    { value: 'draft', label: 'Draft', color: '#8b949e' },
    { value: 'open', label: 'Open', color: '#10b981' },
    { value: 'on_hold', label: 'On Hold', color: '#f59e0b' },
    { value: 'closed', label: 'Closed', color: '#6b7280' },
  ];
  readonly stages = [
    { value: 'applied',   label: 'Applied',   color: '#3b82f6', icon: 'send'         },
    { value: 'screening', label: 'Screening', color: '#6366f1', icon: 'manage_search' },
    { value: 'interview', label: 'Interview', color: '#f59e0b', icon: 'video_call'    },
    { value: 'offer',     label: 'Offer',     color: '#0ea5e9', icon: 'local_offer'   },
    { value: 'hired',     label: 'Hired',     color: '#10b981', icon: 'how_to_reg'    },
    { value: 'rejected',  label: 'Rejected',  color: '#ef4444', icon: 'cancel'        },
  ];
  readonly employmentTypes = [
    { value: 'full_time', label: 'Full Time' },
    { value: 'part_time', label: 'Part Time' },
    { value: 'contract',  label: 'Contract'  },
    { value: 'intern',    label: 'Intern'    },
  ];
  readonly statTiles = [
    { key: 'open_jobs',        label: 'Open Jobs',        color: '#10b981', icon: 'work'       },
    { key: 'total_applicants', label: 'Total Applicants', color: '#3b82f6', icon: 'people'      },
    { key: 'new_this_week',    label: 'New This Week',    color: '#6366f1', icon: 'person_add'  },
    { key: 'in_interview',     label: 'In Interview',     color: '#f59e0b', icon: 'video_call'  },
    { key: 'offers_sent',      label: 'Offers Sent',      color: '#0ea5e9', icon: 'local_offer' },
    { key: 'hired',            label: 'Hired',            color: '#10b981', icon: 'how_to_reg'  },
  ];
  readonly displayedColumns = ['title', 'department', 'type', 'applicants', 'status', 'actions'];

  private readonly api = '/api/v1/recruitment';
  private readonly destroy$ = new Subject<void>();

  constructor(
    private readonly http: HttpClient,
    private readonly fb: FormBuilder,
    private readonly auth: AuthService,
    private readonly cdr: ChangeDetectorRef,
  ) {}

  ngOnInit(): void {
    this.isHR = this.auth.isHRRole();
    this.jobForm = this.fb.group({
      title:           ['', Validators.required],
      employment_type: ['full_time', Validators.required],
      status:          ['open'],
      vacancies:       [1],
      department_id:   [''],
      designation_id:  [''],
      location:        [''],
      salary_min:      [null],
      salary_max:      [null],
      closing_date:    [''],
      description:     ['', Validators.required],
      requirements:    [''],
      benefits:        [''],
    });
    this.hireForm = this.fb.group({
      hire_date: [new Date().toISOString().slice(0, 10), Validators.required],
      salary:    [null, Validators.required],
    });
    this.loadStats(); this.loadJobs(); this.loadDepartments();
    this.searchControl.valueChanges.pipe(debounceTime(400), distinctUntilChanged(), takeUntil(this.destroy$))
      .subscribe(() => this.loadJobs(1));
  }

  loadJobs(page = 1): void {
    this.loading = true; this.currentPage = page;
    const params: any = { page, per_page: 15 };
    if (this.searchControl.value) params.search = this.searchControl.value;
    if (this.statusFilter)        params.status = this.statusFilter;
    this.http.get<any>(`${this.api}/jobs`, { params }).pipe(takeUntil(this.destroy$)).subscribe({
      next: r => { this.jobs = r.data ?? []; this.pagination = r.meta ?? null; this.loading = false; this.cdr.markForCheck(); },
      error: () => { this.loading = false; this.cdr.markForCheck(); },
    });
  }

  loadStats(): void {
    this.http.get<any>(`${this.api}/stats`).pipe(takeUntil(this.destroy$)).subscribe({
      next: s => { this.stats = s; this.statsLoading = false; this.cdr.markForCheck(); },
      error: () => { this.statsLoading = false; this.cdr.markForCheck(); },
    });
  }

  loadDepartments(): void {
    this.http.get<any>('/api/v1/departments').pipe(takeUntil(this.destroy$)).subscribe({
      next: r => { this.departments = r?.data ?? r ?? []; this.cdr.markForCheck(); },
      error: () => {},
    });
  }

  loadApplications(jobId?: number): void {
    this.appsLoading = true;
    const params: any = { per_page: 100 };
    if (jobId)            params.job_posting_id = jobId;
    if (this.stageFilter) params.stage = this.stageFilter;
    this.http.get<any>(`${this.api}/applications`, { params }).pipe(takeUntil(this.destroy$)).subscribe({
      next: r => { this.applications = r.data ?? []; this.appsLoading = false; this.cdr.markForCheck(); },
      error: () => { this.appsLoading = false; this.cdr.markForCheck(); },
    });
  }

  viewApplications(job: any): void {
    this.selectedJobFilter = job.id; this.activeTab = 'pipeline';
    this.loadApplications(job.id);
  }

  viewApp(app: any): void { this.selectedApp = app; this.showAppDetail = true; this.cdr.markForCheck(); }

  moveStage(app: any, stage: string, event: Event): void {
    event.stopPropagation();
    this.http.put(`${this.api}/applications/${app.id}/stage`, { stage }).pipe(takeUntil(this.destroy$)).subscribe({
      next: (r: any) => {
        app.stage = stage;
        if (this.selectedApp?.id === app.id) this.selectedApp.stage = stage;
        this.successMsg = `Moved to ${stage}`;
        this.loadStats();
        setTimeout(() => { this.successMsg = ''; this.cdr.markForCheck(); }, 3000);
        this.cdr.markForCheck();
      },
      error: () => {},
    });
  }

  sendOffer(app: any): void {
    const salary = prompt('Enter offered salary (SAR):');
    if (!salary) return;
    this.http.post(`${this.api}/offer/${app.id}`, { offered_salary: salary }).pipe(takeUntil(this.destroy$)).subscribe({
      next: () => { app.stage = 'offer'; this.successMsg = 'Offer sent.'; this.loadStats(); setTimeout(() => { this.successMsg = ''; this.cdr.markForCheck(); }, 3000); this.cdr.markForCheck(); },
      error: (err: any) => { this.errorMsg = err?.error?.message ?? 'Failed.'; this.cdr.markForCheck(); },
    });
  }

  openHireForm(app: any): void {
    this.selectedApp = app;
    this.hireForm.reset({ hire_date: new Date().toISOString().slice(0, 10) });
    this.showHireForm = true; this.cdr.markForCheck();
  }

  confirmHire(): void {
    if (this.hireForm.invalid) return;
    this.submitting = true;
    this.http.post(`${this.api}/hire/${this.selectedApp.id}`, this.hireForm.value).pipe(takeUntil(this.destroy$)).subscribe({
      next: () => {
        this.submitting = false; this.showHireForm = false; this.showAppDetail = false;
        this.selectedApp.stage = 'hired';
        this.successMsg = 'Employee record created!';
        this.loadStats(); this.loadJobs(this.currentPage);
        setTimeout(() => { this.successMsg = ''; this.cdr.markForCheck(); }, 4000);
        this.cdr.markForCheck();
      },
      error: (err: any) => { this.submitting = false; this.errorMsg = err?.error?.message ?? 'Hire failed.'; this.cdr.markForCheck(); },
    });
  }

  openJobForm(job?: any): void {
    this.editJobId = job?.id ?? null;
    if (job) {
      this.jobForm.patchValue({ ...job, department_id: job.department_id ?? '', designation_id: job.designation_id ?? '' });
    } else {
      this.jobForm.reset({ employment_type: 'full_time', status: 'open', vacancies: 1 });
    }
    this.showJobForm = true; this.cdr.markForCheck();
  }

  saveJob(): void {
    if (this.jobForm.invalid) { this.jobForm.markAllAsTouched(); return; }
    this.submitting = true;
    const req = this.editJobId
      ? this.http.put(`${this.api}/jobs/${this.editJobId}`, this.jobForm.value)
      : this.http.post(`${this.api}/jobs`, this.jobForm.value);
    req.pipe(takeUntil(this.destroy$)).subscribe({
      next: () => {
        this.submitting = false; this.showJobForm = false;
        this.successMsg = this.editJobId ? 'Job updated.' : 'Job posted.';
        this.loadJobs(this.currentPage); this.loadStats();
        setTimeout(() => { this.successMsg = ''; this.cdr.markForCheck(); }, 3000);
        this.cdr.markForCheck();
      },
      error: (err: any) => { this.submitting = false; this.errorMsg = err?.error?.message ?? 'Save failed.'; this.cdr.markForCheck(); },
    });
  }

  deleteJob(id: number, event: Event): void {
    event.stopPropagation();
    if (!confirm('Delete this job posting?')) return;
    this.http.delete(`${this.api}/jobs/${id}`).pipe(takeUntil(this.destroy$)).subscribe({
      next: () => { this.loadJobs(this.currentPage); this.loadStats(); this.cdr.markForCheck(); },
      error: () => {},
    });
  }

  changeJobStatus(job: any, status: string, event: Event): void {
    event.stopPropagation();
    this.http.put(`${this.api}/jobs/${job.id}`, { status }).pipe(takeUntil(this.destroy$)).subscribe({
      next: () => { job.status = status; this.loadStats(); this.cdr.markForCheck(); },
      error: () => {},
    });
  }

  switchTab(id: string): void {
    this.activeTab = id;
    if (id === 'pipeline') this.loadApplications(this.selectedJobFilter ?? undefined);
    this.cdr.markForCheck();
  }

  appsByStage(stage: string): any[] { return this.applications.filter(a => a.stage === stage); }
  stageData(s: string): any { return this.stages.find(x => x.value === s) ?? { label: s, color: '#8b949e', icon: 'help' }; }
  avatarColor(name?: string|null): string { const p=['#3b82f6','#10b981','#f59e0b','#ef4444','#6366f1','#0ea5e9','#f97316','#a78bfa']; return p[(name?.charCodeAt(0)??0)%p.length]; }
  initial(name?: string|null): string { return name?.charAt(0)?.toUpperCase() ?? '?'; }
  formatDate(d: string|null): string { if(!d) return '—'; return new Date(d).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}); }
  get pages(): number[] { if(!this.pagination?.last_page) return []; return Array.from({length:Math.min(this.pagination.last_page,8)},(_,i)=>i+1); }
  get f() { return this.jobForm.controls; }
  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }
}
