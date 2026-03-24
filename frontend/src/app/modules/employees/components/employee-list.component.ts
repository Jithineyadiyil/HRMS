/**
 * @fileoverview Employee list component.
 *
 * Displays a paginated, filterable table of employees with a summary
 * stat strip at the top. All list state is read from the NgRx store;
 * the stat strip is loaded via a direct HTTP call.
 *
 * @module employees/components/employee-list.component
 */

import {
  Component,
  OnInit,
  OnDestroy,
  ChangeDetectionStrategy,
  ChangeDetectorRef,
} from '@angular/core';
import { Router } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Store } from '@ngrx/store';
import { Observable, Subject } from 'rxjs';
import {
  takeUntil,
  debounceTime,
  distinctUntilChanged,
} from 'rxjs/operators';
import { FormControl } from '@angular/forms';
import {
  Employee,
  EmployeeStatus,
  EmploymentType,
} from '../../../shared/models/employee.model';
import { Pagination } from '../store/employee.reducer';
import * as EmployeeActions from '../store/employee.actions';
import { selectAll } from '../store/employee.reducer';
import { DepartmentRef } from '../../../shared/models/employee.model';

/** The slice of global state that contains the employee feature state. */
interface AppState {
  employees: {
    ids:              number[];
    entities:         Record<number, Employee>;
    loading:          boolean;
    pagination:       Pagination | null;
    filters:          Record<string, unknown>;
    error:            string | null;
  };
}

/** Summary counts displayed in the stat strip. */
interface EmpStats {
  total:          number;
  active:         number;
  probation:      number;
  on_leave:       number;
  terminated:     number;
  new_this_month: number;
}

/**
 * Renders the employee list with a stats strip, search, filters,
 * and action buttons.
 *
 * @example
 * <app-employee-list></app-employee-list>
 */
