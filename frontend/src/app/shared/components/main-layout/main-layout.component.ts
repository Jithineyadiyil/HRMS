import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { AuthService, NavItem } from '../../../core/services/auth.service';

@Component({
  standalone: false,
  selector: 'app-main-layout',
  templateUrl: './main-layout.component.html',
  styleUrls: ['./main-layout.component.scss'],
})
export class MainLayoutComponent implements OnInit {
  sidebarOpen = true;
  navItems: NavItem[] = [];
  user: any = null;
  portalType = 'employee';
  portalLabels: any = {
    admin:    { label: 'Admin Portal',    icon: 'shield',           color: '#ef4444' },
    hr:       { label: 'HR Portal',       icon: 'manage_accounts',  color: '#6366f1' },
    finance:  { label: 'Finance Portal',  icon: 'account_balance',  color: '#10b981' },
    manager:  { label: 'Manager Portal',  icon: 'supervisor_account', color: '#f59e0b' },
    employee: { label: 'Employee Portal', icon: 'person',           color: '#3b82f6' },
  };

  constructor(public auth: AuthService, private router: Router) {}

  ngOnInit() {
    this.user       = this.auth.getUser();
    this.portalType = this.auth.getPortalType();
    this.navItems   = this.auth.getVisibleNavItems();
  }

  get portalInfo() { return this.portalLabels[this.portalType] || this.portalLabels['employee']; }

  get userInitials(): string {
    const n = this.user?.name || '';
    return n.split(' ').map((w: string) => w[0]).slice(0,2).join('').toUpperCase();
  }

  get roleLabel(): string {
    const map: any = {
      super_admin: 'Super Admin', hr_manager: 'HR Manager', hr_staff: 'HR Staff',
      finance_manager: 'Finance Manager', department_manager: 'Dept. Manager', employee: 'Employee'
    };
    return map[this.auth.getUserRole()] || this.auth.getUserRole();
  }

  logout() { this.auth.logout(); this.router.navigate(['/auth/login']); }
  toggleSidebar() { this.sidebarOpen = !this.sidebarOpen; }
}
