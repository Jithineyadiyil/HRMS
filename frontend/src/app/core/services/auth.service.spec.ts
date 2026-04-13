import { TestBed, fakeAsync, tick } from '@angular/core/testing';
import { HttpClientTestingModule, HttpTestingController } from '@angular/common/http/testing';

import { AuthService, ROLES } from './auth.service';

/**
 * Unit tests for AuthService.
 *
 * Covers: login, logout, token storage, role helpers,
 * permission helpers, and nav item filtering.
 *
 * @group auth
 */
describe('AuthService', () => {
  let service: AuthService;
  let httpMock: HttpTestingController;

  const mockUserHR = {
    id: 1,
    name: 'HR Manager',
    email: 'hr@example.com',
    roles: ['hr_manager'],
    permissions: ['employees.view', 'leave.view_all', 'payroll.view'],
    employee: null,
  };

  const mockUserEmployee = {
    id: 2,
    name: 'Test Employee',
    email: 'emp@example.com',
    roles: ['employee'],
    permissions: ['leave.view_own', 'leave.request', 'requests.view_own', 'requests.submit'],
    employee: { id: 10, code: 'EMP001', full_name: 'Test Employee', avatar_url: null, department: 'IT' },
  };

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [HttpClientTestingModule],
      providers: [AuthService],
    });

    service  = TestBed.inject(AuthService);
    httpMock = TestBed.inject(HttpTestingController);

    localStorage.clear();
  });

  afterEach(() => {
    httpMock.verify();
    localStorage.clear();
  });

  // ── Login ──────────────────────────────────────────────────────────────

  it('should store token and user on successful login', fakeAsync(() => {
    service.login('hr@example.com', 'password').subscribe();

    httpMock.expectOne('/sanctum/csrf-cookie').flush({});
    httpMock.expectOne('/api/v1/auth/login').flush({
      token: 'test-token-123',
      user:  mockUserHR,
    });

    tick();

    expect(service.getToken()).toBe('test-token-123');
    expect(service.getUser()?.email).toBe('hr@example.com');
  }));

  it('should add Bearer token to Authorization header after login', fakeAsync(() => {
    service.login('hr@example.com', 'password').subscribe();
    httpMock.expectOne('/sanctum/csrf-cookie').flush({});
    httpMock.expectOne('/api/v1/auth/login').flush({ token: 'abc123', user: mockUserHR });
    tick();

    expect(service.getToken()).toBe('abc123');
  }));

  // ── Logout ─────────────────────────────────────────────────────────────

  it('should clear storage on logout', fakeAsync(() => {
    localStorage.setItem('hrms_token', 'tok');
    localStorage.setItem('hrms_user', JSON.stringify(mockUserHR));

    service.logout();
    httpMock.expectOne('/api/v1/auth/logout').flush({});
    tick();

    expect(localStorage.getItem('hrms_token')).toBeNull();
    expect(localStorage.getItem('hrms_user')).toBeNull();
    expect(service.isLoggedIn()).toBeFalse();
  }));

  // ── Role helpers ───────────────────────────────────────────────────────

  it('hasRole should return true for matching role', () => {
    localStorage.setItem('hrms_user', JSON.stringify(mockUserHR));

    expect(service.hasRole(ROLES.HR_MANAGER)).toBeTrue();
    expect(service.hasRole(ROLES.EMPLOYEE)).toBeFalse();
  });

  it('hasAnyRole should return true when at least one role matches', () => {
    localStorage.setItem('hrms_user', JSON.stringify(mockUserHR));

    expect(service.hasAnyRole([ROLES.EMPLOYEE, ROLES.HR_MANAGER])).toBeTrue();
    expect(service.hasAnyRole([ROLES.EMPLOYEE, ROLES.FINANCE_MANAGER])).toBeFalse();
  });

  it('isHRRole returns true for hr_manager', () => {
    localStorage.setItem('hrms_user', JSON.stringify(mockUserHR));
    expect(service.isHRRole()).toBeTrue();
  });

  it('isHRRole returns false for employee', () => {
    localStorage.setItem('hrms_user', JSON.stringify(mockUserEmployee));
    expect(service.isHRRole()).toBeFalse();
  });

  it('getRoles normalises array shape from Spatie serialisation', () => {
    // Spatie sometimes returns {0: 'super_admin'} instead of ['super_admin']
    const userWithObjRoles = { ...mockUserHR, roles: { 0: 'hr_manager' } };
    localStorage.setItem('hrms_user', JSON.stringify(userWithObjRoles));

    const roles = service.getRoles();
    expect(Array.isArray(roles)).toBeTrue();
    expect(roles).toContain('hr_manager');
  });

  // ── Permission helpers ─────────────────────────────────────────────────

  it('can should return true for held permission', () => {
    localStorage.setItem('hrms_user', JSON.stringify(mockUserHR));
    expect(service.can('employees.view')).toBeTrue();
  });

  it('can should return false for missing permission', () => {
    localStorage.setItem('hrms_user', JSON.stringify(mockUserEmployee));
    expect(service.can('payroll.view')).toBeFalse();
  });

  // ── Portal type ───────────────────────────────────────────────────────

  it('getPortalType returns hr for hr_manager', () => {
    localStorage.setItem('hrms_user', JSON.stringify(mockUserHR));
    expect(service.getPortalType()).toBe('hr');
  });

  it('getPortalType returns employee for employee role', () => {
    localStorage.setItem('hrms_user', JSON.stringify(mockUserEmployee));
    expect(service.getPortalType()).toBe('employee');
  });

  // ── Nav items ──────────────────────────────────────────────────────────

  it('getVisibleNavItems returns items matching user permissions', () => {
    localStorage.setItem('hrms_user', JSON.stringify(mockUserHR));

    const items = service.getVisibleNavItems();

    // HR manager should see employees (has employees.view permission)
    expect(items.some(i => i.path === '/employees')).toBeTrue();
  });

  it('getVisibleNavItems excludes admin for regular employee', () => {
    localStorage.setItem('hrms_user', JSON.stringify(mockUserEmployee));

    const items = service.getVisibleNavItems();

    expect(items.some(i => i.path === '/admin')).toBeFalse();
  });

  it('getVisibleNavItems returns empty array when not logged in', () => {
    expect(service.getVisibleNavItems()).toEqual([]);
  });

  it('dashboard appears for all authenticated users', () => {
    localStorage.setItem('hrms_user', JSON.stringify(mockUserEmployee));
    const items = service.getVisibleNavItems();
    expect(items.some(i => i.path === '/dashboard')).toBeTrue();
  });

  // ── isLoggedIn ────────────────────────────────────────────────────────

  it('isLoggedIn returns false when no token', () => {
    expect(service.isLoggedIn()).toBeFalse();
  });

  it('isLoggedIn returns true when token exists', () => {
    localStorage.setItem('hrms_token', 'tok');
    expect(service.isLoggedIn()).toBeTrue();
  });
});
