import { Component, OnInit, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { AuthService } from '../../../core/services/auth.service';

@Component({
  standalone: false,
  selector: 'app-profile',
  templateUrl: './profile.component.html',
  styleUrls: ['./profile.component.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProfileComponent implements OnInit {

  // ── Data ─────────────────────────────────────────────────────────────
  user: any     = null;
  employee: any = null;
  loading       = true;
  activeTab     = 'info';

  // ── Edit form ─────────────────────────────────────────────────────────
  editForm: any      = {};
  editSaving         = false;
  editSuccess        = '';
  editError          = '';

  // ── Password form ─────────────────────────────────────────────────────
  pwForm             = { current_password: '', password: '', password_confirmation: '' };
  pwSaving           = false;
  pwSuccess          = '';
  pwError            = '';
  showCurrentPw      = false;
  showNewPw          = false;
  showConfirmPw      = false;

  // ── Avatar ────────────────────────────────────────────────────────────
  avatarUploading    = false;
  avatarError        = '';

  readonly tabs = [
    { id: 'info',     label: 'My Info',        icon: 'person'   },
    { id: 'edit',     label: 'Edit Profile',   icon: 'edit'     },
    { id: 'password', label: 'Change Password', icon: 'lock'    },
  ];

  constructor(
    private http: HttpClient,
    private auth: AuthService,
    private cdr: ChangeDetectorRef,
  ) {}

  ngOnInit(): void {
    // Immediately populate from localStorage while API loads
    const stored = this.auth.getUser();
    if (stored) { this.user = stored; }

    this.http.get<any>('/api/v1/profile').subscribe({
      next: r => {
        this.user     = r.user     || this.user;
        this.employee = r.employee || null;
        this.loading  = false;
        this.initEditForm();
        this.cdr.markForCheck();
      },
      error: () => {
        this.loading = false;
        this.initEditForm();
        this.cdr.markForCheck();
      },
    });
  }

  // ── Helpers ───────────────────────────────────────────────────────────

  initial(n?: string): string {
    return (n || '?').split(' ').map((w: string) => w[0]).join('').toUpperCase().slice(0, 2);
  }

  roleBadge(role: string): string {
    const labels: Record<string, string> = {
      super_admin:        'Super Admin',
      hr_manager:         'HR Manager',
      hr_staff:           'HR Staff',
      finance_manager:    'Finance Manager',
      department_manager: 'Department Manager',
      employee:           'Employee',
    };
    return labels[role] || role;
  }

  avatarUrl(): string | null {
    return this.employee?.avatar_url || null;
  }

  // ── Edit Profile ──────────────────────────────────────────────────────

  private initEditForm(): void {
    this.editForm = {
      name:        this.user?.name        || '',
      phone:       this.employee?.phone   || '',
      arabic_name: this.employee?.arabic_name || '',
      address:     this.employee?.address || '',
      city:        this.employee?.city    || '',
      country:     this.employee?.country || '',
    };
  }

  saveProfile(): void {
    this.editSaving = true;
    this.editSuccess = '';
    this.editError   = '';

    this.http.put<any>('/api/v1/profile', this.editForm).subscribe({
      next: r => {
        this.editSaving  = false;
        this.editSuccess = 'Profile updated successfully.';
        this.user        = { ...this.user, name: this.editForm.name };
        // Refresh stored user
        if (r.user) localStorage.setItem('hrms_user', JSON.stringify({ ...this.auth.getUser(), name: r.user.name }));
        this.cdr.markForCheck();
      },
      error: e => {
        this.editSaving = false;
        this.editError  = e?.error?.message || 'Failed to update profile.';
        this.cdr.markForCheck();
      },
    });
  }

  // ── Change Password ───────────────────────────────────────────────────

  changePassword(): void {
    this.pwSuccess = '';
    this.pwError   = '';

    if (this.pwForm.password !== this.pwForm.password_confirmation) {
      this.pwError = 'New password and confirmation do not match.';
      this.cdr.markForCheck();
      return;
    }
    if (this.pwForm.password.length < 8) {
      this.pwError = 'Password must be at least 8 characters.';
      this.cdr.markForCheck();
      return;
    }

    this.pwSaving = true;

    this.http.put<any>('/api/v1/profile/password', this.pwForm).subscribe({
      next: () => {
        this.pwSaving  = false;
        this.pwSuccess = 'Password changed successfully.';
        this.pwForm    = { current_password: '', password: '', password_confirmation: '' };
        this.cdr.markForCheck();
      },
      error: e => {
        this.pwSaving = false;
        const errs    = e?.error?.errors;
        this.pwError  = errs
          ? Object.values(errs).flat().join(' ')
          : (e?.error?.message || 'Failed to change password.');
        this.cdr.markForCheck();
      },
    });
  }

  // ── Avatar Upload ─────────────────────────────────────────────────────

  onAvatarChange(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;

    const allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!allowed.includes(file.type)) {
      this.avatarError = 'Only JPG, PNG, GIF or WEBP images are allowed.';
      this.cdr.markForCheck();
      return;
    }
    if (file.size > 2 * 1024 * 1024) {
      this.avatarError = 'Image must be smaller than 2 MB.';
      this.cdr.markForCheck();
      return;
    }

    const fd = new FormData();
    fd.append('avatar', file);

    this.avatarUploading = true;
    this.avatarError     = '';

    this.http.post<any>('/api/v1/profile/avatar', fd).subscribe({
      next: r => {
        this.avatarUploading = false;
        if (this.employee) this.employee = { ...this.employee, avatar_url: r.avatar_url };
        this.cdr.markForCheck();
      },
      error: e => {
        this.avatarUploading = false;
        this.avatarError     = e?.error?.message || 'Failed to upload avatar.';
        this.cdr.markForCheck();
      },
    });
  }
}
