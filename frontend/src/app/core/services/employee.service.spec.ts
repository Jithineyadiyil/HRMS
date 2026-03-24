/**
 * @fileoverview Unit tests for EmployeeService.
 * Test runner: Karma + Jasmine. Zero Jest APIs.
 * Uses HttpClientTestingModule to intercept HTTP without a real server.
 */

import { TestBed } from '@angular/core/testing';
import {
  HttpClientTestingModule,
  HttpTestingController,
} from '@angular/common/http/testing';
import { EmployeeService } from './employee.service';
import { Employee, PaginatedResponse } from '../../shared/models/employee.model';
import { environment } from '../../../environments/environment';

const BASE = `${environment.apiUrl}/employees`;

const emptyPage = (): PaginatedResponse<Employee> => ({
  data: [], current_page: 1, last_page: 1,
  per_page: 15, total: 0, from: null, to: null,
});

describe('EmployeeService', () => {
  let service:  EmployeeService;
  let httpMock: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports:   [HttpClientTestingModule],
      providers: [EmployeeService],
    });
    service  = TestBed.inject(EmployeeService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => { httpMock.verify(); });

  // ── getAll ───────────────────────────────────────────────────────────

  it('getAll() sends GET to /employees', () => {
    service.getAll().subscribe((res) => {
      expect(res.total).toBe(0);
      expect(res.data).toEqual([]);
    });
    const req = httpMock.expectOne(BASE);
    expect(req.request.method).toBe('GET');
    req.flush(emptyPage());
  });

  it('getAll() appends non-empty filter params', () => {
    service.getAll({ status: 'active', search: 'Ahmed', page: 2 }).subscribe();
    const req = httpMock.expectOne((r) => r.url === BASE);
    expect(req.request.params.get('status')).toBe('active');
    expect(req.request.params.get('search')).toBe('Ahmed');
    expect(req.request.params.get('page')).toBe('2');
    req.flush(emptyPage());
  });

  it('getAll() omits empty-string params', () => {
    service.getAll({ status: '', search: '' }).subscribe();
    const req = httpMock.expectOne(BASE);
    expect(req.request.params.has('status')).toBe(false);
    expect(req.request.params.has('search')).toBe(false);
    req.flush(emptyPage());
  });

  it('getAll() omits null/undefined params', () => {
    service.getAll({ status: undefined, search: undefined }).subscribe();
    const req = httpMock.expectOne(BASE);
    expect(req.request.params.keys().length).toBe(0);
    req.flush(emptyPage());
  });

  // ── getOne ──────────────────────────────────────────────────────────

  it('getOne() sends GET /employees/:id and unwraps employee', () => {
    const emp = { id: 42, full_name: 'Ahmed Hassan' } as Employee;
    service.getOne(42).subscribe((result) => {
      expect(result.id).toBe(42);
      expect(result.full_name).toBe('Ahmed Hassan');
    });
    const req = httpMock.expectOne(`${BASE}/42`);
    expect(req.request.method).toBe('GET');
    req.flush({ employee: emp });
  });

  it('getOne() propagates HTTP 404 errors', () => {
    let errorCaught = false;
    service.getOne(9999).subscribe({ error: () => { errorCaught = true; } });
    httpMock.expectOne(`${BASE}/9999`).flush(
      { message: 'Not Found' }, { status: 404, statusText: 'Not Found' }
    );
    expect(errorCaught).toBe(true);
  });

  // ── create ──────────────────────────────────────────────────────────

  it('create() sends POST /employees with the payload', () => {
    const payload: Partial<Employee> = { first_name: 'Sara', email: 'sara@test.com' };
    const mockRes = {
      message: 'Employee created successfully.',
      employee: { id: 1, ...payload } as Employee,
      temp_password: 'Abc123XyZ!@#',
    };
    service.create(payload).subscribe((res) => {
      expect(res.temp_password.length).toBeGreaterThanOrEqual(12);
      expect(res.employee.id).toBe(1);
    });
    const req = httpMock.expectOne(BASE);
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual(payload);
    req.flush(mockRes);
  });

  it('create() temp_password is never the old hardcoded value', () => {
    service.create({ email: 'x@test.com' }).subscribe((res) => {
      expect(res.temp_password).not.toBe('Password@123');
    });
    httpMock.expectOne(BASE).flush({
      message: 'ok',
      employee: { id: 2 } as Employee,
      temp_password: 'R4nd0m!Secure',
    });
  });

  // ── update ──────────────────────────────────────────────────────────

  it('update() sends PUT /employees/:id and unwraps updated employee', () => {
    const updated = { id: 5, first_name: 'UpdatedName' } as Employee;
    service.update(5, { first_name: 'UpdatedName' }).subscribe((emp) => {
      expect(emp.first_name).toBe('UpdatedName');
    });
    const req = httpMock.expectOne(`${BASE}/5`);
    expect(req.request.method).toBe('PUT');
    expect(req.request.body).toEqual({ first_name: 'UpdatedName' });
    req.flush({ employee: updated });
  });

  // ── delete ──────────────────────────────────────────────────────────

  it('delete() sends DELETE /employees/:id', () => {
    service.delete(9).subscribe((res) => {
      expect(res.message).toContain('terminated');
    });
    const req = httpMock.expectOne(`${BASE}/9`);
    expect(req.request.method).toBe('DELETE');
    req.flush({ message: 'Employee terminated and archived.' });
  });

  // ── uploadAvatar ─────────────────────────────────────────────────────

  it('uploadAvatar() sends POST with FormData', () => {
    const file = new File(['data'], 'avatar.png', { type: 'image/png' });
    service.uploadAvatar(3, file).subscribe((res) => {
      expect(res.avatar_url).toContain('avatars/');
    });
    const req = httpMock.expectOne(`${BASE}/3/avatar`);
    expect(req.request.method).toBe('POST');
    expect(req.request.body instanceof FormData).toBe(true);
    req.flush({ avatar_url: 'http://localhost/storage/avatars/3.png' });
  });

  // ── export ──────────────────────────────────────────────────────────

  it('export() sends GET /employees/export with blob responseType', () => {
    service.export({ status: 'active' }).subscribe();
    const req = httpMock.expectOne((r) => r.url === `${BASE}/export`);
    expect(req.request.method).toBe('GET');
    expect(req.request.responseType).toBe('blob');
    expect(req.request.params.get('status')).toBe('active');
    req.flush(new Blob(['csv'], { type: 'text/csv' }));
  });

  it('export() omits empty filter params', () => {
    service.export({ status: '' }).subscribe();
    const req = httpMock.expectOne((r) => r.url === `${BASE}/export`);
    expect(req.request.params.has('status')).toBe(false);
    req.flush(new Blob());
  });
});
