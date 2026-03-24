import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { AuthService } from '../../../core/services/auth.service';

@Component({
  standalone: false,
  selector: 'app-admin',
  templateUrl: './admin.component.html',
  styleUrls: ['./admin.component.scss'],
})
export class AdminComponent implements OnInit {

  activeTab = 'overview';
  loading   = false;
  submitting = false;

  // ── Data ─────────────────────────────────────────────────────────────
  overview: any    = {};
  users: any[]     = [];
  roles: any[]     = [];
  permissions: any = {};
  pagination: any  = null;
  currentPage      = 1;
  employees: any[] = [];
  rolesLoading     = false;
  rolesError       = '';
  permsLoading     = false;

  // ── Filters ──────────────────────────────────────────────────────────
  filterRole   = '';
  filterSearch = '';

  // ── Panels ───────────────────────────────────────────────────────────
  showUserForm   = false;
  showRoleEditor = false;
  showUserDetail = false;
  selectedUser: any = null;
  selectedRole: any = null;
  formError = '';

  // ── User form ────────────────────────────────────────────────────────
  userForm: any = { name:'', email:'', password:'', role:'employee', employee_id:'' };
  userEditId: number | null = null;

  // ── Role editor (permissions) ─────────────────────────────────────────
  editablePerms: Set<string> = new Set();

  // ── Departments & Designations ────────────────────────────────────────
  departments:    any[] = [];
  designations:   any[] = [];
  deptSearch       = '';
  desigSearch      = '';

  showDeptForm     = false;
  showDesigForm    = false;
  deptEditId:      number | null = null;
  desigEditId:     number | null = null;
  deptForm: any    = { name:'', code:'', description:'', parent_id:'', manager_id:'', headcount_budget:'', is_active:true };
  desigForm: any   = { title:'', level:'', department_id:'', min_salary:'', max_salary:'', is_active:true };
  deptFormError    = '';
  desigFormError   = '';

  deptColumns      = ['name','code','manager','headcount','actions'];
  desigColumns     = ['title','level','department','salary','actions'];

  desigLevels = [
    { value:'junior',     label:'Junior'      },
    { value:'mid',        label:'Mid-Level'   },
    { value:'senior',     label:'Senior'      },
    { value:'lead',       label:'Lead'        },
    { value:'manager',    label:'Manager'     },
    { value:'director',   label:'Director'    },
    { value:'executive',  label:'Executive'   },
  ];

  // ── Table columns ─────────────────────────────────────────────────────
  userColumns  = ['user','role','employee','actions'];
  roleColumns  = ['role','users','description','actions'];

  tabs = [
    { id:'overview',      label:'Overview',    icon:'dashboard'           },
    { id:'users',         label:'Users',       icon:'people'              },
    { id:'roles',         label:'Roles',       icon:'security'            },
    { id:'permissions',   label:'Permissions', icon:'lock'                },
    { id:'departments',   label:'Departments', icon:'account_tree'        },
    { id:'designations',  label:'Positions',   icon:'work_outline'        },
  ];

  roleInfo: any = {
    super_admin:        { label:'Super Admin',        color:'#ef4444', icon:'shield'             },
    hr_manager:         { label:'HR Manager',         color:'#6366f1', icon:'manage_accounts'    },
    hr_staff:           { label:'HR Staff',           color:'#8b5cf6', icon:'badge'              },
    finance_manager:    { label:'Finance Manager',    color:'#10b981', icon:'account_balance'    },
    department_manager: { label:'Dept. Manager',      color:'#f59e0b', icon:'supervisor_account' },
    employee:           { label:'Employee',           color:'#3b82f6', icon:'person'             },
  };

  permModules = [
    { key:'dashboard',    label:'Dashboard',    icon:'dashboard'          },
    { key:'employees',    label:'Employees',    icon:'people'             },
    { key:'payroll',      label:'Payroll',      icon:'payments'           },
    { key:'leave',        label:'Leave',        icon:'event_available'    },
    { key:'loans',        label:'Loans',        icon:'account_balance'    },
    { key:'separations',  label:'Separations',  icon:'exit_to_app'        },
    { key:'requests',     label:'Requests',     icon:'inbox'              },
    { key:'recruitment',  label:'Recruitment',  icon:'work'               },
    { key:'performance',  label:'Performance',  icon:'leaderboard'        },
    { key:'orgchart',     label:'Org Chart',    icon:'account_tree'       },
    { key:'attendance',   label:'Attendance',   icon:'fingerprint'         },
    { key:'admin',        label:'Admin',        icon:'admin_panel_settings'},
  ];

