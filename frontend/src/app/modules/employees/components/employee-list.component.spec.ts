/**
 * @fileoverview Unit tests for EmployeeListComponent.
 * Test runner: Karma + Jasmine (configured in tsconfig.spec.json).
 * Zero Jest APIs — pure Jasmine spyOn / jasmine.Spy / jasmine.objectContaining.
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
      ids: [] as number[], entities: {} as Record<number, unknown>,
      loading: false, pagination: null, filters: {}, error: null,
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
      providers: [provideMockStore({ initialState })],
    }).compileComponents();

    fixture   = TestBed.createComponent(EmployeeListComponent);
    component = fixture.componentInstance;
    store     = TestBed.inject(MockStore);
    router    = TestBed.inject(Router);

    spyOn(store, 'dispatch').and.callThrough();
    fixture.detectChanges();
  });

  it('should create', () => { expect(component).toBeTruthy(); });

  it('dispatches loadEmployees on init', () => {
    expect(store.dispatch).toHaveBeenCalledWith(
      jasmine.objectContaining({ type: '[Employees] Load' })
    );
  });

  it('dispatches loadEmployees after 400 ms search debounce', fakeAsync(() => {
    (store.dispatch as jasmine.Spy).calls.reset();
    component.searchControl.setValue('Ahmed');
    tick(400);
    expect(store.dispatch).toHaveBeenCalledWith(
      jasmine.objectContaining({ type: '[Employees] Load' })
    );
  }));

  it('does NOT dispatch before debounce window elapses', fakeAsync(() => {
    (store.dispatch as jasmine.Spy).calls.reset();
    component.searchControl.setValue('A');
    tick(200);
    expect(store.dispatch).not.toHaveBeenCalled();
    tick(200);
  }));

  it('clearFilters() resets controls and dispatches load', () => {
    component.searchControl.setValue('x');
    component.statusFilter.setValue('active');
    (store.dispatch as jasmine.Spy).calls.reset();
    component.clearFilters();
    expect(component.searchControl.value).toBe('');
    expect(component.statusFilter.value).toBe('');
    expect(store.dispatch).toHaveBeenCalledWith(
      jasmine.objectContaining({ type: '[Employees] Load' })
    );
  });

  it('viewEmployee() navigates to /employees/:id', () => {
    spyOn(router, 'navigate');
    component.viewEmployee(42);
    expect(router.navigate).toHaveBeenCalledWith(['/employees', 42]);
  });

  it('editEmployee() navigates to /employees/:id/edit', () => {
    spyOn(router, 'navigate');
    component.editEmployee(7);
    expect(router.navigate).toHaveBeenCalledWith(['/employees', 7, 'edit']);
  });

  it('addEmployee() navigates to /employees/new', () => {
    spyOn(router, 'navigate');
    component.addEmployee();
    expect(router.navigate).toHaveBeenCalledWith(['/employees', 'new']);
  });

  it('terminate() dispatches deleteEmployee when user confirms', () => {
    spyOn(window, 'confirm').and.returnValue(true);
    component.terminate(99);
    expect(store.dispatch).toHaveBeenCalledWith(
      EmployeeActions.deleteEmployee({ id: 99 })
    );
  });

  it('terminate() does NOT dispatch when user cancels', () => {
    spyOn(window, 'confirm').and.returnValue(false);
    (store.dispatch as jasmine.Spy).calls.reset();
    component.terminate(99);
    expect(store.dispatch).not.toHaveBeenCalled();
  });

  describe('statusClass()', () => {
    it('maps active     → badge-green',  () => expect(component.statusClass('active')).toBe('badge-green'));
    it('maps on_leave   → badge-yellow', () => expect(component.statusClass('on_leave')).toBe('badge-yellow'));
    it('maps probation  → badge-blue',   () => expect(component.statusClass('probation')).toBe('badge-blue'));
    it('maps inactive   → badge-gray',   () => expect(component.statusClass('inactive')).toBe('badge-gray'));
    it('maps terminated → badge-red',    () => expect(component.statusClass('terminated')).toBe('badge-red'));
    it('maps unknown    → badge-gray',   () => expect(component.statusClass('unknown')).toBe('badge-gray'));
  });

  describe('typeClass()', () => {
    it('maps full_time → badge-blue',   () => expect(component.typeClass('full_time')).toBe('badge-blue'));
    it('maps part_time → badge-yellow', () => expect(component.typeClass('part_time')).toBe('badge-yellow'));
    it('maps contract  → badge-orange', () => expect(component.typeClass('contract')).toBe('badge-orange'));
    it('maps intern    → badge-purple', () => expect(component.typeClass('intern')).toBe('badge-purple'));
    it('maps unknown   → badge-gray',   () => expect(component.typeClass('unknown')).toBe('badge-gray'));
  });

  describe('initial()', () => {
    it('returns first char uppercased',  () => expect(component.initial('ahmed')).toBe('A'));
    it('returns ? for null',             () => expect(component.initial(null)).toBe('?'));
    it('returns ? for empty string',     () => expect(component.initial('')).toBe('?'));
    it('returns ? for undefined',        () => expect(component.initial(undefined)).toBe('?'));
  });

  describe('avatarColor()', () => {
    it('returns a hex colour',              () => expect(component.avatarColor('Ahmed')).toMatch(/^#[0-9a-fA-F]{6}$/));
    it('is deterministic for same name',    () => expect(component.avatarColor('Ahmed')).toBe(component.avatarColor('Ahmed')));
    it('does not throw for null',           () => expect(() => component.avatarColor(null)).not.toThrow());
  });

  it('ngOnDestroy() calls next() and complete() on destroy$', () => {
    const subject = (component as any).destroy$;
    spyOn(subject, 'next').and.callThrough();
    spyOn(subject, 'complete').and.callThrough();
    component.ngOnDestroy();
    expect(subject.next).toHaveBeenCalled();
    expect(subject.complete).toHaveBeenCalled();
  });
});
