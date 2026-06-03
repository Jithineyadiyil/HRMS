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
  showJobForm = false; showAppDetail = false; showHireForm = false; showHireSuccess = false;
  hireResult: any = null;
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

  // ── CV Bank ──────────────────────────────────────────────────────────────
  cvBankEntries: any[]  = [];
  cvBankLoading  = false;
  cvBankSearch   = '';
  cvBankRating   = '';
  cvBankSource   = '';
  showCvForm     = false;
  cvFormSubmitting = false;
  selectedCv: any = null;
  showCvDetail   = false;
  linkJobId      = '';
  showLinkModal  = false;

  // ── Add Applicant ────────────────────────────────────────────────────────
  showAddApplicant   = false;
  addApplicantBusy   = false;
  applicantFile: File | null = null;
  applicantFileError = '';
  applicantForm: any = {
    job_posting_id: '', applicant_name: '', applicant_email: '',
    applicant_phone: '', expected_salary: null, available_from: '',
    cover_letter_text: '', stage: 'applied',
  };

  // ── Offer & Interview ────────────────────────────────────────────────────
  showOfferForm    = false;
  showInterviewForm = false;
  offerForm: any   = { offered_salary: '', notes: '' };
  offerSubmitting  = false;
  interviewForm: any = {
    round: 'HR', scheduled_at: '', duration_minutes: 60,
    format: 'video', location_or_link: '', interviewers: '',
  };
  interviewSubmitting = false;
  editingNotes = false;
  hrNotesText  = '';

  cvForm: any = {
    applicant_name: '', applicant_email: '', applicant_phone: '',
    position_applied: '', nationality: '', experience_years: null,
    skills: '', source: 'LinkedIn', expected_salary: null,
    available_from: '', notes: '', rating: 'hold',
  };
  cvFile: File | null = null;
  cvFileError = '';

  readonly sources  = ['LinkedIn', 'Walk-in', 'Referral', 'Website', 'Agency', 'Job Portal', 'Other'];
  readonly ratings  = [
    { value: 'shortlist', label: 'Shortlist', color: '#10b981' },
    { value: 'hold',      label: 'Hold',      color: '#f59e0b' },
    { value: 'reject',    label: 'Reject',    color: '#ef4444' },
  ];

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
      hire_date:        [new Date().toISOString().slice(0, 10), Validators.required],
      salary:           [null, Validators.required],
      company_email:    ['', [Validators.required, Validators.email]],
      department_id:    [''],
      designation_id:   [''],
      employment_type:  ['full_time'],
      probation_period: [90],
      custom_tasks:     [''],
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

  openOfferForm(app: any): void {
    this.selectedApp = app;
    this.offerForm = { offered_salary: '', notes: '' };
    this.offerSubmitting = false;
    this.showOfferForm = true;
    this.cdr.markForCheck();
  }

  sendOffer(): void {
    if (!this.offerForm.offered_salary) return;
    this.offerSubmitting = true;
    this.http.post(`${this.api}/offer/${this.selectedApp.id}`, this.offerForm)
      .pipe(takeUntil(this.destroy$)).subscribe({
        next: () => {
          this.offerSubmitting  = false;
          this.showOfferForm    = false;
          this.selectedApp.stage = 'offer';
          this.successMsg = 'Offer sent successfully.';
          this.loadStats();
          setTimeout(() => { this.successMsg = ''; this.cdr.markForCheck(); }, 3000);
          this.cdr.markForCheck();
        },
        error: (e: any) => {
          this.offerSubmitting = false;
          this.errorMsg = e?.error?.message ?? 'Failed to send offer.';
          this.cdr.markForCheck();
        },
      });
  }

  openInterviewForm(app: any): void {
    this.selectedApp = app;
    const now = new Date();
    now.setHours(10, 0, 0, 0);
    this.interviewForm = {
      round: 'HR', duration_minutes: 60, format: 'video',
      location_or_link: '', interviewers: '',
      scheduled_at: now.toISOString().slice(0, 16),
    };
    this.interviewSubmitting = false;
    this.showInterviewForm = true;
    this.cdr.markForCheck();
  }

  submitInterview(): void {
    if (!this.interviewForm.scheduled_at) return;
    this.interviewSubmitting = true;
    const body = { ...this.interviewForm, application_id: this.selectedApp.id };
    this.http.post(`${this.api}/interviews`, body).pipe(takeUntil(this.destroy$)).subscribe({
      next: () => {
        this.interviewSubmitting  = false;
        this.showInterviewForm    = false;
        this.selectedApp.stage    = 'interview';
        this.successMsg = 'Interview scheduled.';
        this.loadStats();
        setTimeout(() => { this.successMsg = ''; this.cdr.markForCheck(); }, 3000);
        this.cdr.markForCheck();
      },
      error: (e: any) => {
        this.interviewSubmitting = false;
        this.errorMsg = e?.error?.message ?? 'Failed.';
        this.cdr.markForCheck();
      },
    });
  }

  saveHrNotes(app: any): void {
    this.http.put(`${this.api}/applications/${app.id}/stage`, { stage: app.stage, hr_notes: this.hrNotesText })
      .pipe(takeUntil(this.destroy$)).subscribe({
        next: () => { app.hr_notes = this.hrNotesText; this.editingNotes = false; this.cdr.markForCheck(); },
      });
  }

  openHireForm(app: any): void {
    this.selectedApp = app;
    this.errorMsg = '';
    // Auto-suggest company email: first initial + last name @ dbroker.com.sa
    // e.g. "Jithin Varkey" → "j.varkey@dbroker.com.sa"
    const parts = (app.applicant_name || '').toLowerCase().trim().split(/\s+/);
    const suggested = parts.length > 1
      ? parts[0][0] + '.' + parts.slice(1).join('') + '@dbroker.com.sa'
      : (parts[0] || '') + '@dbroker.com.sa';

    this.hireForm.reset({
      hire_date:        new Date().toISOString().slice(0, 10),
      salary:           app.expected_salary ?? null,
      company_email:    suggested,
      department_id:    app.job_posting?.department_id ?? '',
      designation_id:   app.job_posting?.designation_id ?? '',
      employment_type:  app.job_posting?.employment_type ?? 'full_time',
      probation_period: 90,
      custom_tasks:     '',
    });
    this.showHireForm = true; this.cdr.markForCheck();
  }

  confirmHire(): void {
    if (this.hireForm.invalid) return;
    this.submitting = true;
    const body = { ...this.hireForm.value };
    // Split custom tasks string into array
    if (body.custom_tasks) {
      body.custom_tasks = String(body.custom_tasks).split('\n').map((t: string) => t.trim()).filter((t: string) => t);
    } else {
      delete body.custom_tasks;
    }

    this.http.post<any>(`${this.api}/hire/${this.selectedApp.id}`, body).pipe(takeUntil(this.destroy$)).subscribe({
      next: (r) => {
        this.submitting      = false;
        this.showHireForm    = false;
        this.showAppDetail   = false;
        this.selectedApp.stage = 'hired';
        this.hireResult      = r;
        this.showHireSuccess = true;
        this.loadStats(); this.loadJobs(this.currentPage);
        if (this.activeTab === 'pipeline') this.loadApplications(this.selectedJobFilter ?? undefined);
        this.cdr.markForCheck();
      },
      error: (err: any) => {
        this.submitting = false;
        this.errorMsg = err?.error?.message ?? 'Hire failed.';
        this.cdr.markForCheck();
      },
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
    if (id === 'cv_bank')  this.loadCvBank();
    this.cdr.markForCheck();
  }

  // ── CV Bank methods ────────────────────────────────────────────────────────

  loadCvBank(): void {
    this.cvBankLoading = true;
    const params: any = {};
    if (this.cvBankSearch) params.search = this.cvBankSearch;
    if (this.cvBankRating) params.rating = this.cvBankRating;
    if (this.cvBankSource) params.source = this.cvBankSource;
    this.http.get<any>(`${this.api}/cv-bank`, { params }).pipe(takeUntil(this.destroy$)).subscribe({
      next: r => { this.cvBankEntries = r.data ?? []; this.cvBankLoading = false; this.cdr.markForCheck(); },
      error: () => { this.cvBankLoading = false; this.cdr.markForCheck(); },
    });
  }

  openCvForm(): void {
    this.cvForm = {
      applicant_name: '', applicant_email: '', applicant_phone: '',
      position_applied: '', nationality: '', experience_years: null,
      skills: '', source: 'LinkedIn', expected_salary: null,
      available_from: '', notes: '', rating: 'hold',
    };
    this.cvFile = null;
    this.cvFileError = '';
    this.showCvForm = true;
    this.cdr.markForCheck();
  }

  onCvFileSelected(event: Event): void {
    const f = (event.target as HTMLInputElement).files?.[0] ?? null;
    this.cvFileError = '';
    if (!f) { this.cvFile = null; return; }
    if (f.size > 5 * 1024 * 1024) { this.cvFileError = 'Max 5 MB'; return; }
    this.cvFile = f;
  }

  submitCv(): void {
    if (!this.cvForm.applicant_name || !this.cvForm.applicant_email) return;
    this.cvFormSubmitting = true;
    const fd = new FormData();
    Object.entries(this.cvForm).forEach(([k, v]) => { if (v !== null && v !== '') fd.append(k, String(v)); });
    if (this.cvFile) fd.append('cv_file', this.cvFile, this.cvFile.name);

    this.http.post<any>(`${this.api}/cv-bank`, fd).pipe(takeUntil(this.destroy$)).subscribe({
      next: () => {
        this.cvFormSubmitting = false;
        this.showCvForm = false;
        this.loadCvBank();
        this.cdr.markForCheck();
      },
      error: (e: any) => {
        this.cvFormSubmitting = false;
        this.errorMsg = e?.error?.message || 'Failed to save CV';
        this.cdr.markForCheck();
      },
    });
  }

  viewCv(cv: any): void {
    this.selectedCv = cv;
    this.showCvDetail = true;
    this.cdr.markForCheck();
  }

  updateCvRating(cv: any, rating: string): void {
    this.http.put(`${this.api}/cv-bank/${cv.id}`, { rating }).pipe(takeUntil(this.destroy$)).subscribe({
      next: () => { cv.rating = rating; this.cdr.markForCheck(); },
    });
  }

  deleteCv(cv: any): void {
    if (!confirm(`Remove ${cv.applicant_name} from CV bank?`)) return;
    this.http.delete(`${this.api}/cv-bank/${cv.id}`).pipe(takeUntil(this.destroy$)).subscribe({
      next: () => { this.cvBankEntries = this.cvBankEntries.filter(c => c.id !== cv.id); this.cdr.markForCheck(); },
      error: (e: any) => alert(e?.error?.message || 'Delete failed'),
    });
  }

  openLinkModal(cv: any): void {
    this.selectedCv = cv;
    this.linkJobId  = '';
    this.showLinkModal = true;
    this.cdr.markForCheck();
  }

  confirmLink(): void {
    if (!this.linkJobId) return;
    this.http.post(`${this.api}/cv-bank/${this.selectedCv.id}/link`, { job_posting_id: this.linkJobId })
      .pipe(takeUntil(this.destroy$)).subscribe({
        next: () => {
          this.showLinkModal = false;
          this.successMsg = 'CV linked to job posting — appears in pipeline now.';
          this.loadCvBank();
          setTimeout(() => { this.successMsg = ''; this.cdr.markForCheck(); }, 4000);
          this.cdr.markForCheck();
        },
        error: (e: any) => alert(e?.error?.message || 'Link failed'),
      });
  }

  ratingColor(r: string): string {
    return ({ shortlist:'#10b981', hold:'#f59e0b', reject:'#ef4444' } as any)[r] ?? '#8b949e';
  }
  ratingLabel(r: string): string {
    return ({ shortlist:'Shortlist', hold:'Hold', reject:'Reject' } as any)[r] ?? r;
  }

  appsByStage(stage: string): any[] { return this.applications.filter(a => a.stage === stage); }
  stageData(s: string): any { return this.stages.find(x => x.value === s) ?? { label: s, color: '#8b949e', icon: 'help' }; }
  avatarColor(name?: string|null): string { const p=['#3b82f6','#10b981','#f59e0b','#ef4444','#6366f1','#0ea5e9','#f97316','#a78bfa']; return p[(name?.charCodeAt(0)??0)%p.length]; }
  initial(name?: string|null): string { return name?.charAt(0)?.toUpperCase() ?? '?'; }
  formatDate(d: string|null): string { if(!d) return '—'; return new Date(d).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}); }
  get pages(): number[] { if(!this.pagination?.last_page) return []; return Array.from({length:Math.min(this.pagination.last_page,8)},(_,i)=>i+1); }
  get f() { return this.jobForm.controls; }
  openAddApplicantForm(): void {
    this.applicantForm = {
      job_posting_id: this.selectedJobFilter ?? '',
      applicant_name: '', applicant_email: '',
      applicant_phone: '', expected_salary: null,
      available_from: '', cover_letter_text: '', stage: 'applied',
    };
    this.applicantFile = null;
    this.applicantFileError = '';
    this.errorMsg = '';
    this.showAddApplicant = true;
    this.cdr.markForCheck();
  }

  onApplicantFileSelected(event: Event): void {
    const f = (event.target as HTMLInputElement).files?.[0] ?? null;
    this.applicantFileError = '';
    if (!f) { this.applicantFile = null; return; }
    if (f.size > 5 * 1024 * 1024) { this.applicantFileError = 'Max 5 MB'; return; }
    this.applicantFile = f;
  }

  submitApplicant(): void {
    if (!this.applicantForm.job_posting_id || !this.applicantForm.applicant_name || !this.applicantForm.applicant_email) return;
    this.addApplicantBusy = true;
    this.errorMsg = '';

    const fd = new FormData();
    Object.entries(this.applicantForm).forEach(([k, v]) => {
      if (v !== null && v !== '' && v !== undefined) fd.append(k, String(v));
    });
    if (this.applicantFile) fd.append('cv_path', this.applicantFile, this.applicantFile.name);

    this.http.post<any>(`${this.api}/apply/${this.applicantForm.job_posting_id}`, fd)
      .pipe(takeUntil(this.destroy$)).subscribe({
        next: () => {
          this.addApplicantBusy = false;
          this.showAddApplicant = false;
          this.successMsg = `${this.applicantForm.applicant_name} added to the pipeline.`;
          this.loadApplications(this.selectedJobFilter ?? undefined);
          this.loadStats();
          setTimeout(() => { this.successMsg = ''; this.cdr.markForCheck(); }, 4000);
          this.cdr.markForCheck();
        },
        error: (e: any) => {
          this.addApplicantBusy = false;
          this.errorMsg = e?.error?.message ?? e?.error?.errors
            ? Object.values(e.error.errors).flat().join(' ')
            : 'Failed to add applicant.';
          this.cdr.markForCheck();
        },
      });
  }

  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }
}
