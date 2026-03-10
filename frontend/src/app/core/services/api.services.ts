import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

const BASE = environment.apiUrl;

// ── Employee Service ──────────────────────────────────────────────────────────
@Injectable({ providedIn: 'root' })
export class EmployeeApiService {
  constructor(private http: HttpClient) {}
  private url = `${BASE}/employees`;
  getAll(params?: any): Observable<any> { return this.http.get(this.url, { params }); }
  getOne(id: number): Observable<any> { return this.http.get(`${this.url}/${id}`); }
  create(data: any): Observable<any> { return this.http.post(this.url, data); }
  update(id: number, data: any): Observable<any> { return this.http.put(`${this.url}/${id}`, data); }
  delete(id: number): Observable<any> { return this.http.delete(`${this.url}/${id}`); }
  uploadAvatar(id: number, file: File): Observable<any> {
    const fd = new FormData(); fd.append('avatar', file);
    return this.http.post(`${this.url}/${id}/avatar`, fd);
  }
  uploadDocument(id: number, data: FormData): Observable<any> { return this.http.post(`${this.url}/${id}/documents`, data); }
  getDocuments(id: number): Observable<any> { return this.http.get(`${this.url}/${id}/documents`); }
  export(params?: any): Observable<any> { return this.http.get(`${this.url}/export`, { params, responseType: 'blob' }); }
}

// ── Payroll Service ───────────────────────────────────────────────────────────
@Injectable({ providedIn: 'root' })
export class PayrollApiService {
  constructor(private http: HttpClient) {}
  private url = `${BASE}/payroll`;
  getAll(params?: any): Observable<any> { return this.http.get(this.url, { params }); }
  run(data: any): Observable<any> { return this.http.post(`${this.url}/run`, data); }
  getOne(id: number): Observable<any> { return this.http.get(`${this.url}/${id}`); }
  approve(id: number): Observable<any> { return this.http.post(`${this.url}/${id}/approve`, {}); }
  reject(id: number, reason: string): Observable<any> { return this.http.post(`${this.url}/${id}/reject`, { reason }); }
  getPayslips(id: number, params?: any): Observable<any> { return this.http.get(`${this.url}/${id}/payslips`, { params }); }
  getEmployeeHistory(empId: number): Observable<any> { return this.http.get(`${this.url}/employee/${empId}`); }
  downloadPayslip(id: number): Observable<any> { return this.http.get(`${this.url}/payslip/${id}/download`, { responseType: 'blob' }); }
  exportBankTransfer(id: number): Observable<any> { return this.http.get(`${this.url}/${id}/export`, { responseType: 'blob' }); }
  getComponents(): Observable<any> { return this.http.get(`${this.url}/components`); }
  createComponent(data: any): Observable<any> { return this.http.post(`${this.url}/components`, data); }
}

// ── Leave Service ─────────────────────────────────────────────────────────────
@Injectable({ providedIn: 'root' })
export class LeaveApiService {
  constructor(private http: HttpClient) {}
  private url = `${BASE}/leave`;
  getTypes(): Observable<any> { return this.http.get(`${this.url}/types`); }
  getRequests(params?: any): Observable<any> { return this.http.get(`${this.url}/requests`, { params }); }
  createRequest(data: any): Observable<any> { return this.http.post(`${this.url}/requests`, data); }
  approve(id: number): Observable<any> { return this.http.post(`${this.url}/requests/${id}/approve`, {}); }
  reject(id: number, reason: string): Observable<any> { return this.http.post(`${this.url}/requests/${id}/reject`, { reason }); }
  cancel(id: number): Observable<any> { return this.http.delete(`${this.url}/requests/${id}`); }
  getBalance(empId: number): Observable<any> { return this.http.get(`${this.url}/balance/${empId}`); }
  getCalendar(params?: any): Observable<any> { return this.http.get(`${this.url}/calendar`, { params }); }
}

