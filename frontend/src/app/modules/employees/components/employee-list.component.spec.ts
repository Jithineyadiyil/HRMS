/**
 * @fileoverview Unit tests for EmployeeListComponent.
 *
 * Tests cover: initial load dispatch, filter debounce, navigate actions,
 * CSS class mapping helpers, and the terminate confirmation guard.
 *
 * @module employees/components/employee-list.component.spec
 */

import {
  ComponentFixture,
  TestBed,
  fakeAsync,
  tick,
} from '@angular/core/testing';
import { RouterTestingModule } from '@angular/router/testing';
import { ReactiveFormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { provideMockStore, MockStore } from '@ngrx/store/testing';
import { ToastrModule } from 'ngx-toastr';
import { EmployeeListComponent } from './employee-list.component';
import * as EmployeeActions from '../store/employee.actions';

describe('EmployeeListComponent', () => {
  let component: EmployeeListComponent;
  let fixture:   ComponentFixture<EmployeeListComponent>;
  let store:     MockStore;
  let router:    Router;

  const initialState = {
    employees: {
      ids:        [],
      entities:   {},
      loading:    false,
      pagination: null,
      filters:    {},
      error:      null,
    },
  };

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      declarations: [EmployeeListComponent],
      imports: [
        RouterTestingModule.withRoutes([]),
        ReactiveFormsModule,
        ToastrModule.forRoot(),
      ],
      providers: [
        provideMockStore({ initialState }),
      ],
    }).compileComponents();

    fixture   = TestBed.createComponent(EmployeeListComponent);
    component = fixture.componentInstance;
    store     = TestBed.inject(MockStore);
    router    = TestBed.inject(Router);

    jest.spyOn(store, 'dispatch');
    fixture.detectChanges();
  });

  // ── Initialisation ───────────────────────────────────────────────────────

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should dispatch loadEmployees on init', () => {
    expect(store.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({ type: '[Employees] Load' })
    );
  });

  // ── Filters ──────────────────────────────────────────────────────────────

  it('should dispatch loadEmployees after search debounce', fakeAsync(() => {
    (store.dispatch as jest.Mock).mockClear();

    component.searchControl.setValue('Ahmed');
    tick(400); // debounceTime

    expect(store.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({
        type:   '[Employees] Load',
        params: expect.objectContaining({ search: 'Ahmed' }),
      })
    );
  }));

  it('should not dispatch before debounce window elapses', fakeAsync(() => {
    (store.dispatch as jest.Mock).mockClear();

    component.searchControl.setValue('A');
    tick(200); // before debounce

    expect(store.dispatch).not.toHaveBeenCalled();
  }));

  it('clearFilters should reset all controls and dispatch', () => {
    component.searchControl.setValue('x');
    component.statusFilter.setValue('active');
    (store.dispatch as jest.Mock).mockClear();

    component.clearFilters();

    expect(component.searchControl.value).toBe('');
    expect(component.statusFilter.value).toBe('');
    expect(store.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({ type: '[Employees] Load' })
    );
  });

  // ── Navigation ───────────────────────────────────────────────────────────

  it('viewEmployee navigates to /employees/:id', () => {
    const spy = jest.spyOn(router, 'navigate');
    component.viewEmployee(42);
    expect(spy).toHaveBeenCalledWith(['/employees', 42]);
  });

  it('editEmployee navigates to /employees/:id/edit', () => {
    const spy = jest.spyOn(router, 'navigate');
    component.editEmployee(7);
    expect(spy).toHaveBeenCalledWith(['/employees', 7, 'edit']);
  });

  it('addEmployee navigates to /employees/new', () => {
    const spy = jest.spyOn(router, 'navigate');
    component.addEmployee();
    expect(spy).toHaveBeenCalledWith(['/employees', 'new']);
  });

  // ── terminate ────────────────────────────────────────────────────────────

  it('terminate dispatches deleteEmployee after confirm', () => {
    jest.spyOn(window, 'confirm').mockReturnValue(true);
    component.terminate(99);
    expect(store.dispatch).toHaveBeenCalledWith(
      EmployeeActions.deleteEmployee({ id: 99 })
    );
  });

  it('terminate does NOT dispatch when user cancels confirm', () => {
    jest.spyOn(window, 'confirm').mockReturnValue(false);
    (store.dispatch as jest.Mock).mockClear();
    component.terminate(99);
    expect(store.dispatch).not.toHaveBeenCalled();
  });

  // ── Helper methods ───────────────────────────────────────────────────────

  describe('statusClass', () => {
    it.each([
      ['active',     'badge-green'],
      ['on_leave',   'badge-yellow'],
      ['probation',  'badge-blue'],
      ['inactive',   'badge-gray'],
      ['terminated', 'badge-red'],
      ['unknown',    'badge-gray'],
    ])('maps %s → %s', (status, expected) => {
      expect(component.statusClass(status)).toBe(expected);
    });
  });

  describe('typeClass', () => {
    it.each([
      ['full_time', 'badge-blue'],
      ['part_time', 'badge-yellow'],
      ['contract',  'badge-orange'],
      ['intern',    'badge-purple'],
      ['unknown',   'badge-gray'],
    ])('maps %s → %s', (type, expected) => {
      expect(component.typeClass(type)).toBe(expected);
    });
  });

  describe('initial', () => {
    it('returns first char uppercased', () => {
      expect(component.initial('ahmed')).toBe('A');
    });
    it('returns ? for null', () => {
      expect(component.initial(null)).toBe('?');
    });
    it('returns ? for empty string', () => {
      expect(component.initial('')).toBe('?');
    });
  });

  describe('avatarColor', () => {
    it('returns a hex colour string', () => {
      const colour = component.avatarColor('Ahmed');
      expect(colour).toMatch(/^#[0-9a-f]{6}$/i);
    });

    it('returns deterministic colour for the same name', () => {
      expect(component.avatarColor('Ahmed')).toBe(component.avatarColor('Ahmed'));
    });

    it('handles null gracefully', () => {
      expect(() => component.avatarColor(null)).not.toThrow();
    });
  });

  // ── Teardown ─────────────────────────────────────────────────────────────

  it('should complete destroy$ on ngOnDestroy', () => {
    const spy = jest.spyOn((component as any).destroy$, 'next');
    component.ngOnDestroy();
    expect(spy).toHaveBeenCalled();
  });
});
