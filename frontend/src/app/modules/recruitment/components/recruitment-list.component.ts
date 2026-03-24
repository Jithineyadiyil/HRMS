import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { AuthService } from '../../../core/services/auth.service';

@Component({
  standalone: false, selector: 'app-recruitment-list',
  templateUrl: './recruitment-list.component.html',
  styleUrls: ['./recruitment-list.component.scss'],
})
export class RecruitmentListComponent implements OnInit {

  activeView: 'dashboard' | 'jobs' | 'applicants' = 'dashboard';
  loading     = false;
  submitting  = false;

  // ── Dashboard stats ───────────────────────────────────────────────────
  stats: any       = {};
  statsLoading     = true;

  // ── Jobs ─────────────────────────────────────────────────────────────
  jobs: any[]        = [];
  jobPagination: any = null;
  jobPage            = 1;
  jobStatusFilter    = '';
  selectedJob: any   = null;
  jobColumns = ['title','department','type','vacancies','applicants','status','actions'];

  // ── Applicants ────────────────────────────────────────────────────────
  applications: any[] = [];
  appPagination: any  = null;
  appLoading          = false;
  appStageFilter      = '';
  appColumns = ['applicant','job','stage','date','actions'];

  // ── Job form ──────────────────────────────────────────────────────────
  showJobForm    = false;
  jobEditId: number | null = null;
  jobFormError   = '';
  jobForm: any   = this.blankJob();

  // ── Applicant form ────────────────────────────────────────────────────
  showApplicantForm = false;
  appFormError      = '';
  applicantForm: any = this.blankApplicant();
  cvFile: File | null = null;

  // ── Stage modal ───────────────────────────────────────────────────────
  showStageModal = false;
  stageTarget: any = null;
  newStage = '';
  stageNote = '';
  stageSaving = false;

  // ── Interview modal ───────────────────────────────────────────────────
  showInterviewModal = false;
  interviewTarget: any = null;
  interviewForm: any = this.blankInterview();
  interviewError = '';

  // ── Hire modal ────────────────────────────────────────────────────────
  showHireModal = false;
  hireTarget: any = null;
  hireForm: any = { hire_date: '', salary: '' };
  hireError = '';

  // ── Lookups ───────────────────────────────────────────────────────────
  departments: any[] = [];

  readonly TABS = [
    { id: 'dashboard', label: 'Overview',   icon: 'dashboard'      },
    { id: 'jobs',      label: 'Job Postings', icon: 'work_outline'  },
    { id: 'applicants',label: 'All Applicants', icon: 'people'      },
  ];

  readonly JOB_STATUSES = [
    { value:'draft',   label:'Draft',   color:'#8b949e' },
    { value:'open',    label:'Open',    color:'#10b981' },
    { value:'on_hold', label:'On Hold', color:'#f59e0b' },
    { value:'closed',  label:'Closed',  color:'#ef4444' },
  ];

  readonly APP_STAGES = [
    { value:'applied',   label:'Applied',   icon:'inbox',        color:'#8b949e' },
    { value:'screening', label:'Screening', icon:'search',       color:'#6366f1' },
    { value:'interview', label:'Interview', icon:'groups',       color:'#f59e0b' },
    { value:'offer',     label:'Offer',     icon:'handshake',    color:'#0ea5e9' },
    { value:'hired',     label:'Hired',     icon:'check_circle', color:'#10b981' },
    { value:'rejected',  label:'Rejected',  icon:'cancel',       color:'#ef4444' },
  ];

  readonly EMP_TYPES = [
    { value:'full_time', label:'Full Time'  },
    { value:'part_time', label:'Part Time'  },
    { value:'contract',  label:'Contract'   },
    { value:'intern',    label:'Internship' },
  ];

  constructor(private http: HttpClient, public auth: AuthService) {}

  ngOnInit() {
    this.loadStats();
    this.loadDepartments();
  }

  // ── Dashboard ─────────────────────────────────────────────────────────

  loadStats() {
    this.statsLoading = true;
    this.http.get<any>('/api/v1/recruitment/stats').subscribe({
      next: r => { this.stats = r; this.statsLoading = false; },
      error: () => this.statsLoading = false,
    });
  }

  // ── Tab switching ─────────────────────────────────────────────────────

  switchTab(id: string) {
    this.activeView = id as any;
    if (id === 'jobs')        this.loadJobs();                          // always reload — show latest data
    if (id === 'applicants')  { this.selectedJob = null; this.loadApplications(); }
    if (id === 'dashboard')   this.loadStats();
  }

  // ── Jobs ──────────────────────────────────────────────────────────────

  loadJobs(page = 1) {
    this.loading = true; this.jobPage = page;
    const params: any = { page, per_page: 15 };
    if (this.jobStatusFilter) params.status = this.jobStatusFilter;
    this.http.get<any>('/api/v1/recruitment/jobs', { params }).subscribe({
      next: r => { this.jobs = r?.data || []; this.jobPagination = r; this.loading = false; },
      error: () => this.loading = false,
    });
  }