  constructor(private http: HttpClient, public auth: AuthService) {}

  ngOnInit() {
    this.loadOverview();
    this.loadRoles();
    this.loadPermissions();
    this.loadEmployees();
    this.loadDepartments();
    this.loadDesignations();
  }

  loadOverview() {
    this.http.get<any>('/api/v1/admin/overview').subscribe({ next: r => this.overview = r });
  }

  loadUsers(page = 1) {
    this.loading = true; this.currentPage = page;
    const params: any = { page, per_page: 20 };
    if (this.filterRole)   params.role   = this.filterRole;
    if (this.filterSearch) params.search = this.filterSearch;
    this.http.get<any>('/api/v1/admin/users', { params }).subscribe({
      next: r => { this.users = r?.data || []; this.pagination = r; this.loading = false; },
      error: () => this.loading = false
    });
  }

  loadRoles() {
    this.rolesLoading = true;
    this.rolesError   = '';
    this.http.get<any>('/api/v1/admin/roles').subscribe({
      next: r => {
        // API returns { roles: [...] }. Normalise to plain array regardless of shape.
        const raw = r?.roles;
        this.roles        = Array.isArray(raw) ? raw : Object.values(raw || {});
        this.rolesLoading = false;
      },
      error: err => {
        this.rolesError   = err?.error?.message || `Failed to load roles (${err?.status ?? 'network error'})`;
        this.rolesLoading = false;
      },
    });
  }

  loadPermissions() {
    this.permsLoading = true;
    this.http.get<any>('/api/v1/admin/permissions').subscribe({
      next: r => { this.permissions = r?.permissions || {}; this.permsLoading = false; },
      error: ()  => { this.permsLoading = false; },
    });
  }

  loadEmployees() {
    this.http.get<any>('/api/v1/employees?per_page=500').subscribe({ next: r => this.employees = r?.data || [] });
  }

  switchTab(id: string) {
    this.activeTab = id;
    if (id === 'users')        this.loadUsers();
    if (id === 'roles')        this.loadRoles();          // always reload — retries if initial load failed
    if (id === 'permissions')  { this.loadPermissions(); this.loadRoles(); }
    if (id === 'departments')  this.loadDepartments();
    if (id === 'designations') this.loadDesignations();
  }

  // ── User CRUD ──────────────────────────────────────────────────────
  openUserForm(user?: any) {
    if (user) {
      this.userEditId = user.id;
      this.userForm = { name: user.name, email: user.email, password: '', role: user.roles?.[0]?.name || 'employee', employee_id: user.employee?.id || '' };
    } else {
      this.userEditId = null;
      this.userForm = { name:'', email:'', password:'', role:'employee', employee_id:'' };
    }
    this.formError = ''; this.showUserForm = true;
  }

  viewUser(user: any) {
    this.http.get<any>(`/api/v1/admin/users/${user.id}`).subscribe({ next: r => {
      this.selectedUser = r.user; this.showUserDetail = true;
    }});
  }

  saveUser() {
    if (!this.userForm.name || !this.userForm.email) { this.formError = 'Name and email required.'; return; }
    this.submitting = true; this.formError = '';
    const req = this.userEditId
      ? this.http.put<any>(`/api/v1/admin/users/${this.userEditId}`, this.userForm)
      : this.http.post<any>('/api/v1/admin/users', this.userForm);
    req.subscribe({
      next: r => {
        const userId = this.userEditId || r?.user?.id;
        if (userId) {
          // Ensure role is always synced regardless of create/edit path
          this.http.post(`/api/v1/admin/users/${userId}/assign-role`, { role: this.userForm.role })
            .subscribe({ error: () => {} });
        }
        this.submitting = false; this.showUserForm = false;
        this.loadUsers(this.currentPage); this.loadOverview(); this.loadRoles();
      },
      error: err => { this.submitting = false; this.formError = err?.error?.message || 'Failed.'; }
    });
  }

