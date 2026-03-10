import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { AuthService } from '../../../core/services/auth.service';

@Component({
  standalone: false,
  selector: 'app-org-chart',
  templateUrl: './org-chart.component.html',
  styleUrls: ['./org-chart.component.scss'],
})
export class OrgChartComponent implements OnInit {
  loading      = true;
  statsLoading = true;

  // ── Views ─────────────────────────────────────────────────────────────
  activeView: 'tree' | 'grid' | 'list' = 'tree';

  // ── Data ─────────────────────────────────────────────────────────────
  chart:   any[]  = [];   // hierarchy tree
  stats:   any    = {};
  searchResults: any[] = [];
  searchQuery    = '';
  searching      = false;

  // ── Collapsed nodes (tree view) ───────────────────────────────────────
  collapsed: Set<number> = new Set();

  // ── Selected department detail ────────────────────────────────────────
  selectedDept: any    = null;
  deptLoading          = false;
  showDeptPanel        = false;

  // ── Department form ───────────────────────────────────────────────────
  showDeptForm         = false;
  deptEditId: number | null = null;
  deptForm: any        = { name:'', code:'', description:'', parent_id:'', manager_id:'', headcount_budget:0, is_active:true };
  deptFormError        = '';
  deptSaving           = false;
  allDepts:  any[]     = [];
  allEmployees: any[]  = [];

  // ── Flat dept list for grid/list ──────────────────────────────────────
  flatDepts: any[] = [];

  constructor(private http: HttpClient, public auth: AuthService) {}

  ngOnInit() {
    this.loadChart();
    this.loadStats();
    this.loadAllDepts();
    this.loadAllEmployees();
  }

  loadChart() {
    this.loading = true;
    this.http.get<any>('/api/v1/org-chart').subscribe({
      next: r => { this.chart = r?.chart || []; this.flatDepts = this.flatten(this.chart); this.loading = false; },
      error: () => this.loading = false
    });
  }

  loadStats() {
    this.statsLoading = true;
    this.http.get<any>('/api/v1/org-chart/stats').subscribe({
      next: r => { this.stats = r; this.statsLoading = false; },
      error: () => this.statsLoading = false
    });
  }

  loadAllDepts() {
    this.http.get<any>('/api/v1/departments').subscribe({ next: r => this.allDepts = r || [] });
  }

  loadAllEmployees() {
    this.http.get<any>('/api/v1/employees?per_page=500&status=active').subscribe({ next: r => this.allEmployees = r?.data || [] });
  }

  // ── Search ────────────────────────────────────────────────────────────
  onSearch() {
    if (!this.searchQuery.trim()) { this.searchResults = []; return; }
    this.searching = true;
    this.http.get<any>('/api/v1/org-chart/search', { params: { q: this.searchQuery } }).subscribe({
      next: r => { this.searchResults = r?.results || []; this.searching = false; },
      error: () => this.searching = false
    });
  }

  clearSearch() { this.searchQuery = ''; this.searchResults = []; }

  jumpToDept(deptId: number) {
    this.clearSearch();
    this.openDept(deptId);
    // Expand tree path to dept
    this.expandToNode(this.chart, deptId);
  }

  expandToNode(nodes: any[], targetId: number): boolean {
    for (const n of nodes) {
      if (n.id === targetId) return true;
      if (n.children?.length && this.expandToNode(n.children, targetId)) {
        this.collapsed.delete(n.id);
        return true;
      }
    }
    return false;
  }

  // ── Tree toggle ───────────────────────────────────────────────────────
  toggle(id: number) {
    if (this.collapsed.has(id)) this.collapsed.delete(id);
    else this.collapsed.add(id);
  }
  isCollapsed(id: number): boolean { return this.collapsed.has(id); }
  expandAll() { this.collapsed.clear(); }
  collapseAll() { this.flatten(this.chart).forEach(d => this.collapsed.add(d.id)); }

  // ── Department detail panel ───────────────────────────────────────────
  openDept(id: number) {
    this.showDeptPanel = true; this.deptLoading = true;
    this.http.get<any>(`/api/v1/org-chart/dept/${id}`).subscribe({
      next: r => { this.selectedDept = r.department; this.deptLoading = false; },
      error: () => this.deptLoading = false
    });
  }

  // ── Department form ───────────────────────────────────────────────────
  openDeptForm(dept?: any) {
    if (dept) {
      this.deptEditId = dept.id;
      this.deptForm = {
        name: dept.name, code: dept.code, description: dept.description || '',
        parent_id: dept.parent_id || '', manager_id: dept.manager_id || '',
        headcount_budget: dept.headcount_budget || 0, is_active: dept.is_active !== false
      };
    } else {
      this.deptEditId = null;
      this.deptForm = { name:'', code:'', description:'', parent_id:'', manager_id:'', headcount_budget:0, is_active:true };
    }
    this.deptFormError = ''; this.showDeptForm = true;
  }

  saveDept() {
    if (!this.deptForm.name || !this.deptForm.code) { this.deptFormError = 'Name and code are required.'; return; }
    this.deptSaving = true;
    const req = this.deptEditId
      ? this.http.put<any>(`/api/v1/org-chart/dept/${this.deptEditId}`, this.deptForm)
      : this.http.post<any>('/api/v1/org-chart/dept', this.deptForm);
    req.subscribe({
      next: () => { this.deptSaving = false; this.showDeptForm = false; this.loadChart(); this.loadStats(); this.loadAllDepts(); },
      error: err => { this.deptSaving = false; this.deptFormError = err?.error?.message || 'Failed to save.'; }
    });
  }

  // ── Helpers ───────────────────────────────────────────────────────────
  flatten(nodes: any[], depth = 0): any[] {
    const result: any[] = [];
    for (const n of nodes) {
      result.push({ ...n, depth });
      if (n.children?.length) result.push(...this.flatten(n.children, depth + 1));
    }
    return result;
  }

  deptColor(name: string): string {
    const c = ['#3b82f6','#6366f1','#8b5cf6','#ec4899','#10b981','#f59e0b','#ef4444','#0ea5e9'];
    return c[(name?.charCodeAt(0) || 0) % c.length];
  }

  avatarColor(name: string): string {
    const c = ['#3b82f6','#6366f1','#8b5cf6','#ec4899','#10b981','#f59e0b','#ef4444','#0ea5e9'];
    return c[(name?.charCodeAt(0) || 0) % c.length];
  }

  initials(name: string): string {
    return (name || '?').split(' ').map(w => w[0]).slice(0,2).join('').toUpperCase();
  }

  headcountPct(dept: any): number {
    if (!dept.headcount_budget) return 0;
    return Math.min(100, Math.round((dept.employees_count / dept.headcount_budget) * 100));
  }

  headcountColor(dept: any): string {
    const pct = this.headcountPct(dept);
    if (pct >= 100) return 'var(--danger)';
    if (pct >= 80)  return 'var(--warning)';
    return 'var(--success)';
  }

  canManage(): boolean { return this.auth.canAny(['employees.create','employees.edit']); }

  get totalByDept(): any[] { return this.stats?.departments || []; }
}