// ── Recruitment Service ───────────────────────────────────────────────────────
@Injectable({ providedIn: 'root' })
export class RecruitmentApiService {
  constructor(private http: HttpClient) {}
  private url = `${BASE}/recruitment`;
  getJobs(params?: any): Observable<any> { return this.http.get(`${this.url}/jobs`, { params }); }
  createJob(data: any): Observable<any> { return this.http.post(`${this.url}/jobs`, data); }
  updateJob(id: number, data: any): Observable<any> { return this.http.put(`${this.url}/jobs/${id}`, data); }
  getApplications(params?: any): Observable<any> { return this.http.get(`${this.url}/applications`, { params }); }
  getApplication(id: number): Observable<any> { return this.http.get(`${this.url}/applications/${id}`); }
  updateStage(id: number, stage: string, notes?: string): Observable<any> { return this.http.put(`${this.url}/applications/${id}/stage`, { stage, hr_notes: notes }); }
  scheduleInterview(data: any): Observable<any> { return this.http.post(`${this.url}/interviews`, data); }
  sendOffer(appId: number, data: any): Observable<any> { return this.http.post(`${this.url}/offer/${appId}`, data); }
  hire(appId: number, data: any): Observable<any> { return this.http.post(`${this.url}/hire/${appId}`, data); }
}

// ── Performance Service ───────────────────────────────────────────────────────
@Injectable({ providedIn: 'root' })
export class PerformanceApiService {
  constructor(private http: HttpClient) {}
  private url = `${BASE}/performance`;
  getCycles(params?: any): Observable<any> { return this.http.get(`${this.url}/reviews`, { params }); }
  createCycle(data: any): Observable<any> { return this.http.post(`${this.url}/reviews`, data); }
  getCycle(id: number): Observable<any> { return this.http.get(`${this.url}/reviews/${id}`); }
  submitSelf(cycleId: number, data: any): Observable<any> { return this.http.post(`${this.url}/reviews/${cycleId}/self`, data); }
  submitManager(cycleId: number, data: any): Observable<any> { return this.http.post(`${this.url}/reviews/${cycleId}/manager`, data); }
  getKpis(params?: any): Observable<any> { return this.http.get(`${this.url}/kpis`, { params }); }
  createKpi(data: any): Observable<any> { return this.http.post(`${this.url}/kpis`, data); }
  getReport(empId: number): Observable<any> { return this.http.get(`${this.url}/reports/${empId}`); }
}

// ── Dashboard Service ─────────────────────────────────────────────────────────
@Injectable({ providedIn: 'root' })
export class DashboardApiService {
  constructor(private http: HttpClient) {}
  getStats(): Observable<any> { return this.http.get(`${BASE}/dashboard/stats`); }
}

// ── OrgChart Service ──────────────────────────────────────────────────────────
@Injectable({ providedIn: 'root' })
export class OrgChartApiService {
  constructor(private http: HttpClient) {}
  getChart(): Observable<any> { return this.http.get(`${BASE}/org-chart`); }
  getDepartments(): Observable<any> { return this.http.get(`${BASE}/departments`); }
  createDepartment(data: any): Observable<any> { return this.http.post(`${BASE}/departments`, data); }
  updateDepartment(id: number, data: any): Observable<any> { return this.http.put(`${BASE}/departments/${id}`, data); }
}

// ── Attendance Service ────────────────────────────────────────────────────────
@Injectable({ providedIn: 'root' })
export class AttendanceApiService {
  constructor(private http: HttpClient) {}
  checkIn(): Observable<any> { return this.http.post(`${BASE}/attendance/checkin`, {}); }
  checkOut(): Observable<any> { return this.http.post(`${BASE}/attendance/checkout`, {}); }
  getToday(): Observable<any> { return this.http.get(`${BASE}/attendance/today`); }
  getLog(empId: number, params?: any): Observable<any> { return this.http.get(`${BASE}/attendance/employee/${empId}`, { params }); }
  manualEntry(data: any): Observable<any> { return this.http.post(`${BASE}/attendance/manual`, data); }
}