  quickAssignRole(userId: number, role: string) {
    this.http.post(`/api/v1/admin/users/${userId}/assign-role`, { role }).subscribe({
      next: () => this.loadUsers(this.currentPage)
    });
  }

  // ── Role permissions editor ────────────────────────────────────────
  openRoleEditor(role: any) {
    if (role.name === 'super_admin') return;
    this.selectedRole  = role;
    this.editablePerms = new Set(role.permissions);
    this.formError     = '';
    this.submitting    = false;
    this.showRoleEditor = true;
  }

  togglePerm(perm: string) {
    // Must reassign the Set reference — Angular change detection does NOT
    // track in-place Set mutations, so hasPerm() won't re-evaluate otherwise
    const s = new Set(this.editablePerms);
    if (s.has(perm)) s.delete(perm); else s.add(perm);
    this.editablePerms = s;
  }

  hasPerm(perm: string): boolean { return this.editablePerms.has(perm); }

  saveRolePermissions() {
    if (!this.selectedRole) return;
    this.submitting = true;
    this.formError  = '';
    this.http.put<any>(`/api/v1/admin/roles/${this.selectedRole.id}/permissions`, {
      permissions: Array.from(this.editablePerms),
    }).subscribe({
      next: () => {
        this.submitting    = false;
        this.showRoleEditor = false;
        this.loadRoles();
        this.loadPermissions(); // refresh permission matrix tab too
      },
      error: err => {
        this.submitting = false;
        this.formError  = err?.error?.message || 'Failed to save permissions. Please try again.';
      },
    });
  }

  // ── Permission matrix helpers ──────────────────────────────────────
  modulePerms(moduleKey: string): string[] {
    return (this.permissions[moduleKey] || []).map((p: any) => p.name);
  }

  roleHasPerm(role: any, perm: string): boolean {
    return role.permissions?.includes(perm);
  }

  permLabel(perm: string): string {
    return perm.split('.')[1]?.replace(/_/g,' ') || perm;
  }

  // ── Departments ────────────────────────────────────────────────────
  loadDepartments() {
    this.http.get<any>('/api/v1/departments').subscribe({
      next: r => { this.departments = Array.isArray(r) ? r : r?.data || []; }
    });
  }

  get filteredDepts(): any[] {
    if (!this.deptSearch) return this.departments;
    const s = this.deptSearch.toLowerCase();
    return this.departments.filter(d => d.name?.toLowerCase().includes(s) || d.code?.toLowerCase().includes(s));
  }

  openDeptForm(dept?: any) {
    if (dept) {
      this.deptEditId = dept.id;
      this.deptForm   = { name: dept.name, code: dept.code, description: dept.description || '',
                          parent_id: dept.parent_id || '', manager_id: dept.manager_id || '',
                          headcount_budget: dept.headcount_budget || '', is_active: dept.is_active !== false };
    } else {
      this.deptEditId = null;
      this.deptForm   = { name:'', code:'', description:'', parent_id:'', manager_id:'', headcount_budget:'', is_active:true };
    }
    this.deptFormError = ''; this.showDeptForm = true;
  }

  saveDept() {
    if (!this.deptForm.name || !this.deptForm.code) { this.deptFormError = 'Name and code are required.'; return; }
    this.submitting = true; this.deptFormError = '';
    const body = { ...this.deptForm, parent_id: this.deptForm.parent_id || null, manager_id: this.deptForm.manager_id || null };
    const req  = this.deptEditId
      ? this.http.put<any>(`/api/v1/departments/${this.deptEditId}`, body)
      : this.http.post<any>('/api/v1/departments', body);
    req.subscribe({
      next: () => { this.submitting = false; this.showDeptForm = false; this.loadDepartments(); this.loadOverview(); },
      error: err => { this.submitting = false; this.deptFormError = err?.error?.message || 'Save failed.'; }
    });
  }