  openJobForm(job?: any) {
    this.jobEditId = job?.id ?? null; this.jobFormError = '';
    this.jobForm = job ? {
      title:           job.title,
      // Convert IDs to string — native <select> [value]="d.id" binds as string,
      // so Number vs string comparison would cause "no option selected" on edit
      department_id:   job.department_id  ? String(job.department_id)  : '',
      designation_id:  job.designation_id ? String(job.designation_id) : '',
      employment_type: job.employment_type,
      status:          job.status,
      vacancies:       job.vacancies ?? 1,
      description:     job.description   ?? '',
      requirements:    job.requirements  ?? '',
      benefits:        job.benefits      ?? '',
      salary_min:      job.salary_min    ?? '',
      salary_max:      job.salary_max    ?? '',
      location:        job.location      ?? '',
      closing_date:    job.closing_date?.slice(0,10) ?? '',
    } : this.blankJob();
    this.showJobForm = true;
  }

  saveJob() {
    if (!this.jobForm.title || !this.jobForm.employment_type || !this.jobForm.description) {
      this.jobFormError = 'Title, type and description are required.'; return;
    }
    this.submitting = true; this.jobFormError = '';
    const body = { ...this.jobForm,
      department_id:  this.jobForm.department_id  || null,
      designation_id: this.jobForm.designation_id || null,
      salary_min:     this.jobForm.salary_min     || null,
      salary_max:     this.jobForm.salary_max     || null,
      closing_date:   this.jobForm.closing_date   || null,
    };
    const req = this.jobEditId
      ? this.http.put<any>(`/api/v1/recruitment/jobs/${this.jobEditId}`, body)
      : this.http.post<any>('/api/v1/recruitment/jobs', body);
    req.subscribe({
      next: r => {
        this.submitting = false; this.showJobForm = false;
        // Patch the updated job in the local array for instant display (avoid full reload flicker)
        const updatedJob = r?.job;
        if (updatedJob && this.jobEditId) {
          const i = this.jobs.findIndex(j => j.id === this.jobEditId);
          if (i > -1) this.jobs[i] = updatedJob;
        }
        this.loadJobs(this.jobPage); this.loadStats();
      },
      error: err => {
        this.submitting = false;
        const errs = err?.error?.errors;
        this.jobFormError = errs ? Object.values(errs).flat().join(' ') : err?.error?.message || 'Failed to save.';
      },
    });
  }

  deleteJob(job: any) {
    if (!confirm(`Delete "${job.title}"? This cannot be undone.`)) return;
    this.http.delete(`/api/v1/recruitment/jobs/${job.id}`).subscribe({
      next: () => { this.loadJobs(this.jobPage); this.loadStats(); },
    });
  }

  // ── Applicants ────────────────────────────────────────────────────────

  viewJobApplicants(job: any) {
    this.selectedJob = job;
    this.activeView  = 'applicants';
    this.appStageFilter = '';
    this.loadApplications();
  }

  loadApplications(page = 1) {
    this.appLoading = true;
    const params: any = { page, per_page: 20 };
    if (this.selectedJob?.id) params.job_posting_id = this.selectedJob.id;
    if (this.appStageFilter)  params.stage = this.appStageFilter;
    this.http.get<any>('/api/v1/recruitment/applications', { params }).subscribe({
      next: r => { this.applications = r?.data || []; this.appPagination = r; this.appLoading = false; },
      error: () => this.appLoading = false,
    });
  }

  openApplicantForm(job?: any) {
    if (job) this.selectedJob = job;
    if (!this.selectedJob) { alert('Please select a job first.'); return; }
    this.applicantForm = this.blankApplicant(); this.appFormError = ''; this.cvFile = null;
    this.showApplicantForm = true;
  }

  onCvSelect(e: Event) {
    const f = (e.target as HTMLInputElement).files?.[0];
    if (f) this.cvFile = f;
  }

  submitApplicant() {
    if (!this.applicantForm.applicant_name || !this.applicantForm.applicant_email) {
      this.appFormError = 'Name and email are required.'; return;
    }
    this.submitting = true; this.appFormError = '';
    const fd = new FormData();
    fd.append('applicant_name',  this.applicantForm.applicant_name);
    fd.append('applicant_email', this.applicantForm.applicant_email);
    if (this.applicantForm.applicant_phone)   fd.append('applicant_phone',   this.applicantForm.applicant_phone);
    if (this.applicantForm.cover_letter_text) fd.append('cover_letter_text', this.applicantForm.cover_letter_text);
    if (this.applicantForm.expected_salary)   fd.append('expected_salary',   this.applicantForm.expected_salary);
    if (this.applicantForm.available_from)    fd.append('available_from',    this.applicantForm.available_from);
    if (this.cvFile)                          fd.append('cv_path', this.cvFile);

    this.http.post<any>(`/api/v1/recruitment/apply/${this.selectedJob.id}`, fd).subscribe({
      next: () => {
        this.submitting = false; this.showApplicantForm = false;
        this.loadApplications(); this.loadStats();
        if (this.jobs.length) this.loadJobs(this.jobPage);
      },
      error: err => {
        this.submitting = false;
        const errs = err?.error?.errors;
        this.appFormError = errs ? Object.values(errs).flat().join(' ') : err?.error?.message || 'Failed to add applicant.';
      },
    });
  }

