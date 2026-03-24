import { Component, OnInit, OnDestroy } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { AuthService } from '../../../core/services/auth.service';

@Component({
  standalone:   false,
  selector:     'app-employee-detail',
  templateUrl:  './employee-detail.component.html',
  styleUrls:    ['./employee-detail.component.scss'],
})
export class EmployeeDetailComponent implements OnInit, OnDestroy {
  loading       = true;
  loadError     = '';
  employeeId: any = null;
  employee: any   = null;
  documents:    any[] = [];
  leaveBalance: any[] = [];
  onboarding:   any[] = [];
  activeTab     = 'profile';

  // ── Attendance tab state ───────────────────────────────────────────────
  attendanceLogs:    any[]    = [];
  attendanceLoading  = false;
  attendanceMonth    = new Date().getMonth() + 1;
  attendanceYear     = new Date().getFullYear();

  // Upload state
  showUploadForm = false;
  dragOver       = false;
  uploading      = false;
  uploadProgress = 0;
  uploadError    = '';
  uploadData: { title: string; type: string; expiry_date: string; file: File | null } = {
    title: '', type: '', expiry_date: '', file: null
  };

  tabs = [
    { id: 'profile',    label: 'Profile',     icon: 'person' },
    { id: 'documents',  label: 'Documents',   icon: 'folder' },
    { id: 'leave',      label: 'Leave Balance',icon: 'event_available' },
    { id: 'onboarding', label: 'Onboarding',  icon: 'checklist' },
    { id: 'attendance', label: 'Attendance',  icon: 'fingerprint' },
  ];

  months = [
    { v: 1, l: 'January' }, { v: 2, l: 'February' }, { v: 3, l: 'March' },
    { v: 4, l: 'April' },   { v: 5, l: 'May' },       { v: 6, l: 'June' },
    { v: 7, l: 'July' },    { v: 8, l: 'August' },    { v: 9, l: 'September' },
    { v: 10, l: 'October'},{ v: 11, l: 'November' }, { v: 12, l: 'December' },
  ];

  years = Array.from({ length: 3 }, (_, i) => new Date().getFullYear() - i);

  private readonly destroy$ = new Subject<void>();

  constructor(
    private readonly route:  ActivatedRoute,
    private readonly router: Router,
    private readonly http:   HttpClient,
    private readonly auth:   AuthService,
  ) {}

