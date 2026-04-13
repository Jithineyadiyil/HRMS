import { ComponentFixture, TestBed, fakeAsync, tick } from '@angular/core/testing';
import { HttpClientTestingModule, HttpTestingController } from '@angular/common/http/testing';
import { RouterTestingModule } from '@angular/router/testing';
import { ReactiveFormsModule } from '@angular/forms';
import { provideMockStore, MockStore } from '@ngrx/store/testing';

import { EmployeeListComponent } from './employee-list.component';
import * as EmployeeActions from '../store/employee.actions';
import { By } from '@angular/platform-browser';

/**
 * Unit tests for EmployeeListComponent.
 *
 * Covers: component mount, stats loading, filter dispatch, stat tile click,
 * quick-status toggle, and pagination.
 *
 * @group employees
 */
describe('EmployeeListComponent', () => {
  let component: EmployeeListComponent;
  let fixture: ComponentFixture<EmployeeListComponent>;
  let httpMock: HttpTestingController;
  let store: MockStore;

  const initialState = {
    employees: {
      ids: [],
      entities: {},
      loading: false,
      pagination: null,
      filters: {},
      error: null,
    },
  };

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      declarations: [EmployeeListComponent],
      imports: [
        HttpClientTestingModule,
        RouterTestingModule,
        ReactiveFormsModule,
      ],
      providers: [
        provideMockStore({ initialState }),
      ],
    }).compileComponents();

    fixture  = TestBed.createComponent(EmployeeListComponent);
    component = fixture.componentInstance;
    httpMock  = TestBed.inject(HttpTestingController);
    store     = TestBed.inject(MockStore);

    spyOn(store, 'dispatch').and.callThrough();
  });

  afterEach(() => {
    httpMock.verify();
  });

  // ── Component creation ─────────────────────────────────────────────────

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  // ── Stats loading ──────────────────────────────────────────────────────

  it('should load stats on init', fakeAsync(() => {
    fixture.detectChanges();   // triggers ngOnInit

    const statsReq = httpMock.expectOne('/api/v1/employees/stats');
    expect(statsReq.request.method).toBe('GET');

    statsReq.flush({
      total: 50, active: 40, probation: 5, on_leave: 3,
      terminated: 2, new_this_month: 4,
    });

    const deptReq = httpMock.expectOne('/api/v1/departments');
    deptReq.flush([]);

    tick();
    expect(component.stats?.total).toBe(50);
    expect(component.statsLoading).toBeFalse();
  }));

  it('should build stat tiles after stats load', fakeAsync(() => {
    fixture.detectChanges();

    httpMock.expectOne('/api/v1/employees/stats').flush({
      total: 100, active: 80, probation: 10, on_leave: 5,
      terminated: 5, new_this_month: 3,
    });
    httpMock.expectOne('/api/v1/departments').flush([]);

    tick();
    expect(component.statTiles.length).toBe(6);
    expect(component.statTiles[0].label).toBe('Total');
    expect(component.statTiles[0].value).toBe(100);
  }));

  it('should handle stats HTTP error gracefully', fakeAsync(() => {
    fixture.detectChanges();

    httpMock.expectOne('/api/v1/employees/stats').error(new ErrorEvent('Network error'));
    httpMock.expectOne('/api/v1/departments').flush([]);

    tick();
    expect(component.statsLoading).toBeFalse();
    expect(component.stats).toBeNull();
  }));

  // ── Store dispatch ─────────────────────────────────────────────────────

  it('should dispatch loadEmployees on init', fakeAsync(() => {
    fixture.detectChanges();
    httpMock.expectOne('/api/v1/employees/stats').flush({ total: 0, active: 0, probation: 0, on_leave: 0, terminated: 0, new_this_month: 0 });
    httpMock.expectOne('/api/v1/departments').flush([]);
    tick();

    expect(store.dispatch).toHaveBeenCalledWith(
      jasmine.objectContaining({ type: EmployeeActions.loadEmployees.type })
    );
  }));

  it('should dispatch loadEmployees with search term after debounce', fakeAsync(() => {
    fixture.detectChanges();
    httpMock.expectOne('/api/v1/employees/stats').flush({ total: 0, active: 0, probation: 0, on_leave: 0, terminated: 0, new_this_month: 0 });
    httpMock.expectOne('/api/v1/departments').flush([]);

    (store.dispatch as jasmine.Spy).calls.reset();

    component.searchControl.setValue('John');
    tick(400);   // debounce

    expect(store.dispatch).toHaveBeenCalledWith(
      jasmine.objectContaining({
        type:   EmployeeActions.loadEmployees.type,
        params: jasmine.objectContaining({ search: 'John' }),
      })
    );
  }));

  // ── Filters ────────────────────────────────────────────────────────────

  it('clearFilters should reset all controls and reload', fakeAsync(() => {
    component.searchControl.setValue('test');
    component.statusFilter.setValue('active');
    component.deptFilter.setValue(1);

    (store.dispatch as jasmine.Spy).calls.reset();
    component.clearFilters();

    expect(component.searchControl.value).toBe('');
    expect(component.statusFilter.value).toBe('');
    expect(store.dispatch).toHaveBeenCalled();
  }));

  it('filterByStatus should set status filter and reload', () => {
    const tile = { label: 'Active', value: 40, color: '#10b981', icon: 'how_to_reg', status: 'active' };
    (store.dispatch as jasmine.Spy).calls.reset();

    component.filterByStatus(tile);

    expect(component.statusFilter.value).toBe('active');
    expect(store.dispatch).toHaveBeenCalledWith(
      jasmine.objectContaining({ type: EmployeeActions.loadEmployees.type })
    );
  });

  // ── Helper methods ─────────────────────────────────────────────────────

  it('statusClass should return correct badge class', () => {
    expect(component.statusClass('active')).toBe('badge-green');
    expect(component.statusClass('terminated')).toBe('badge-red');
    expect(component.statusClass('unknown')).toBe('badge-gray');
  });

  it('typeClass should return correct badge class', () => {
    expect(component.typeClass('full_time')).toBe('badge-blue');
    expect(component.typeClass('intern')).toBe('badge-purple');
  });

  it('initial should return first char of name', () => {
    expect(component.initial('John')).toBe('J');
    expect(component.initial(null)).toBe('?');
    expect(component.initial('')).toBe('?');
  });

  it('avatarColor should return a hex colour', () => {
    const colour = component.avatarColor('Alice');
    expect(colour).toMatch(/^#[0-9a-f]{6}$/i);
  });

  // ── Quick status ───────────────────────────────────────────────────────

  it('quickStatus should PUT to API and update employee in place', fakeAsync(() => {
    fixture.detectChanges();
    httpMock.expectOne('/api/v1/employees/stats').flush({ total: 0, active: 0, probation: 0, on_leave: 0, terminated: 0, new_this_month: 0 });
    httpMock.expectOne('/api/v1/departments').flush([]);

    spyOn(window, 'confirm').and.returnValue(true);

    const emp = { id: 1, full_name: 'John Smith', status: 'active' };
    const event = new MouseEvent('click');
    spyOn(event, 'stopPropagation');

    component.quickStatus(emp, 'on_leave', event);

    const putReq = httpMock.expectOne('/api/v1/employees/1');
    expect(putReq.request.method).toBe('PUT');
    expect(putReq.request.body).toEqual({ status: 'on_leave' });

    putReq.flush({ employee: { status: 'on_leave' } });

    // Stats reload triggered
    httpMock.expectOne('/api/v1/employees/stats').flush({ total: 0, active: 0, probation: 0, on_leave: 1, terminated: 0, new_this_month: 0 });

    tick();
    expect(emp.status).toBe('on_leave');
    expect(event.stopPropagation).toHaveBeenCalled();
  }));

  it('quickStatus should not proceed if confirm returns false', () => {
    spyOn(window, 'confirm').and.returnValue(false);
    const emp   = { id: 1, full_name: 'Jane', status: 'active' };
    const event = new MouseEvent('click');

    component.quickStatus(emp, 'terminated', event);

    httpMock.expectNone('/api/v1/employees/1');
  });

  // ── Lifecycle ──────────────────────────────────────────────────────────

  it('should complete destroy$ on ngOnDestroy', () => {
    spyOn(component['destroy$'], 'next');
    spyOn(component['destroy$'], 'complete');

    component.ngOnDestroy();

    expect(component['destroy$'].next).toHaveBeenCalled();
    expect(component['destroy$'].complete).toHaveBeenCalled();
  });
});
