import { Injectable } from '@angular/core';
import { CanActivate, Router, ActivatedRouteSnapshot } from '@angular/router';
import { AuthService } from '../services/auth.service';

@Injectable({ providedIn: 'root' })
export class AuthGuard implements CanActivate {
  constructor(private auth: AuthService, private router: Router) {}
  canActivate(): boolean {
    if (this.auth.isLoggedIn()) return true;
    this.router.navigate(['/auth/login']);
    return false;
  }
}

@Injectable({ providedIn: 'root' })
export class RoleGuard implements CanActivate {
  constructor(private auth: AuthService, private router: Router) {}
  canActivate(route: ActivatedRouteSnapshot): boolean {
    if (!this.auth.isLoggedIn()) { this.router.navigate(['/auth/login']); return false; }
    const roles: string[] = route.data['roles'] || [];
    const perms: string[] = route.data['perms'] || [];
    if (!roles.length && !perms.length) return true;
    if (roles.length && this.auth.hasAnyRole(roles)) return true;
    if (perms.length && this.auth.canAny(perms)) return true;
    this.router.navigate(['/dashboard']);
    return false;
  }
}
