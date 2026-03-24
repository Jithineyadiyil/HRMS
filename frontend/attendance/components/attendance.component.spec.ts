import { ComponentFixture, TestBed, fakeAsync, tick } from '@angular/core/testing';
import { HttpClientTestingModule, HttpTestingController } from '@angular/common/http/testing';
import { RouterTestingModule } from '@angular/router/testing';
import { NO_ERRORS_SCHEMA } from '@angular/core';

import { AttendanceComponent } from './attendance.component';
import { AuthService } from '../../../core/services/auth.service';

describe('AttendanceComponent', () => {
  let component: AttendanceComponent;
  let fixture: ComponentFixture<AttendanceComponent>;
  let httpMock: HttpTestingController;
  let authServiceSpy: jasmine.SpyObj<AuthService>;

  beforeEach(async () => {
    authServiceSpy = jasmine.createSpyObj('AuthService', ['hasAnyRole', 'getUser']);
    authServiceSpy.hasAnyRole.and.returnValue(true);
    authServiceSpy.getUser.and.returnValue({ name: 'Test User' });

    await TestBed.configureTestingModule({
      imports: [HttpClientTestingModule, RouterTestingModule],
      declarations: [AttendanceComponent],
      providers: [{ provide: AuthService, useValue: authServiceSpy }],
      schemas: [NO_ERRORS_SCHEMA],
    }).compileComponents();

    fixture   = TestBed.createComponent(AttendanceComponent);
    component = fixture.componentInstance;
    httpMock  = TestBed.inject(HttpTestingController);
  });

  afterEach(() => httpMock.verify());

  // ── Initialization ────────────────────────────────────────────────────

  it('should create the component', () => {
    expect(component).toBeTruthy();
  });

  it('should start on today tab', () => {
    expect(component.activeTab).toBe('today');
  });

  it('should load today attendance on init', () => {
    fixture.detectChanges();

    const req = httpMock.expectOne(r => r.url.includes('/attendance/today'));
    expect(req.request.method).toBe('GET');
    req.flush({ log: null, server_time: '09:00:00' });

    expect(component.todayLog).toBeNull();
  });

  // ── Check-in / Check-out ──────────────────────────────────────────────

  it('should call check-in API and set todayLog', () => {
    fixture.detectChanges();
    httpMock.expectOne(r => r.url.includes('/attendance/today')).flush({ log: null });
    httpMock.expectOne(r => r.url.includes('/departments')).flush([]);
    httpMock.expectOne(r => r.url.includes('/employees')).flush({ data: [] });

    const mockLog = { id: 1, check_in: '09:00:00', check_out: null, status: 'present' };
    component.checkIn();

    const req = httpMock.expectOne(r => r.url.includes('/attendance/checkin'));
    expect(req.request.method).toBe('POST');
    req.flush({ message: 'Check-in recorded successfully.', log: mockLog });

    expect(component.todayLog).toEqual(mockLog);
    expect(component.checkingIn).toBeFalse();
  });

  it('should set todayError on check-in failure', () => {
    fixture.detectChanges();
    httpMock.expectOne(r => r.url.includes('/attendance/today')).flush({ log: null });
    httpMock.expectOne(r => r.url.includes('/departments')).flush([]);
    httpMock.expectOne(r => r.url.includes('/employees')).flush({ data: [] });

    component.checkIn();
    httpMock.expectOne(r => r.url.includes('/attendance/checkin'))
      .flush({ message: 'Already checked in today' }, { status: 422, statusText: 'Unprocessable' });

    expect(component.todayError).toBe('Already checked in today');
  });

  // ── Tab switching ────────────────────────────────────────────────────

  it('should switch tab and load data lazily', () => {
    fixture.detectChanges();
    httpMock.expectOne(r => r.url.includes('/attendance/today')).flush({ log: null });
    httpMock.expectOne(r => r.url.includes('/departments')).flush([]);
    httpMock.expectOne(r => r.url.includes('/employees')).flush({ data: [] });

    component.switchTab('my-log');
    expect(component.activeTab).toBe('my-log');

    const req = httpMock.expectOne(r => r.url.includes('/attendance/my-log'));
    expect(req.request.method).toBe('GET');
    req.flush({ logs: [], summary: {}, month: 3, year: 2026 });
  });

  it('should not re-load my-log if already loaded', () => {
    fixture.detectChanges();
    httpMock.expectOne(r => r.url.includes('/attendance/today')).flush({ log: null });
    httpMock.expectOne(r => r.url.includes('/departments')).flush([]);
    httpMock.expectOne(r => r.url.includes('/employees')).flush({ data: [] });

    component.myLogs = [{ id: 1 }]; // pre-loaded
    component.switchTab('my-log');
    httpMock.expectNone(r => r.url.includes('/attendance/my-log'));
  });

  // ── Calendar builder ─────────────────────────────────────────────────

  it('should build calendar with correct number of cells', () => {
    // March 2026 starts on Sunday → offset 6 for Mon-start
    (component as any).buildCalendar([], 3, 2026);
    const cells = component.calendarDays;

    // 6 fillers + 31 days = 37
    const fillers = cells.filter(c => c === null).length;
    const days    = cells.filter(c => c !== null).length;
    expect(days).toBe(31);
    expect(fillers).toBe(6);
  });

  it('should mark today correctly in calendar', () => {
    const now = new Date();
    (component as any).buildCalendar([], now.getMonth() + 1, now.getFullYear());
    const todayCell = component.calendarDays.find(c => c?.isToday);
    expect(todayCell).toBeTruthy();
  });

  // ── Helpers ──────────────────────────────────────────────────────────

  it('should return correct statusCls', () => {
    expect(component.statusCls('present')).toBe('badge-green');
    expect(component.statusCls('late')).toBe('badge-yellow');
    expect(component.statusCls('absent')).toBe('badge-red');
    expect(component.statusCls('unknown')).toBe('badge-gray');
  });

  it('should return correct pct', () => {
    expect(component.pct(5, 20)).toBe(25);
    expect(component.pct(0, 0)).toBe(0);
  });

  it('should filter daily records by search', () => {
    component.dailyRecords = [
      { employee: { first_name: 'Ahmed', last_name: 'Ali', employee_code: 'EMP001' } },
      { employee: { first_name: 'Sara',  last_name: 'Khan', employee_code: 'EMP002' } },
    ];
    component.dailySearch = 'ahmed';
    expect(component.filteredDailyRecords.length).toBe(1);
    expect(component.filteredDailyRecords[0].employee.first_name).toBe('Ahmed');
  });

  // ── Manual form ──────────────────────────────────────────────────────

  it('should open manual form with empty state when no log passed', () => {
    component.openManualForm();
    expect(component.showManualForm).toBeTrue();
    expect(component.manualEditId).toBeNull();
    expect(component.manualForm.employee_id).toBe('');
  });

  it('should populate form when editing existing log', () => {
    const log = { id: 5, employee_id: 10, date: '2026-03-01', check_in: '09:00:00', check_out: '17:00:00', status: 'present', notes: '' };
    component.openManualForm(log);
    expect(component.manualEditId).toBe(5);
    expect(component.manualForm.employee_id).toBe(10);
  });

  it('should validate employee_id before saving', () => {
    component.manualForm.employee_id = '';
    component.saveManual();
    expect(component.manualError).toBe('Employee is required.');
  });

  // ── HR role check ────────────────────────────────────────────────────

  it('should return true for isHR when user has hr_manager role', () => {
    authServiceSpy.hasAnyRole.and.returnValue(true);
    expect(component.isHR()).toBeTrue();
  });

  it('should hide HR tabs for regular employees', () => {
    authServiceSpy.hasAnyRole.and.returnValue(false);
    const visible = component.visibleTabs;
    expect(visible.every(t => !t.hrOnly)).toBeTrue();
  });
});
