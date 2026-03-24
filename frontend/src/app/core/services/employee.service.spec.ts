/**
 * @fileoverview Unit tests for EmployeeService.
 *
 * Uses HttpClientTestingModule to intercept real HTTP and assert
 * the correct URLs, methods, params, and response mappings.
 *
 * @module core/services/employee.service.spec
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

describe('EmployeeService', () => {
  let service:     EmployeeService;
  let httpMock:    HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports:   [HttpClientTestingModule],
      providers: [EmployeeService],
    });

    service  = TestBed.inject(EmployeeService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify(); // assert no unexpected requests remain
  });

  // ── getAll ───────────────────────────────────────────────────────────────

  it('getAll() sends GET to /employees', () => {
    const mockPage: PaginatedResponse<Employee> = {
      data: [], current_page: 1, last_page: 1, per_page: 15, total: 0, from: null, to: null,
    };

    service.getAll().subscribe((res) => {
      expect(res.total).toBe(0);
    });

    const req = httpMock.expectOne(BASE);
    expect(req.request.method).toBe('GET');
    req.flush(mockPage);
  });

  it('getAll() appends non-empty filter params', () => {
    service.getAll({ status: 'active', search: 'Ahmed', page: 2 }).subscribe();

    const req = httpMock.expectOne((r) => r.url === BASE);
    expect(req.request.params.get('status')).toBe('active');
    expect(req.request.params.get('search')).toBe('Ahmed');
    expect(req.request.params.get('page')).toBe('2');
    req.flush({ data: [], current_page: 1, last_page: 1, per_page: 15, total: 0, from: null, to: null });
  });

  it('getAll() omits empty-string filter params', () => {
    service.getAll({ status: '', search: '' }).subscribe();

    const req = httpMock.expectOne(BASE);
    expect(req.request.params.has('status')).toBeFalse();
    expect(req.request.params.has('search')).toBeFalse();
    req.flush({ data: [], current_page: 1, last_page: 1, per_page: 15, total: 0, from: null, to: null });
  });

  // ── getOne ───────────────────────────────────────────────────────────────

  it('getOne() sends GET to /employees/:id and unwraps employee', () => {
    const mockEmployee = { id: 42, full_name: 'Ahmed Hassan' } as Employee;

    service.getOne(42).subscribe((emp) => {
      expect(emp.id).toBe(42);
      expect(emp.full_name).toBe('Ahmed Hassan');
    });

    const req = httpMock.expectOne(`${BASE}/42`);
    expect(req.request.method).toBe('GET');
    req.flush({ employee: mockEmployee });
  });

  // ── create ───────────────────────────────────────────────────────────────

  it('create() sends POST to /employees', () => {
    const payload = { first_name: 'Sara', email: 'sara@test.com' } as Partial<Employee>;
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

  // ── update ───────────────────────────────────────────────────────────────

  it('update() sends PUT to /employees/:id and unwraps employee', () => {
    const mockEmployee = { id: 5, first_name: 'Updated' } as Employee;

    service.update(5, { first_name: 'Updated' }).subscribe((emp) => {
      expect(emp.first_name).toBe('Updated');
    });

    const req = httpMock.expectOne(`${BASE}/5`);
    expect(req.request.method).toBe('PUT');
    req.flush({ employee: mockEmployee });
  });

  // ── delete ───────────────────────────────────────────────────────────────

  it('delete() sends DELETE to /employees/:id', () => {
    service.delete(9).subscribe((res) => {
      expect(res.message).toContain('terminated');
    });

    const req = httpMock.expectOne(`${BASE}/9`);
    expect(req.request.method).toBe('DELETE');
    req.flush({ message: 'Employee terminated and archived.' });
  });

  // ── export ───────────────────────────────────────────────────────────────

  it('export() sends GET with responseType blob', () => {
    service.export({ status: 'active' }).subscribe();

    const req = httpMock.expectOne((r) => r.url === `${BASE}/export`);
    expect(req.request.method).toBe('GET');
    expect(req.request.responseType).toBe('blob');
    expect(req.request.params.get('status')).toBe('active');
    req.flush(new Blob());
  });
});
