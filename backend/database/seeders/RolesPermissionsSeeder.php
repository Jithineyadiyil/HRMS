<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

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

            // Attendance
            'attendance.view_all',
            'attendance.manage',
            'attendance.view_own',
            'attendance.manual_entry',

            // Contracts
            'contracts.view_all',
            'contracts.create',
            'contracts.edit',
            'contracts.delete',
            'contracts.view_own',

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

        // ── Define Roles ──────────────────────────────────────────────────
        $roles = [

            'super_admin' => $permissions,   // all permissions

            'hr_manager' => [
                'dashboard.view',
                'employees.view','employees.create','employees.edit','employees.view_salary','employees.view_documents',
                'payroll.view','payroll.run','payroll.approve','payroll.export',
                'leave.view_all','leave.approve','leave.manage_types','leave.manage_holidays',
                'loans.view_all','loans.approve_hr','loans.manage_types',
                'separations.view_all','separations.create','separations.approve_hr','separations.manage_offboarding',
                'requests.view_all','requests.process',
                'attendance.view_all','attendance.manage','attendance.manual_entry',
                'contracts.view_all','contracts.create','requests.approve_manager','requests.manage_types',
                'recruitment.view','recruitment.manage',
                'performance.view','performance.manage',
                'attendance.view_all','attendance.manage','attendance.manual_entry',
                'contracts.view_all','contracts.create','contracts.edit','contracts.delete',
                'orgchart.view',
            ],

            'hr_staff' => [
                'dashboard.view',
                'employees.view','employees.create','employees.edit','employees.view_documents',
                'payroll.view',
                'leave.view_all','leave.approve','leave.manage_holidays',
                'loans.view_all',
                'separations.view_all','separations.create','separations.manage_offboarding',
                'requests.view_all','requests.process',
                'attendance.view_all','attendance.manage','attendance.manual_entry',
                'contracts.view_all','contracts.create',
                'recruitment.view',
                'performance.view',
                'orgchart.view',
            ],

            'finance_manager' => [
                'dashboard.view',
                'employees.view','employees.view_salary',
                'payroll.view','payroll.approve','payroll.export',
                'loans.view_all','loans.approve_finance','loans.disburse',
                'separations.view_all',
                'requests.view_all',
                'orgchart.view',
            ],

            'department_manager' => [
                'dashboard.view',
                'employees.view','employees.view_documents',
                'leave.view_all','leave.approve','leave.view_own','leave.request',
                'loans.approve_manager','loans.view_own','loans.request',
                'separations.view_all','separations.approve_manager',
                'requests.approve_manager','requests.view_own','requests.submit',
                'performance.view','performance.manage',
                'orgchart.view',
            ],

            'employee' => [
                'dashboard.view',
                'payroll.view_own',
                'leave.view_own','leave.request',
                'loans.view_own','loans.request',
                'requests.view_own','requests.submit',
                'attendance.view_own',
                'contracts.view_own',
                'orgchart.view',
            ],
        ];

        foreach ($roles as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($perms);
        }

        // ── Assign super_admin to first admin user ─────────────────────────
        $admin = User::where('email', 'admin@hrms.com')->first();
        if ($admin && !$admin->hasRole('super_admin')) {
            $admin->assignRole('super_admin');
        }

        // ── Assign employee role to all other users ────────────────────────
        User::where('email', '!=', 'admin@hrms.com')->each(function ($user) {
            if ($user->roles->isEmpty()) {
                $user->assignRole('employee');
            }
        });

        $this->command->info('✓ Roles & Permissions seeded successfully.');
    }
}