  // ── Stage ─────────────────────────────────────────────────────────────

  openStageModal(app: any) {
    this.stageTarget = app; this.newStage = app.stage; this.stageNote = app.hr_notes ?? '';
    this.showStageModal = true;
  }

  saveStage() {
    if (!this.newStage) return;
    this.stageSaving = true;
    this.http.put<any>(`/api/v1/recruitment/applications/${this.stageTarget.id}/stage`, {
      stage: this.newStage, hr_notes: this.stageNote,
    }).subscribe({
      next: () => {
        const i = this.applications.findIndex(a => a.id === this.stageTarget.id);
        if (i > -1) this.applications[i] = { ...this.applications[i], stage: this.newStage, hr_notes: this.stageNote };
        this.stageSaving = false; this.showStageModal = false;
        this.loadStats();
      },
      error: () => this.stageSaving = false,
    });
  }

  // ── Interview ─────────────────────────────────────────────────────────

  openInterviewModal(app: any) {
    this.interviewTarget = app; this.interviewForm = this.blankInterview();
    this.interviewForm.application_id = app.id; this.interviewError = '';
    this.showInterviewModal = true;
  }

  saveInterview() {
    if (!this.interviewForm.scheduled_at || !this.interviewForm.round) {
      this.interviewError = 'Round and schedule are required.'; return;
    }
    this.submitting = true;
    this.http.post<any>('/api/v1/recruitment/interviews', this.interviewForm).subscribe({
      next: () => {
        this.submitting = false; this.showInterviewModal = false;
        this.http.put(`/api/v1/recruitment/applications/${this.interviewTarget.id}/stage`, { stage: 'interview' }).subscribe();
        this.loadApplications(); this.loadStats();
      },
      error: err => { this.submitting = false; this.interviewError = err?.error?.message || 'Failed.'; },
    });
  }

  // ── Hire ─────────────────────────────────────────────────────────────

  openHireModal(app: any) {
    this.hireTarget = app;
    this.hireForm   = { hire_date: new Date().toISOString().slice(0,10), salary: app.expected_salary ?? '' };
    this.hireError  = ''; this.showHireModal = true;
  }

  saveHire() {
    if (!this.hireForm.hire_date) { this.hireError = 'Hire date is required.'; return; }
    this.submitting = true;
    this.http.post<any>(`/api/v1/recruitment/hire/${this.hireTarget.id}`, this.hireForm).subscribe({
      next: () => { this.submitting = false; this.showHireModal = false; this.loadApplications(); this.loadStats(); },
      error: err => { this.submitting = false; this.hireError = err?.error?.message || 'Failed to create employee.'; },
    });
  }

  // ── Helpers ───────────────────────────────────────────────────────────

  private loadDepartments() {
    this.http.get<any>('/api/v1/departments').subscribe({
      next: r => this.departments = Array.isArray(r) ? r : (r?.data ?? []),
    });
  }

  private blankJob() {
    return { title:'', department_id:'', designation_id:'', employment_type:'full_time',
             status:'open', vacancies:1, description:'', requirements:'', benefits:'',
             salary_min:'', salary_max:'', location:'', closing_date:'' };
  }
  private blankApplicant() {
    return { applicant_name:'', applicant_email:'', applicant_phone:'',
             cover_letter_text:'', expected_salary:'', available_from:'' };
  }
  private blankInterview() {
    return { application_id:'', round:'HR', scheduled_at:'',
             duration_minutes:60, format:'video', location_or_link:'' };
  }

  statusCls(s: string) {
    return ({ open:'badge-green', draft:'badge-gray', on_hold:'badge-yellow', closed:'badge-red' } as any)[s] ?? 'badge-gray';
  }
  stageCls(s: string) {
    return ({ applied:'badge-gray', screening:'badge-purple', interview:'badge-yellow',
              offer:'badge-blue', hired:'badge-green', rejected:'badge-red' } as any)[s] ?? 'badge-gray';
  }
  stageMeta(s: string)       { return this.APP_STAGES.find(x => x.value === s) ?? { label:s, icon:'help', color:'#8b949e' }; }
  jobStatusLabel(s: string)  { return this.JOB_STATUSES.find(x => x.value === s)?.label ?? s; }
  stageCount(stage: string)  { return this.stats?.by_stage?.[stage] ?? 0; }
  fmtSAR(n: any)             { return n ? 'SAR ' + Number(n).toLocaleString('en-SA') : '—'; }

  get jobPages(): number[] {
    if (!this.jobPagination?.last_page) return [];
    return Array.from({ length: Math.min(this.jobPagination.last_page, 8) }, (_,i) => i+1);
  }
  get appPages(): number[] {
    if (!this.appPagination?.last_page) return [];
    return Array.from({ length: Math.min(this.appPagination.last_page, 8) }, (_,i) => i+1);
  }
  canManage(): boolean { return this.auth.canAny(['recruitment.manage']); }
}
