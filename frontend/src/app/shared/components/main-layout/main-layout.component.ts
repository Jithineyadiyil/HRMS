import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { AuthService, NavItem } from '../../../core/services/auth.service';
import { ThemeService, THEMES } from '../../../core/services/theme.service';

export interface NavGroup {
  label: string;
  items: NavItem[];
}

@Component({
  standalone: false,
  selector: 'app-main-layout',
  templateUrl: './main-layout.component.html',
  styleUrls: ['./main-layout.component.scss'],
})
export class MainLayoutComponent implements OnInit {
  sidebarOpen = true;
  navGroups: NavGroup[] = [];
  user: any = null;
  portalType = 'employee';

  portalLabels: Record<string, { label: string; icon: string; color: string }> = {
    admin:    { label: 'Admin Portal',    icon: 'shield',              color: '#ef4444' },
    hr:       { label: 'HR Portal',       icon: 'manage_accounts',     color: '#6366f1' },
    finance:  { label: 'Finance Portal',  icon: 'account_balance',     color: '#10b981' },
    manager:  { label: 'Manager Portal',  icon: 'supervisor_account',  color: '#f59e0b' },
    employee: { label: 'Employee Portal', icon: 'person',              color: '#3b82f6' },
  };

  constructor(
    public auth: AuthService,
    public themeService: ThemeService,
    private router: Router,
  ) {}

  ngOnInit(): void {
    this.user       = this.auth.getUser();
    this.portalType = this.auth.getPortalType();
    this.navGroups  = this.buildNavGroups(this.auth.getVisibleNavItems());
  }

  private buildNavGroups(items: NavItem[]): NavGroup[] {
    const groups: NavGroup[] = [];
    let current: NavGroup | null = null;

    for (const item of items) {
      if (item.group) {
        current = { label: item.group, items: [item] };
        groups.push(current);
      } else if (current) {
        current.items.push(item);
      } else {
        current = { label: '', items: [item] };
        groups.push(current);
      }
    }
    return groups;
  }

  get portalInfo() {
    return this.portalLabels[this.portalType] ?? this.portalLabels['employee'];
  }

  get userInitials(): string {
    return (this.user?.name || '')
      .split(' ')
      .map((w: string) => w[0])
      .slice(0, 2)
      .join('')
      .toUpperCase();
  }

  get roleLabel(): string {
    const map: Record<string, string> = {
      super_admin:        'Super Admin',
      hr_manager:         'HR Manager',
      hr_staff:           'HR Staff',
      finance_manager:    'Finance Manager',
      department_manager: 'Dept. Manager',
      employee:           'Employee',
    };
    return map[this.auth.getUserRole()] ?? this.auth.getUserRole();
  }

  setTheme(id: string): void {
    this.themeService.set(id);
  }

  /** Cycle through themes when sidebar is collapsed */
  cycleTheme(): void {
    const ids  = THEMES.map(t => t.id);
    const cur  = this.themeService.current();
    const next = ids[(ids.indexOf(cur) + 1) % ids.length];
    this.themeService.set(next);
  }

  logout(): void {
    this.auth.logout();
    this.router.navigate(['/auth/login']);
  }

  toggleSidebar(): void {
    this.sidebarOpen = !this.sidebarOpen;
  }
}
