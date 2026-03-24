/**
 * @fileoverview Employee list component.
 *
 * Displays a paginated, filterable table of employees. All state is read
 * from the NgRx store; mutations are dispatched as actions. No direct HTTP
 * calls are made from this component.
 *
 * @module employees/components/employee-list.component
 */

import {
  Component,
  OnInit,
  OnDestroy,
  ChangeDetectionStrategy,
} from '@angular/core';
import { Router } from '@angular/router';
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

/**
 * Renders the employee list with search, filters, and action buttons.
 *
 * @example
 * <app-employee-list></app-employee-list>
 */
@Component({
  standalone:   false,
  selector:     'app-employee-list',
  templateUrl:  './employee-list.component.html',
  styleUrls:    ['./employee-list.component.scss'],
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

  /** Angular Material table column order. */
  readonly displayedColumns: string[] = [
    'avatar', 'employee_code', 'full_name', 'department',
    'designation', 'employment_type', 'status', 'actions',
  ];

  private readonly destroy$ = new Subject<void>();

  constructor(
    private readonly store: Store<AppState>,
    private readonly router: Router,
  ) {
    this.employees$  = this.store.select((s) => selectAll(s.employees));
    this.loading$    = this.store.select((s) => s.employees.loading);
    this.pagination$ = this.store.select((s) => s.employees.pagination);
  }

  /** @inheritdoc */
  ngOnInit(): void {
    this.loadEmployees();

    // Re-fetch on search input after debounce
    this.searchControl.valueChanges.pipe(
      debounceTime(400),
      distinctUntilChanged(),
      takeUntil(this.destroy$),
    ).subscribe(() => this.loadEmployees());
  }

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

  /**
   * Map employee status to a CSS badge class.
   *
   * @param   status  Employee status string
   * @returns CSS class name for the badge
   */
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

  /**
   * Map employment type to a CSS badge class.
   *
   * @param   type  Employment type string
   * @returns CSS class name for the badge
   */
  typeClass(type: EmploymentType | string): string {
    const map: Record<string, string> = {
      full_time: 'badge-blue',
      part_time: 'badge-yellow',
      contract:  'badge-orange',
      intern:    'badge-purple',
    };
    return map[type] ?? 'badge-gray';
  }

  /**
   * Return the first character of a name for avatar fallback display.
   *
   * @param   name  Full name string
   * @returns Single uppercase character, or '?' if name is absent
   */
  initial(name?: string | null): string {
    return name?.charAt(0)?.toUpperCase() ?? '?';
  }

  /**
   * Deterministically pick a colour from a fixed palette based on the name.
   *
   * @param   name  Full name string used to seed the colour
   * @returns Hex colour string
   */
  avatarColor(name?: string | null): string {
    const palette = [
      '#3b82f6', '#10b981', '#f59e0b', '#ef4444',
      '#6366f1', '#0ea5e9', '#f97316', '#a78bfa',
    ];
    return palette[(name?.charCodeAt(0) ?? 0) % palette.length];
  }

  /** @inheritdoc */
  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }
}
