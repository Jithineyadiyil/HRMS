import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { switchMap, tap } from 'rxjs/operators';

export const ROLES = {
  SUPER_ADMIN:        'super_admin',
  HR_MANAGER:         'hr_manager',
  HR_STAFF:           'hr_staff',
  FINANCE_MANAGER:    'finance_manager',
  DEPT_MANAGER:       'department_manager',
  EMPLOYEE:           'employee',
} as const;

@Injectable({ providedIn: 'root' })
export class AuthService {
  private apiUrl   = '/api/v1';
  private tokenKey = 'hrms_token';
  private userKey  = 'hrms_user';

  constructor(private http: HttpClient) {}

  login(email: string, password: string): Observable<any> {
    return this.http.get('/sanctum/csrf-cookie', { withCredentials: true }).pipe(
      switchMap(() =>
        this.http.post<any>(`${this.apiUrl}/auth/login`, { email, password },
          { headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' } }
        )
      ),
      tap(res => {
        if (res.token) localStorage.setItem(this.tokenKey, res.token);
        if (res.user)  localStorage.setItem(this.userKey, JSON.stringify(res.user));
      })
    );
  }

  logout() {
    this.http.post(`${this.apiUrl}/auth/logout`, {}).subscribe();
    localStorage.removeItem(this.tokenKey);
    localStorage.removeItem(this.userKey);
  }

  refreshUser(): Observable<any> {
    return this.http.get<any>(`${this.apiUrl}/auth/me`).pipe(
      tap(res => { if (res.user) localStorage.setItem(this.userKey, JSON.stringify(res.user)); })
    );
  }

  // ── Identity ──────────────────────────────────────────────────────────
  getToken(): string | null  { return localStorage.getItem(this.tokenKey); }
  isLoggedIn(): boolean      { return !!this.getToken(); }
  getUser(): any {
    const u = localStorage.getItem(this.userKey);
    return u ? JSON.parse(u) : null;
  }

  // ── Role helpers ──────────────────────────────────────────────────────
  getRoles(): string[]      { return this.getUser()?.roles || []; }
  getUserRole(): string     { return this.getRoles()[0] || ''; }
  getPermissions(): string[]{ return this.getUser()?.permissions || []; }

  hasRole(role: string): boolean       { return this.getRoles().includes(role); }
  hasAnyRole(roles: string[]): boolean { return roles.some(r => this.hasRole(r)); }
  can(permission: string): boolean     { return this.getPermissions().includes(permission); }
  canAny(perms: string[]): boolean     { return perms.some(p => this.can(p)); }

  // ── Convenience shortcuts ─────────────────────────────────────────────
  isSuperAdmin():      boolean { return this.hasRole(ROLES.SUPER_ADMIN); }
  isHRManager():       boolean { return this.hasRole(ROLES.HR_MANAGER); }
  isHRStaff():         boolean { return this.hasRole(ROLES.HR_STAFF); }
  isFinanceManager():  boolean { return this.hasRole(ROLES.FINANCE_MANAGER); }
  isDeptManager():     boolean { return this.hasRole(ROLES.DEPT_MANAGER); }
  isEmployee():        boolean { return this.hasRole(ROLES.EMPLOYEE); }

  isHRRole():      boolean { return this.hasAnyRole([ROLES.SUPER_ADMIN, ROLES.HR_MANAGER, ROLES.HR_STAFF]); }
  isAdminRole():   boolean { return this.hasAnyRole([ROLES.SUPER_ADMIN, ROLES.HR_MANAGER]); }
  isManagerRole(): boolean { return this.hasAnyRole([ROLES.SUPER_ADMIN, ROLES.HR_MANAGER, ROLES.HR_STAFF, ROLES.DEPT_MANAGER]); }

  // ── Portal type: drives which layout/dashboard to show ───────────────
  getPortalType(): 'admin' | 'hr' | 'finance' | 'manager' | 'employee' {
    if (this.isSuperAdmin())     return 'admin';
    if (this.isHRManager())      return 'hr';
    if (this.isHRStaff())        return 'hr';
    if (this.isFinanceManager()) return 'finance';
    if (this.isDeptManager())    return 'manager';
    return 'employee';
  }

  // ── Nav visibility ────────────────────────────────────────────────────
  getVisibleNavItems(): NavItem[] {
    const u = this.getUser();
    if (!u) return [];
    const portal    = this.getPortalType();
    const superAdmin = this.isSuperAdmin();

    // Full nav definition — order determines sidebar order
    // group = section header shown above the item (only first item in each group needs it)
    const all: NavItem[] = [
      // ── Overview ──────────────────────────────────────────────────
      { path: '/dashboard',   label: 'Dashboard',    icon: 'dashboard',            group: 'Overview' },

      // ── Workforce ─────────────────────────────────────────────────
      { path: '/employees',   label: 'Employees',    icon: 'people',               group: 'Workforce', perms: ['employees.view'] },
      { path: '/org-chart',   label: 'Org Chart',    icon: 'account_tree',         perms: ['orgchart.view'] },
      { path: '/recruitment', label: 'Recruitment',  icon: 'work_outline',         perms: ['recruitment.view'] },
      { path: '/performance', label: 'Performance',  icon: 'leaderboard',          perms: ['performance.view'] },

      // ── Time & Attendance ─────────────────────────────────────────
      { path: '/attendance',  label: 'Attendance',   icon: 'fingerprint',          group: 'Time & Leave', perms: ['attendance.view_all','attendance.view_own','attendance.checkin'] },
      { path: '/leave',       label: 'Leave',        icon: 'event_available',      perms: ['leave.view_all','leave.view_own','leave.request'] },
      { path: '/attendance/biotime', label: 'BioTime Devices', icon: 'developer_board', perms: ['admin.manage_users','attendance.view_all'] },

      // ── Payroll & Finance ─────────────────────────────────────────
      { path: '/payroll',     label: 'Payroll',      icon: 'payments',             group: 'Payroll & Finance', perms: ['payroll.view','payroll.view_own'] },
      { path: '/loans',       label: 'Loans',        icon: 'account_balance',      perms: ['loans.view_all','loans.view_own','loans.request'] },

      // ── HR Operations ─────────────────────────────────────────────
      { path: '/separations', label: 'Separations',  icon: 'exit_to_app',          group: 'HR Operations', perms: ['separations.view_all','separations.create'] },
      { path: '/requests',    label: 'Requests',     icon: 'inbox',                perms: ['requests.view_all'], excludePortal: ['employee'] },
      { path: '/requests',    label: 'My Requests',  icon: 'inbox',                perms: ['requests.view_own','requests.submit'], excludePortal: ['admin','hr','finance','manager'] },

      // ── Admin ──────────────────────────────────────────────────────
      { path: '/reports',     label: 'Reports',      icon: 'assessment',           group: 'Reports', perms: ['reports.view','payroll.view','employees.view'] },
      { path: '/admin',       label: 'Admin',        icon: 'admin_panel_settings', group: 'Administration', perms: ['admin.manage_users','admin.manage_roles'] },
    ];

    // Super admin: show everything once (skip duplicates by path)
    if (superAdmin) {
      const seen = new Set<string>();
      return all.filter(item => {
        if (seen.has(item.path)) return false;
        seen.add(item.path);
        return true;
      });
    }

    // All other roles: filter by permissions + portal exclusions
    const seen = new Set<string>();
    return all.filter(item => {
      const key = item.path + item.label;
      if (seen.has(key)) return false;

      if (item.excludePortal?.includes(portal)) return false;

      if (!item.perms?.length && !item.roles?.length) { seen.add(key); return true; }
      if (item.roles?.length && this.hasAnyRole(item.roles)) { seen.add(key); return true; }
      if (item.perms?.length && this.canAny(item.perms))     { seen.add(key); return true; }

      return false;
    });
  }
}

export interface NavItem {
  path: string;
  label: string;
  icon: string;
  group?: string;         // section header label
  groupDivider?: boolean; // render a divider above this item
  roles?: string[];
  perms?: string[];
  excludePortal?: string[];
}
