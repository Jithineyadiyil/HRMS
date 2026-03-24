import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { HttpClient } from '@angular/common/http';

@Component({
  standalone: false,
  selector: 'app-employee-detail',
  templateUrl: './employee-detail.component.html',
  styleUrls: ['./employee-detail.component.scss'],
})
export class EmployeeDetailComponent implements OnInit {
  loading  = true;
  employeeId: any = null;
  employee: any = null;
  documents:    any[] = [];
  leaveBalance: any[] = [];
  onboarding:   any[] = [];
  activeTab = 'profile';

  payslips:     any[] = [];
  payslipsLoading = false;
  showAvatarPicker = false;
  avatarUploading  = false;

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
    { id: 'profile',    label: 'Profile',      icon: 'person'          },
    { id: 'documents',  label: 'Documents',    icon: 'folder'          },
    { id: 'payslips',   label: 'Payslips',     icon: 'receipt_long'    },
    { id: 'leave',      label: 'Leave Balance',icon: 'event_available' },
    { id: 'onboarding', label: 'Onboarding',   icon: 'checklist'       },
  ];

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private http: HttpClient
  ) {}

  loadError = '';

  ngOnInit() {
    const id = this.route.snapshot.paramMap.get('id');
    this.employeeId = id;
    this.http.get<any>(`/api/v1/employees/${id}`).subscribe({
      next: r => {
        this.employee     = r.employee || r;
        this.loading      = false;
        this.leaveBalance = this.employee?.leave_allocations || [];
        // onboarding_tasks comes from eager-loaded relation (snake_case from Laravel)
        this.onboarding   = this.employee?.onboarding_tasks || [];
        this.loadDocuments();
        // onboarding already eager-loaded by show() — no extra call needed
      },
      error: err => {
        this.loading   = false;
        if (err?.status === 0) {
          this.loadError = 'Cannot connect to server. Make sure the backend is running on port 8000.';
        } else if (err?.status === 404) {
          this.loadError = 'Employee not found (ID may be invalid).';
        } else {
          this.loadError = err?.error?.message || ('Server error ' + err?.status + '. Check Laravel logs.');
        }
      }
    });
  }

  loadDocuments() {
    this.http.get<any>(`/api/v1/employees/${this.employeeId}/documents`)
      .subscribe({ next: d => this.documents = d?.documents || [], error: () => {} });
  }

  loadOnboarding() {
    // Onboarding tasks are already loaded from show() eager load in ngOnInit.
    // This is a no-op kept for future explicit refresh if needed.
  }

  updateTaskStatus(task: any, status: string) {
    this.http.put<any>(`/api/v1/onboarding/tasks/${task.id}`, { status }).subscribe({
      next: r => {
        const idx = this.onboarding.findIndex((t: any) => t.id === task.id);
        if (idx > -1) this.onboarding[idx] = { ...this.onboarding[idx], status };
      },
      error: () => {}
    });
  }

  switchTab(id: string) {
    this.activeTab = id;
    if (id === 'payslips' && !this.payslips.length) this.loadPayslips();
  }

  loadPayslips() {
    this.payslipsLoading = true;
    this.http.get<any>(`/api/v1/payroll/employee/${this.employeeId}`)
      .subscribe({
        next: r  => { this.payslips = r?.data || r || []; this.payslipsLoading = false; },
        error: () => { this.payslipsLoading = false; }
      });
  }

  uploadAvatar(file: File) {
    if (!file) return;
    this.avatarUploading = true;
    const fd = new FormData();
    fd.append('avatar', file);
    this.http.post<any>(`/api/v1/employees/${this.employee.id}/avatar`, fd).subscribe({
      next: r  => { this.employee = { ...this.employee, avatar_url: r.avatar_url }; this.avatarUploading = false; },
      error: () => { this.avatarUploading = false; }
    });
  }

  onAvatarSelect(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (file) this.uploadAvatar(file);
  }

  fmtSAR(n: any) { return 'SAR ' + (parseFloat(n) || 0).toLocaleString('en-SA', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

  edit() { this.router.navigate(['/employees', this.employee?.id, 'edit']); }
  back() { this.router.navigate(['/employees']); }

  initial(n?: string) { return n?.split(' ').map((w:string) => w[0]).join('').toUpperCase().slice(0,2) || '?'; }
  avCol(n?: string) {
    const c = ['#3b82f6','#10b981','#f59e0b','#ef4444','#6366f1','#0ea5e9','#f97316','#a78bfa'];
    return c[(n?.charCodeAt(0) ?? 0) % c.length];
  }
  statusCls(s: string) {
    return ({ active:'badge-green', on_leave:'badge-yellow', probation:'badge-blue', inactive:'badge-gray', terminated:'badge-red' } as any)[s] || 'badge-gray';
  }
  taskCls(s: string) {
    return ({ completed:'badge-green', in_progress:'badge-blue', pending:'badge-yellow', overdue:'badge-red' } as any)[s] || 'badge-gray';
  }
  pctLeave(alloc: any) {
    if (!alloc?.allocated_days) return 0;
    return Math.round((alloc.used_days / alloc.allocated_days) * 100);
  }
  fmtSize(bytes: number) {
    if (!bytes) return '—';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024*1024) return (bytes/1024).toFixed(1) + ' KB';
    return (bytes/(1024*1024)).toFixed(1) + ' MB';
  }

  // ── Document helpers ──────────────────────────────────────────────────────
  onFileSelect(e: Event) {
    const input = e.target as HTMLInputElement;
    if (input.files?.[0]) this.uploadData.file = input.files[0];
  }

  onDrop(e: DragEvent) {
    e.preventDefault();
    this.dragOver = false;
    const file = e.dataTransfer?.files?.[0];
    if (file) this.uploadData.file = file;
  }

  submitUpload() {
    if (!this.uploadData.title) { this.uploadError = 'Document title is required.'; return; }
    if (!this.uploadData.type)  { this.uploadError = 'Document type is required.'; return; }
    if (!this.uploadData.file)  { this.uploadError = 'Please select a file to upload.'; return; }
    if (this.uploadData.file.size > 10 * 1024 * 1024) { this.uploadError = 'File must be under 10MB.'; return; }

    this.uploading      = true;
    this.uploadProgress = 0;
    this.uploadError    = '';

    const fd = new FormData();
    fd.append('title',       this.uploadData.title);
    fd.append('type',        this.uploadData.type);
    fd.append('file',        this.uploadData.file);
    if (this.uploadData.expiry_date) fd.append('expiry_date', this.uploadData.expiry_date);

    // Simulate progress then do the actual upload
    const interval = setInterval(() => {
      if (this.uploadProgress < 85) this.uploadProgress += 15;
    }, 200);

    this.http.post<any>(`/api/v1/employees/${this.employee.id}/documents`, fd).subscribe({
      next: r => {
        clearInterval(interval);
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
        clearInterval(interval);
        this.uploading      = false;
        this.uploadProgress = 0;
        this.uploadError    = err?.error?.message || 'Upload failed. Please try again.';
      }
    });
  }

  deleteDoc(docId: number) {
    if (!confirm('Delete this document? This cannot be undone.')) return;
    this.http.delete(`/api/v1/employees/${this.employee.id}/documents/${docId}`).subscribe({
      next: () => { this.documents = this.documents.filter(d => d.id !== docId); },
      error: () => {}
    });
  }

  downloadDoc(doc: any) {
    // Public storage files (file_url) can be opened directly.
    // Private/local API downloads need auth headers via HttpClient.
    if (doc.file_url && doc.file_url.startsWith('http')) {
      window.open(doc.file_url, '_blank');
      return;
    }
    // Use HttpClient so auth token is sent in the Authorization header
    this.http.get(`/api/v1/employees/${this.employee?.id}/documents/${doc.id}/download`,
      { responseType: 'blob' }
    ).subscribe({
      next: blob => {
        const url = window.URL.createObjectURL(blob);
        const a   = document.createElement('a');
        a.style.display = 'none';
        a.href     = url;
        a.download = doc.file_name || 'document';
        document.body.appendChild(a);
        a.click();
        setTimeout(() => { window.URL.revokeObjectURL(url); document.body.removeChild(a); }, 500);
      },
      error: () => alert('Download failed. The file may have been deleted from the server.')
    });
  }

  docIcon(type: string) {
    return ({ contract:'description', id:'badge', certificate:'workspace_premium',
              visa:'flight', passport:'import_contacts', medical:'medical_information',
              other:'attach_file' } as any)[type] || 'attach_file';
  }
  docIconBg(type: string) {
    return ({ contract:'rgba(59,130,246,.1)', id:'rgba(167,139,250,.1)', certificate:'rgba(16,185,129,.1)',
              visa:'rgba(14,165,233,.1)', passport:'rgba(249,115,22,.1)', medical:'rgba(239,68,68,.1)',
              other:'rgba(139,148,158,.1)' } as any)[type] || 'rgba(139,148,158,.1)';
  }
  docIconColor(type: string) {
    return ({ contract:'#3b82f6', id:'#a78bfa', certificate:'#10b981',
              visa:'#0ea5e9', passport:'#f97316', medical:'#ef4444',
              other:'#8b949e' } as any)[type] || '#8b949e';
  }

  isExpired(d: string)     { return d && new Date(d) < new Date(); }
  isExpiringSoon(d: string){ const diff = new Date(d).getTime() - Date.now(); return diff > 0 && diff < 30 * 86400000; }
}
