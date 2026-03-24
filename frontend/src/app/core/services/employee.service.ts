/**
 * @fileoverview Angular service for Employee API communication.
 *
 * This service is the single point of contact between NgRx Effects and the
 * Laravel backend. All HTTP concerns (URLs, headers, response mapping) are
 * encapsulated here so Effects remain free of transport logic.
 *
 * @module core/services/employee.service
 */

import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from '../../../environments/environment';
import {
  Employee,
  EmployeeFilters,
  PaginatedResponse,
} from '../../shared/models/employee.model';

/**
 * HTTP response shape from POST /employees (includes temp_password once).
 */
export interface CreateEmployeeResponse {
  message:       string;
  employee:      Employee;
  temp_password: string;
}

/**
 * Typed service for all Employee CRUD and file operations.
 *
 * Inject this into NgRx Effects — never call HttpClient directly from Effects.
 */
@Injectable({ providedIn: 'root' })
export class EmployeeService {
  private readonly baseUrl = `${environment.apiUrl}/employees`;

  constructor(private readonly http: HttpClient) {}

  /**
   * Fetch a paginated, filtered list of employees.
   *
   * @param   filters  Optional filter/pagination parameters
   * @returns Observable wrapping the paginated API response
   */
  getAll(filters?: EmployeeFilters): Observable<PaginatedResponse<Employee>> {
    let params = new HttpParams();

    if (filters) {
      Object.entries(filters).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
          params = params.set(key, String(value));
        }
      });
    }

    return this.http.get<PaginatedResponse<Employee>>(this.baseUrl, { params });
  }

  /**
   * Fetch a single employee by ID.
   *
   * @param   id  Employee primary key
   * @returns Observable wrapping the employee object
   */
  getOne(id: number): Observable<Employee> {
    return this.http
      .get<{ employee: Employee }>(`${this.baseUrl}/${id}`)
      .pipe(map((r) => r.employee));
  }

  /**
   * Create a new employee record.
   *
   * @param   data  Partial employee payload validated server-side
   * @returns Observable wrapping the creation response (includes temp_password)
   */
  create(data: Partial<Employee>): Observable<CreateEmployeeResponse> {
    return this.http.post<CreateEmployeeResponse>(this.baseUrl, data);
  }

  /**
   * Update an existing employee record.
   *
   * @param   id    Employee primary key
   * @param   data  Partial update payload
   * @returns Observable wrapping the updated employee
   */
  update(id: number, data: Partial<Employee>): Observable<Employee> {
    return this.http
      .put<{ employee: Employee }>(`${this.baseUrl}/${id}`, data)
      .pipe(map((r) => r.employee));
  }

  /**
   * Terminate (soft-delete) an employee.
   *
   * @param   id  Employee primary key
   * @returns Observable wrapping the success message
   */
  delete(id: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.baseUrl}/${id}`);
  }

  /**
   * Upload or replace an employee's avatar image.
   *
   * @param   id    Employee primary key
   * @param   file  Image file selected by the user
   * @returns Observable wrapping the new avatar_url
   */
  uploadAvatar(id: number, file: File): Observable<{ avatar_url: string }> {
    const fd = new FormData();
    fd.append('avatar', file);
    return this.http.post<{ avatar_url: string }>(`${this.baseUrl}/${id}/avatar`, fd);
  }

  /**
   * Upload a document to an employee's record.
   *
   * @param   id    Employee primary key
   * @param   data  FormData containing file, title, type, expiry_date
   * @returns Observable wrapping the created document record
   */
  uploadDocument(id: number, data: FormData): Observable<unknown> {
    return this.http.post(`${this.baseUrl}/${id}/documents`, data);
  }

  /**
   * Fetch all documents attached to an employee.
   *
   * @param   id  Employee primary key
   * @returns Observable wrapping the documents array
   */
  getDocuments(id: number): Observable<unknown[]> {
    return this.http
      .get<{ documents: unknown[] }>(`${this.baseUrl}/${id}/documents`)
      .pipe(map((r) => r.documents));
  }

  /**
   * Export all (filtered) employees as a CSV blob.
   *
   * @param   filters  Optional filter parameters
   * @returns Observable wrapping a binary Blob for download
   */
  export(filters?: EmployeeFilters): Observable<Blob> {
    let params = new HttpParams();

    if (filters) {
      Object.entries(filters).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
          params = params.set(key, String(value));
        }
      });
    }

    return this.http.get(`${this.baseUrl}/export`, {
      params,
      responseType: 'blob',
    });
  }
}