  deleteDept(dept: any) {
    if (!confirm(`Delete department "${dept.name}"? This cannot be undone.`)) return;
    this.http.delete(`/api/v1/departments/${dept.id}`).subscribe({
      next: () => { this.loadDepartments(); this.loadOverview(); }
    });
  }

  // ── Designations / Positions ────────────────────────────────────────
  loadDesignations() {
    this.http.get<any>('/api/v1/designations').subscribe({
      next: r => { this.designations = Array.isArray(r) ? r : r?.data || []; }
    });
  }

  get filteredDesigs(): any[] {
    if (!this.desigSearch) return this.designations;
    const s = this.desigSearch.toLowerCase();
    return this.designations.filter(d => d.title?.toLowerCase().includes(s));
  }

  openDesigForm(desig?: any) {
    if (desig) {
      this.desigEditId = desig.id;
      this.desigForm   = { title: desig.title, level: desig.level || '', department_id: desig.department_id || '',
                           min_salary: desig.min_salary || '', max_salary: desig.max_salary || '', is_active: desig.is_active !== false };
    } else {
      this.desigEditId = null;
      this.desigForm   = { title:'', level:'', department_id:'', min_salary:'', max_salary:'', is_active:true };
    }
    this.desigFormError = ''; this.showDesigForm = true;
  }

  saveDesig() {
    if (!this.desigForm.title) { this.desigFormError = 'Position title is required.'; return; }
    this.submitting = true; this.desigFormError = '';
    const body = { ...this.desigForm, department_id: this.desigForm.department_id || null };
    const req  = this.desigEditId
      ? this.http.put<any>(`/api/v1/designations/${this.desigEditId}`, body)
      : this.http.post<any>('/api/v1/designations', body);
    req.subscribe({
      next: () => { this.submitting = false; this.showDesigForm = false; this.loadDesignations(); this.loadOverview(); },
      error: err => { this.submitting = false; this.desigFormError = err?.error?.message || 'Save failed.'; }
    });
  }

  deleteDesig(desig: any) {
    if (!confirm(`Delete position "${desig.title}"?`)) return;
    this.http.delete(`/api/v1/designations/${desig.id}`).subscribe({
      next: () => this.loadDesignations()
    });
  }

  // ── Helpers ────────────────────────────────────────────────────────
  get pages(): number[] {
    if (!this.pagination?.last_page) return [];
    return Array.from({ length: Math.min(this.pagination.last_page, 8) }, (_, i) => i + 1);
  }

  roleData(roleName: string): any {
    return this.roleInfo[roleName] || { label: roleName, color: '#8b949e', icon: 'person' };
  }

  avatarColor(name: string): string {
    const colors = ['#3b82f6','#6366f1','#8b5cf6','#ec4899','#10b981','#f59e0b','#ef4444','#0ea5e9'];
    return colors[(name?.charCodeAt(0) || 0) % colors.length];
  }

  overviewRoles(): any[] { return this.overview?.users_by_role || []; }

  /** Human-readable label for a designation level value. */
  levelLabel(level: string): string {
    return ({
      junior:    'Junior',
      mid:       'Mid-Level',
      senior:    'Senior',
      lead:      'Lead',
      manager:   'Manager',
      director:  'Director',
      executive: 'Executive',
      management:'Management',
      staff:     'Staff',
    } as Record<string, string>)[level] ?? level;
  }

  /** Format a salary number as abbreviated SAR string e.g. "SAR 5,000". */
  formatSalary(val: any): string {
    if (val == null || val === '') return '—';
    const n = parseFloat(val);
    if (isNaN(n)) return '—';
    return 'SAR ' + n.toLocaleString('en-SA', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
  }


  roleHasModule(role: any, moduleKey: string): boolean {
    return (role.permissions || []).some((p: string) => p.startsWith(moduleKey + '.'));
  }

  moduleColor(role: any, moduleKey: string): string {
    return this.roleHasModule(role, moduleKey) ? this.roleData(role.name).color : 'var(--text3)';
  }

  moduleBorder(role: any, moduleKey: string, alpha: string = '60'): string {
    return this.roleHasModule(role, moduleKey) ? this.roleData(role.name).color + alpha : 'transparent';
  }

  unlinkEmployee(userId: number) {
    // handled via employee update endpoint
  }
}
