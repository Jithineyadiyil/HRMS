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
    const portal = this.getPortalType();

    const all: NavItem[] = [
      { path: '/dashboard',   label: 'Dashboard',    icon: 'dashboard',          roles: [] },
      { path: '/employees',   label: 'Employees',    icon: 'people',             perms: ['employees.view'] },
      { path: '/org-chart',   label: 'Org Chart',    icon: 'account_tree',       perms: ['orgchart.view'] },
      { path: '/payroll',     label: 'Payroll',      icon: 'payments',           perms: ['payroll.view','payroll.view_own'] },
      { path: '/leave',       label: 'Leave',        icon: 'event_available',    perms: ['leave.view_all','leave.view_own','leave.request'] },
      { path: '/loans',       label: 'Loans',        icon: 'account_balance',    perms: ['loans.view_all','loans.view_own','loans.request'] },
      { path: '/contracts',   label: 'Contracts',    icon: 'description',        perms: ['contracts.view','contracts.create'] },
      { path: '/separations', label: 'Separations',  icon: 'exit_to_app',        perms: ['separations.view_all','separations.create'] },
      { path: '/requests',    label: 'My Requests',  icon: 'inbox',              perms: ['requests.view_own','requests.submit'] },
      { path: '/requests',    label: 'Requests',     icon: 'inbox',              perms: ['requests.view_all'], excludePortal: ['employee'] },
      { path: '/recruitment', label: 'Recruitment',  icon: 'work',               perms: ['recruitment.view'] },
      { path: '/performance', label: 'Performance',  icon: 'leaderboard',        perms: ['performance.view'] },
      { path: '/admin',       label: 'Admin',        icon: 'admin_panel_settings', perms: ['admin.manage_users','admin.manage_roles'] },
    ];

    const seen = new Set<string>();
    return all.filter(item => {
      const key = item.path + item.label;
      if (seen.has(key)) return false;

      // excludePortal filter
      if (item.excludePortal?.includes(portal)) return false;

      // No permission restriction = always show
      if (!item.perms?.length && !item.roles?.length) { seen.add(key); return true; }

      // Role match
      if (item.roles?.length && this.hasAnyRole(item.roles)) { seen.add(key); return true; }

      // Permission match
      if (item.perms?.length && this.canAny(item.perms)) { seen.add(key); return true; }

      return false;
    });
  }
}

export interface NavItem {
  path: string;
  label: string;
  icon: string;
  roles?: string[];
  perms?: string[];
  excludePortal?: string[];
}
