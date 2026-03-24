<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

/**
 * Seeds all roles and permissions for the HRMS application.
 *
 * Run with: php artisan db:seed --class=RolesPermissionsSeeder
 *
 * CHANGELOG
 * ---------
 * 2026-03-24  Added contracts.view and contracts.create permissions.
 *             Assigned to super_admin, hr_manager, hr_staff (manage),
 *             department_manager and employee (view own only).
 */
class RolesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ── Define all permissions ────────────────────────────────────────
        $permissions = [
            // Dashboard
            'dashboard.view',

            // Employees
            'employees.view',
            'employees.create',
            'employees.edit',
            'employees.delete',
            'employees.view_salary',
            'employees.view_documents',

            // Payroll
            'payroll.view',
            'payroll.run',
            'payroll.approve',
            'payroll.export',
            'payroll.view_own',

            // Leave
            'leave.view_all',
            'leave.approve',
            'leave.manage_types',
            'leave.manage_holidays',
            'leave.view_own',
            'leave.request',

            // Loans
            'loans.view_all',
            'loans.approve_manager',
            'loans.approve_hr',
            'loans.approve_finance',
            'loans.disburse',
            'loans.manage_types',
            'loans.view_own',
            'loans.request',

            // Contracts — FIX: was missing, caused nav item to be invisible
            'contracts.view',
            'contracts.create',
            'contracts.edit',
            'contracts.delete',
            'contracts.view_own',

            // Separations
            'separations.view_all',
            'separations.create',
            'separations.approve_manager',
            'separations.approve_hr',
            'separations.manage_offboarding',

            // Requests
            'requests.view_all',
            'requests.process',
            'requests.approve_manager',
            'requests.manage_types',
            'requests.view_own',
            'requests.submit',

            // Recruitment
            'recruitment.view',
            'recruitment.manage',

            // Performance
            'performance.view',
            'performance.manage',

            // Org Chart
            'orgchart.view',

            // Admin
            'admin.manage_users',
            'admin.manage_roles',
            'admin.view_logs',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // ── Define Roles & their permissions ─────────────────────────────
        $roles = [

            'super_admin' => $permissions,

            'hr_manager' => [
                'dashboard.view',
                'employees.view', 'employees.create', 'employees.edit',
                'employees.view_salary', 'employees.view_documents',
                'payroll.view', 'payroll.run', 'payroll.approve', 'payroll.export',
                'leave.view_all', 'leave.approve', 'leave.manage_types', 'leave.manage_holidays',
                'loans.view_all', 'loans.approve_hr', 'loans.manage_types',
                // Contracts: full access for HR managers
                'contracts.view', 'contracts.create', 'contracts.edit', 'contracts.delete',
                'separations.view_all', 'separations.create',
                'separations.approve_hr', 'separations.manage_offboarding',
                'requests.view_all', 'requests.process',
                'requests.approve_manager', 'requests.manage_types',
                'recruitment.view', 'recruitment.manage',
                'performance.view', 'performance.manage',
                'orgchart.view',
            ],

            'hr_staff' => [
                'dashboard.view',
                'employees.view', 'employees.create', 'employees.edit',
                'employees.view_documents',
                'payroll.view',
                'leave.view_all', 'leave.approve', 'leave.manage_holidays',
                'loans.view_all',
                // Contracts: HR staff can view and create, but not delete
                'contracts.view', 'contracts.create', 'contracts.edit',
                'separations.view_all', 'separations.create',
                'separations.manage_offboarding',
                'requests.view_all', 'requests.process',
                'recruitment.view',
                'performance.view',
                'orgchart.view',
            ],

            'finance_manager' => [
                'dashboard.view',
                'employees.view', 'employees.view_salary',
                'payroll.view', 'payroll.approve', 'payroll.export',
                'loans.view_all', 'loans.approve_finance', 'loans.disburse',
                // Contracts: Finance can view (for salary/benefit terms)
                'contracts.view',
                'separations.view_all',
                'requests.view_all',
                'orgchart.view',
            ],

            'department_manager' => [
                'dashboard.view',
                'employees.view', 'employees.view_documents',
                'leave.view_all', 'leave.approve', 'leave.view_own', 'leave.request',
                'loans.approve_manager', 'loans.view_own', 'loans.request',
                // Contracts: managers can view their team's contracts
                'contracts.view_own',
                'separations.view_all', 'separations.approve_manager',
                'requests.approve_manager', 'requests.view_own', 'requests.submit',
                'performance.view', 'performance.manage',
                'orgchart.view',
            ],

            'employee' => [
                'dashboard.view',
                'payroll.view_own',
                'leave.view_own', 'leave.request',
                'loans.view_own', 'loans.request',
                // Contracts: employees can view their own contract
                'contracts.view_own',
                'requests.view_own', 'requests.submit',
                'orgchart.view',
            ],
        ];

        foreach ($roles as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($perms);
        }

        // ── Ensure admin user has super_admin role ────────────────────────
        $admin = User::where('email', 'admin@hrms.com')->first();
        if ($admin && ! $admin->hasRole('super_admin')) {
            $admin->assignRole('super_admin');
        }

        // ── Assign employee role to any users that have no role ───────────
        User::where('email', '!=', 'admin@hrms.com')->each(function (User $user): void {
            if ($user->roles->isEmpty()) {
                $user->assignRole('employee');
            }
        });

        $this->command->info('✓ Roles & Permissions seeded (including contracts.*).');
    }
}
