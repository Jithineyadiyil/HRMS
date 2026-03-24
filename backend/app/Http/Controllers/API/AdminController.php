<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Employee;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    // ══════════════════════════════════════════════════════════════════════
    // OVERVIEW / STATS
    // ══════════════════════════════════════════════════════════════════════
    public function overview()
    {
        return response()->json([
            'total_users'     => User::count(),
            'users_by_role'   => (function() {
                $counts = DB::table(config('permission.table_names.model_has_roles', 'model_has_roles'))
                    ->select('role_id', DB::raw('COUNT(*) as cnt'))
                    ->groupBy('role_id')
                    ->pluck('cnt', 'role_id');
                return Role::orderBy('id')->get()->map(fn($r) => [
                    'role'  => $r->name,
                    'label' => $this->roleLabel($r->name),
                    'count' => (int) ($counts[$r->id] ?? 0),
                    'color' => $this->roleColor($r->name),
                ])->values();
            })(),
            'total_roles'       => Role::count(),
            'total_permissions' => Permission::count(),
            'unassigned_users'  => User::doesntHave('roles')->count(),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // USERS
    // ══════════════════════════════════════════════════════════════════════
    public function users(Request $request)
    {
        $query = User::with(['roles','employee.department','employee.designation'])
            ->when($request->role,   fn($q) => $q->role($request->role))
            ->when($request->search, fn($q) =>
                $q->where('name','like',"%{$request->search}%")
                  ->orWhere('email','like',"%{$request->search}%")
            )
            ->orderBy('name');

        return response()->json($query->paginate(20));
    }

    public function showUser($id)
    {
        return response()->json([
            'user' => User::with(['roles','permissions','employee.department'])->findOrFail($id)
        ]);
    }

    public function storeUser(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:120',
            'email'    => 'required|email|unique:users',
            'password' => 'required|min:8',
            'role'     => 'required|exists:roles,name',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);
        $user->assignRole($request->role);

        // Link to employee if employee_id provided
        if ($request->employee_id) {
            Employee::where('id', $request->employee_id)->update(['user_id' => $user->id]);
        }

        return response()->json(['message' => 'User created.', 'user' => $user->load('roles','employee')], 201);
    }

    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $request->validate([
            'name'  => 'sometimes|string|max:120',
            'email' => "sometimes|email|unique:users,email,{$id}",
        ]);

        $user->update($request->only('name','email'));

        if ($request->password) {
            $user->update(['password' => Hash::make($request->password)]);
        }

        // Update employee link: unlink old, link new
        if ($request->has('employee_id')) {
            // Remove this user from any previously linked employee
            Employee::where('user_id', $user->id)->update(['user_id' => null]);
            // Link to new employee if provided
            if ($request->employee_id) {
                Employee::where('id', $request->employee_id)->update(['user_id' => $user->id]);
            }
        }

        return response()->json(['user' => $user->fresh('roles','employee.department')]);
    }

    public function assignRole(Request $request, $id)
    {
        $request->validate(['role' => 'required|exists:roles,name']);
        $user = User::findOrFail($id);
        $user->syncRoles([$request->role]);

        return response()->json(['message' => "Role '{$request->role}' assigned.", 'user' => $user->fresh('roles')]);
    }

    public function toggleUserStatus($id)
    {
        $user = User::findOrFail($id);
        // Use a soft "blocked" approach via a flag (add column if needed, else use token revocation)
        $user->tokens()->delete(); // revoke all tokens = effectively disabled
        return response()->json(['message' => 'User tokens revoked. User must re-login.']);
    }

    // ══════════════════════════════════════════════════════════════════════
    // ROLES
    // ══════════════════════════════════════════════════════════════════════
    public function roles()
    {
        try {
            // Always flush the Spatie permission cache before reading roles.
            // Stale cache from old serialized objects causes "Class name must be a valid
            // object or a string" — this one line prevents that entirely.
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

            // Load roles with permissions only.
            // withCount('users') triggers Role::users() → getModelForGuard(guard_name)
            // which returns null when guard_name doesn't match any provider in auth.php,
            // causing: "Class name must be a valid object or a string".
            // We count users manually via the pivot table to avoid this entirely.
            $roleUserCounts = DB::table(config('permission.table_names.model_has_roles', 'model_has_roles'))
                ->select('role_id', DB::raw('COUNT(*) as cnt'))
                ->groupBy('role_id')
                ->pluck('cnt', 'role_id');

            $roles = Role::with('permissions')
                ->orderBy('id')
                ->get()
                ->map(fn($r) => [
                    'id'          => $r->id,
                    'name'        => $r->name,
                    'label'       => $this->roleLabel($r->name),
                    'color'       => $this->roleColor($r->name),
                    'icon'        => $this->roleIcon($r->name),
                    'description' => $this->roleDescription($r->name),
                    'users_count' => (int) ($roleUserCounts[$r->id] ?? 0),
                    'permissions' => $r->permissions->pluck('name')->values()->all(),
                ])
                ->values();

            return response()->json(['roles' => $roles]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to load roles: ' . $e->getMessage(),
                'roles'   => [],
            ], 500);
        }
    }

    public function updateRolePermissions(Request $request, $id)
    {
        $role = Role::findOrFail($id);
        if ($role->name === 'super_admin') {
            return response()->json(['message' => 'Super admin permissions cannot be modified.'], 403);
        }
        $request->validate(['permissions' => 'required|array']);
        $role->syncPermissions($request->permissions);
        return response()->json(['message' => 'Permissions updated.', 'role' => $role->fresh('permissions')]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // PERMISSIONS
    // ══════════════════════════════════════════════════════════════════════
    public function permissions()
    {
        try {
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

            $perms = Permission::all()
                ->groupBy(fn($p) => explode('.', $p->name)[0])
                ->map(fn($group) => $group->map(fn($p) => [
                    'id'   => $p->id,
                    'name' => $p->name,
                ])->values()->all());

            return response()->json(['permissions' => $perms]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to load permissions: ' . $e->getMessage(), 'permissions' => []], 500);
        }
    }

    // ══════════════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════════════
    private function roleLabel(string $name): string {
        return [
            'super_admin'       => 'Super Admin',
            'hr_manager'        => 'HR Manager',
            'hr_staff'          => 'HR Staff',
            'finance_manager'   => 'Finance Manager',
            'department_manager'=> 'Department Manager',
            'employee'          => 'Employee',
        ][$name] ?? ucfirst(str_replace('_',' ',$name));
    }

    private function roleColor(string $name): string {
        return [
            'super_admin'       => '#ef4444',
            'hr_manager'        => '#6366f1',
            'hr_staff'          => '#8b5cf6',
            'finance_manager'   => '#10b981',
            'department_manager'=> '#f59e0b',
            'employee'          => '#3b82f6',
        ][$name] ?? '#8b949e';
    }

    private function roleIcon(string $name): string {
        return [
            'super_admin'       => 'shield',
            'hr_manager'        => 'manage_accounts',
            'hr_staff'          => 'badge',
            'finance_manager'   => 'account_balance',
            'department_manager'=> 'supervisor_account',
            'employee'          => 'person',
        ][$name] ?? 'person';
    }

    private function roleDescription(string $name): string {
        return [
            'super_admin'       => 'Full system access — all modules and admin tools',
            'hr_manager'        => 'Full HR operations — employees, payroll, leave, loans, separations',
            'hr_staff'          => 'Day-to-day HR processing — requests, leave, employee records',
            'finance_manager'   => 'Financial approvals — payroll, loan finance, final settlements',
            'department_manager'=> 'Team management — approve leave, loans, view team data',
            'employee'          => 'Self-service — requests, leave, payslips, loans',
        ][$name] ?? '';
    }
}
