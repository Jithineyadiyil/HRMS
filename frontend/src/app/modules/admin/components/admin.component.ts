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

  // ── Table columns ─────────────────────────────────────────────────────
  userColumns  = ['user','role','employee','actions'];
  roleColumns  = ['role','users','description','actions'];

  tabs = [
    { id:'overview',    label:'Overview',    icon:'dashboard'          },
    { id:'users',       label:'Users',       icon:'people'             },
    { id:'roles',       label:'Roles',       icon:'security'           },
    { id:'permissions', label:'Permissions', icon:'lock'               },
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
    { key:'admin',        label:'Admin',        icon:'admin_panel_settings'},
  ];

  constructor(private http: HttpClient, public auth: AuthService) {}

  ngOnInit() {
    this.loadOverview();
    this.loadRoles();
    this.loadPermissions();
    this.loadEmployees();
  }

  loadOverview() {
    this.http.get<any>('/api/v1/admin/overview').subscribe({
      next: r => {
        this.overview = {
          attention: [],         // ensure *ngFor never sees undefined
          users_by_role: [],
          attendance_today: {},
          ...r,
        };
      },
      error: err => {
        console.error('[Admin] overview failed:', err?.status, err?.error?.message ?? err?.message);
      },
    });
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
    this.http.get<any>('/api/v1/admin/roles').subscribe({ next: r => this.roles = r?.roles || [] });
  }

  loadPermissions() {
    this.http.get<any>('/api/v1/admin/permissions').subscribe({ next: r => this.permissions = r?.permissions || {} });
  }

  loadEmployees() {
    this.http.get<any>('/api/v1/employees?per_page=500').subscribe({ next: r => this.employees = r?.data || [] });
  }

  switchTab(id: string) {
    this.activeTab = id;
    if (id === 'users') this.loadUsers();
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
        // Also assign role if editing
        if (this.userEditId) {
          this.http.post(`/api/v1/admin/users/${this.userEditId}/assign-role`, { role: this.userForm.role }).subscribe();
        }
        this.submitting = false; this.showUserForm = false;
        this.loadUsers(this.currentPage); this.loadOverview();
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
    this.selectedRole = role;
    this.editablePerms = new Set(role.permissions);
    this.showRoleEditor = true;
  }

  togglePerm(perm: string) {
    if (this.editablePerms.has(perm)) this.editablePerms.delete(perm);
    else this.editablePerms.add(perm);
  }

  hasPerm(perm: string): boolean { return this.editablePerms.has(perm); }

  saveRolePermissions() {
    if (!this.selectedRole) return;
    this.http.put(`/api/v1/admin/roles/${this.selectedRole.id}/permissions`, {
      permissions: Array.from(this.editablePerms)
    }).subscribe({ next: () => { this.showRoleEditor = false; this.loadRoles(); }});
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
