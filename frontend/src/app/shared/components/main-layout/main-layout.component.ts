import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { AuthService, NavItem } from '../../../core/services/auth.service';

/** A section rendered in the sidebar: a label followed by its nav links. */
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

  constructor(public auth: AuthService, private router: Router) {}

  ngOnInit(): void {
    this.user       = this.auth.getUser();
    this.portalType = this.auth.getPortalType();
    this.navGroups  = this.buildNavGroups(this.auth.getVisibleNavItems());
  }

  /**
   * Converts the flat NavItem array (which carries optional `group` labels)
   * into an ordered array of NavGroup objects for the template.
   *
   * Items that share a `group` string are collected together. The first item
   * in each new group starts a new section. Items without a `group` property
   * are appended to whichever group is currently open.
   */
  private buildNavGroups(items: NavItem[]): NavGroup[] {
    const groups: NavGroup[] = [];
    let current: NavGroup | null = null;

    for (const item of items) {
      if (item.group) {
        // Start a new group section
        current = { label: item.group, items: [item] };
        groups.push(current);
      } else if (current) {
        // Continue the open section
        current.items.push(item);
      } else {
        // Edge case: no group label yet — create an unlabelled section
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
    const n = this.user?.name || '';
    return n.split(' ')
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

  logout(): void {
    this.auth.logout();
    this.router.navigate(['/auth/login']);
  }

  toggleSidebar(): void {
    this.sidebarOpen = !this.sidebarOpen;
  }
}