  ngOnInit(): void {
    const id = this.route.snapshot.paramMap.get('id');
    this.employeeId = id;

    this.http.get<any>(`/api/v1/employees/${id}`)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: r => {
          this.employee     = r.employee || r;
          this.loading      = false;
          this.leaveBalance = this.employee?.leave_allocations || [];
          this.onboarding   = this.employee?.onboarding_tasks || [];
          this.loadDocuments();
        },
        error: err => {
          this.loading = false;
          if (err?.status === 0) {
            this.loadError = 'Cannot connect to server. Make sure the backend is running on port 8000.';
          } else if (err?.status === 404) {
            this.loadError = 'Employee not found (ID may be invalid).';
          } else {
            this.loadError = err?.error?.message || ('Server error ' + err?.status + '. Check Laravel logs.');
          }
        },
      });
  }

  onTabChange(tabId: string): void {
    this.activeTab = tabId;
    if (tabId === 'attendance' && !this.attendanceLogs.length) {
      this.loadAttendance();
    }
  }

  // ── Attendance ────────────────────────────────────────────────────────

  loadAttendance(): void {
    this.attendanceLoading = true;
    const params: any = { month: this.attendanceMonth, year: this.attendanceYear };
    this.http.get<any>(`/api/v1/attendance/employee/${this.employeeId}`, { params })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: res => {
          this.attendanceLogs    = res.data ?? res ?? [];
          this.attendanceLoading = false;
        },
        error: () => { this.attendanceLoading = false; },
      });
  }

  onAttendanceFilterChange(): void { this.loadAttendance(); }

  formatMins(mins: number | null): string {
    if (!mins) return '—';
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
  }

  attendanceStatusClass(status: string): string {
    const map: Record<string, string> = {
      present:  'badge-green',
      absent:   'badge-red',
      late:     'badge-yellow',
      half_day: 'badge-orange',
    };
    return map[status] ?? 'badge-gray';
  }

  // ── Documents ─────────────────────────────────────────────────────────

  loadDocuments(): void {
    this.http.get<any>(`/api/v1/employees/${this.employeeId}/documents`)
      .pipe(takeUntil(this.destroy$))
      .subscribe({ next: d => this.documents = d?.documents || [], error: () => {} });
  }

  edit(): void { this.router.navigate(['/employees', this.employee?.id, 'edit']); }
  back(): void { this.router.navigate(['/employees']); }

  initial(n?: string): string {
    return n?.split(' ').map((w: string) => w[0]).join('').toUpperCase().slice(0, 2) || '?';
  }
  avCol(n?: string): string {
    const c = ['#3b82f6','#10b981','#f59e0b','#ef4444','#6366f1','#0ea5e9','#f97316','#a78bfa'];
    return c[(n?.charCodeAt(0) ?? 0) % c.length];
  }
  statusCls(s: string): string {
    return ({ active:'badge-green', on_leave:'badge-yellow', probation:'badge-blue',
              inactive:'badge-gray', terminated:'badge-red' } as any)[s] || 'badge-gray';
  }
  taskCls(s: string): string {
    return ({ completed:'badge-green', in_progress:'badge-blue', pending:'badge-yellow',
              overdue:'badge-red' } as any)[s] || 'badge-gray';
  }
  pctLeave(alloc: any): number {
    if (!alloc?.allocated_days) return 0;
    return Math.round((alloc.used_days / alloc.allocated_days) * 100);
  }
  fmtSize(bytes: number): string {
    if (!bytes) return '—';
    if (bytes < 1024)        return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  }

  onFileSelect(e: Event): void {
    const input = e.target as HTMLInputElement;
    if (input.files?.[0]) this.uploadData.file = input.files[0];
  }
  onDrop(e: DragEvent): void {
    e.preventDefault();
    this.dragOver = false;
    const file = e.dataTransfer?.files?.[0];
    if (file) this.uploadData.file = file;
  }

  submitUpload(): void {
    if (!this.uploadData.title) { this.uploadError = 'Document title is required.'; return; }
    if (!this.uploadData.type)  { this.uploadError = 'Document type is required.'; return; }
    if (!this.uploadData.file)  { this.uploadError = 'Please select a file to upload.'; return; }
    if (this.uploadData.file.size > 10 * 1024 * 1024) { this.uploadError = 'File must be under 10MB.'; return; }

    this.uploading      = true;
    this.uploadProgress = 0;
    this.uploadError    = '';

    const fd = new FormData();
    fd.append('title', this.uploadData.title);
    fd.append('type',  this.uploadData.type);
    fd.append('file',  this.uploadData.file);
    if (this.uploadData.expiry_date) fd.append('expiry_date', this.uploadData.expiry_date);

    const ivl = setInterval(() => {
      if (this.uploadProgress < 85) this.uploadProgress += 15;
    }, 200);

    this.http.post<any>(`/api/v1/employees/${this.employee.id}/documents`, fd)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: r => {
          clearInterval(ivl);
          this.uploadProgress = 100;
          setTimeout(() => {
            this.documents.unshift(r.document);
            this.uploading      = false;
            this.uploadProgress = 0;
            this.showUploadForm = false;
            this.uploadData     = { title: '', type: '', expiry_date: '', file: null };
          }, 400);
        },
        error: err => {
          clearInterval(ivl);
          this.uploading      = false;
          this.uploadProgress = 0;
          this.uploadError    = err?.error?.message || 'Upload failed. Please try again.';
        },
      });
  }

  deleteDoc(docId: number): void {
    if (!confirm('Delete this document? This cannot be undone.')) return;
    this.http.delete(`/api/v1/employees/${this.employee.id}/documents/${docId}`)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: () => { this.documents = this.documents.filter(d => d.id !== docId); },
        error: () => {},
      });
  }

  downloadDoc(doc: any): void {
    const url = doc.file_url || doc.download_url ||
      `/api/v1/employees/${this.employee.id}/documents/${doc.id}/download`;
    window.open(url, '_blank');
  }

  docIcon(type: string): string {
    return ({ contract:'description', id:'badge', certificate:'workspace_premium',
              visa:'flight', passport:'import_contacts', medical:'medical_information',
              other:'attach_file' } as any)[type] || 'attach_file';
  }
  docIconBg(type: string): string {
    return ({ contract:'rgba(59,130,246,.1)', id:'rgba(167,139,250,.1)',
              certificate:'rgba(16,185,129,.1)', visa:'rgba(14,165,233,.1)',
              passport:'rgba(249,115,22,.1)', medical:'rgba(239,68,68,.1)',
              other:'rgba(139,148,158,.1)' } as any)[type] || 'rgba(139,148,158,.1)';
  }
  docIconColor(type: string): string {
    return ({ contract:'#3b82f6', id:'#a78bfa', certificate:'#10b981',
              visa:'#0ea5e9', passport:'#f97316', medical:'#ef4444',
              other:'#8b949e' } as any)[type] || '#8b949e';
  }

  isExpired(d: string): boolean     { return !!d && new Date(d) < new Date(); }
  isExpiringSoon(d: string): boolean {
    const diff = new Date(d).getTime() - Date.now();
    return diff > 0 && diff < 30 * 86400000;
  }

  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }
}