@Component({
  standalone:      false,
  selector:        'app-employee-list',
  templateUrl:     './employee-list.component.html',
  styleUrls:       ['./employee-list.component.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class EmployeeListComponent implements OnInit, OnDestroy {

  /** Stream of employee arrays from the NgRx entity store. */
  employees$:  Observable<Employee[]>;

  /** True while an HTTP request is in flight. */
  loading$:    Observable<boolean>;

  /** Current pagination metadata. */
  pagination$: Observable<Pagination | null>;

  /** Filter controls */
  searchControl  = new FormControl<string>('');
  statusFilter   = new FormControl<EmployeeStatus | ''>('');
  deptFilter     = new FormControl<number | ''>('');
  typeFilter     = new FormControl<EmploymentType | ''>('');

  /** Department list for the filter dropdown. */
  departments: DepartmentRef[] = [];

  /** Workforce summary for the stat strip. */
  stats: EmpStats | null = null;
  statsLoading = true;

  /** Stat strip tile definitions (built after stats load). */
  statTiles: Array<{
    label:  string;
    value:  number;
    color:  string;
    icon:   string;
    status: string;        // value to set on statusFilter when clicked
  }> = [];

  /** Columns shown in the Material table. */
  readonly displayedColumns: string[] = [
    'avatar', 'employee_code', 'full_name', 'department',
    'employment_type', 'status', 'actions',
  ];

  private readonly destroy$ = new Subject<void>();

  constructor(
    private readonly store:  Store<AppState>,
    private readonly router: Router,
    private readonly http:   HttpClient,
    private readonly cdr:    ChangeDetectorRef,
  ) {
    this.employees$  = this.store.select((s) => selectAll(s.employees));
    this.loading$    = this.store.select((s) => s.employees.loading);
    this.pagination$ = this.store.select((s) => s.employees.pagination);
  }

  /** @inheritdoc */
  ngOnInit(): void {
    this.loadEmployees();
    this.loadStats();
    this.loadDepartments();

    // Re-fetch on search input after debounce
    this.searchControl.valueChanges.pipe(
      debounceTime(400),
      distinctUntilChanged(),
      takeUntil(this.destroy$),
    ).subscribe(() => this.loadEmployees());
  }

  // ── Stats strip ────────────────────────────────────────────────────────

  /** Fetch workforce summary from the dedicated stats endpoint. */
  loadStats(): void {
    this.statsLoading = true;
    this.http.get<EmpStats>('/api/v1/employees/stats')
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (s) => {
          this.stats = s;
          this.buildTiles(s);
          this.statsLoading = false;
          this.cdr.markForCheck();
        },
        error: () => {
          this.statsLoading = false;
          this.cdr.markForCheck();
        },
      });
  }

  /** Load department list for filter dropdown. */
  loadDepartments(): void {
    this.http.get<any>('/api/v1/departments')
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (r) => {
          this.departments = r?.data ?? r ?? [];
          this.cdr.markForCheck();
        },
        error: () => {},
      });
  }

  private buildTiles(s: EmpStats): void {
    this.statTiles = [
      { label: 'Total',          value: s.total,          color: '#3b82f6', icon: 'people',          status: '' },
      { label: 'Active',         value: s.active,          color: '#10b981', icon: 'how_to_reg',      status: 'active' },
      { label: 'Probation',      value: s.probation,       color: '#f59e0b', icon: 'hourglass_top',   status: 'probation' },
      { label: 'On Leave',       value: s.on_leave,        color: '#6366f1', icon: 'event_busy',      status: 'on_leave' },
      { label: 'Terminated',     value: s.terminated,      color: '#ef4444', icon: 'person_remove',   status: 'terminated' },
      { label: 'New This Month', value: s.new_this_month,  color: '#0ea5e9', icon: 'person_add_alt_1', status: '' },
    ];
  }

  /**
   * Click a stat tile to filter the table by that status.
   * Clicking "Total" or "New This Month" (no status filter) clears the filter.
   */
  filterByStatus(tile: typeof this.statTiles[0]): void {
    this.statusFilter.setValue(tile.status as EmployeeStatus | '');
    this.loadEmployees();
  }

  // ── Table ──────────────────────────────────────────────────────────────

  /**
   * Dispatch a load action with the current filter state.
   *
   * @param page  Page number (1-based); defaults to 1
   */
  loadEmployees(page = 1): void {
    this.store.dispatch(EmployeeActions.loadEmployees({
      params: {
        search:          this.searchControl.value  ?? '',
        status:          this.statusFilter.value   ?? '',
        department_id:   this.deptFilter.value     ?? '',
        employment_type: this.typeFilter.value      ?? '',
        page,
        per_page:        15,
      },
    }));
  }

  /** Reset all filters and reload the first page. */
  clearFilters(): void {
    this.searchControl.setValue('');
    this.statusFilter.setValue('');
    this.deptFilter.setValue('');
    this.typeFilter.setValue('');
    this.loadEmployees();
  }

  /** Navigate to the employee detail page. */
  viewEmployee(id: number): void {
    this.router.navigate(['/employees', id]);
  }

  /** Navigate to the employee edit form. */
  editEmployee(id: number): void {
    this.router.navigate(['/employees', id, 'edit']);
  }

  /** Navigate to the new-employee form. */
  addEmployee(): void {
    this.router.navigate(['/employees', 'new']);
  }

  /**
   * Confirm and dispatch a terminate action.
   *
   * @param id  Employee primary key
   */
  terminate(id: number): void {
    if (confirm('Terminate this employee? This action cannot be undone.')) {
      this.store.dispatch(EmployeeActions.deleteEmployee({ id }));
    }
  }

  // ── Helpers ───────────────────────────────────────────────────────────

  statusClass(status: EmployeeStatus | string): string {
    const map: Record<string, string> = {
      active:     'badge-green',
      on_leave:   'badge-yellow',
      probation:  'badge-blue',
      inactive:   'badge-gray',
      terminated: 'badge-red',
    };
    return map[status] ?? 'badge-gray';
  }

  typeClass(type: EmploymentType | string): string {
    const map: Record<string, string> = {
      full_time: 'badge-blue',
      part_time: 'badge-yellow',
      contract:  'badge-orange',
      intern:    'badge-purple',
    };
    return map[type] ?? 'badge-gray';
  }

  initial(name?: string | null): string {
    return name?.charAt(0)?.toUpperCase() ?? '?';
  }

  avatarColor(name?: string | null): string {
    const palette = [
      '#3b82f6', '#10b981', '#f59e0b', '#ef4444',
      '#6366f1', '#0ea5e9', '#f97316', '#a78bfa',
    ];
    return palette[(name?.charCodeAt(0) ?? 0) % palette.length];
  }

  /**
   * Quickly toggle an employee's status directly from the list row.
   * Stops row click propagation so it doesn't navigate to the detail page.
   *
   * @param employee  The employee row object (mutated in place on success)
   * @param status    New status value
   * @param event     MouseEvent — used to stop propagation
   */
  quickStatus(employee: any, status: string, event: MouseEvent): void {
    event.stopPropagation();
    if (!confirm(`Set employee "${employee.full_name}" to ${status.replace('_', ' ')}?`)) return;

    this.http.put<any>(`/api/v1/employees/${employee.id}`, { status })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (res) => {
          // Mutate in place so the row updates without a full store reload
          employee.status = res.employee?.status ?? status;
          this.cdr.markForCheck();
          // Reload stats strip to reflect the change
          this.loadStats();
        },
        error: () => {},
      });
  }

  /** @inheritdoc */
  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }
}
