import { Component, OnInit, OnDestroy } from '@angular/core';
import { Router } from '@angular/router';
import { Store } from '@ngrx/store';
import { Subject } from 'rxjs';
import { takeUntil, debounceTime, distinctUntilChanged } from 'rxjs/operators';
import { FormControl } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import * as EmployeeActions from '../store/employee.actions';
import { selectAll } from '../store/employee.reducer';

@Component({
  standalone: false,
  selector: 'app-employee-list',
  templateUrl: './employee-list.component.html',
  styleUrls: ['./employee-list.component.scss'],
})
export class EmployeeListComponent implements OnInit, OnDestroy {
  employees$   = this.store.select((s: any) => selectAll(s['employees'] || {}));
  loading$     = this.store.select((s: any) => s['employees']?.loading ?? false);
  pagination$  = this.store.select((s: any) => s['employees']?.pagination ?? null);

  searchControl   = new FormControl('');
  statusFilter    = new FormControl('');
  deptFilter      = new FormControl('');
  typeFilter      = new FormControl('');

  departments: any[] = [];
  displayedColumns = ['avatar','employee_code','full_name','department','designation','employment_type','status','actions'];

  private destroy$ = new Subject<void>();

  constructor(private store: Store, private router: Router, private http: HttpClient) {}

  ngOnInit() {
    this.loadEmployees();
    this.http.get<any>('/api/v1/departments').subscribe(r => this.departments = r?.data || r || []);
    this.searchControl.valueChanges.pipe(debounceTime(400), distinctUntilChanged(), takeUntil(this.destroy$))
      .subscribe(() => this.loadEmployees());
  }

  loadEmployees(page = 1) {
    this.store.dispatch(EmployeeActions.loadEmployees({ params: {
      search: this.searchControl.value || '',
      status: this.statusFilter.value || '',
      department_id: this.deptFilter.value || '',
      employment_type: this.typeFilter.value || '',
      page, per_page: 15,
    }}));
  }

  clearFilters() {
    this.searchControl.setValue('');
    this.statusFilter.setValue('');
    this.deptFilter.setValue('');
    this.typeFilter.setValue('');
    this.loadEmployees();
  }

  viewEmployee(id: number)   { this.router.navigate(['/employees', id]); }
  editEmployee(id: number)   { this.router.navigate(['/employees', id, 'edit']); }
  addEmployee()              { this.router.navigate(['/employees', 'new']); }

  terminate(id: number) {
    if (confirm('Terminate this employee? This action cannot be undone.')) {
      this.store.dispatch(EmployeeActions.deleteEmployee({ id }));
    }
  }

  statusCls(s: string) {
    return ({ active:'badge-green', on_leave:'badge-yellow', probation:'badge-blue', inactive:'badge-gray', terminated:'badge-red' } as any)[s] || 'badge-gray';
  }
  typeCls(t: string) {
    return ({ full_time:'badge-blue', part_time:'badge-yellow', contract:'badge-orange', intern:'badge-purple' } as any)[t] || 'badge-gray';
  }
  initial(n?: string)  { return n?.charAt(0)?.toUpperCase() || '?'; }
  avCol(n?: string) {
    const c = ['#3b82f6','#10b981','#f59e0b','#ef4444','#6366f1','#0ea5e9','#f97316','#a78bfa'];
    return c[(n?.charCodeAt(0) ?? 0) % c.length];
  }

  ngOnDestroy() { this.destroy$.next(); this.destroy$.complete(); }
}
