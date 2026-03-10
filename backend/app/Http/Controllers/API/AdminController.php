<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Employee;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    // ══════════════════════════════════════════════════════════════════════
    // OVERVIEW / STATS
    // ══════════════════════════════════════════════════════════════════════
    public function overview()
    {
        return response()->json([
            'total_users'     => User::count(),
            'users_by_role'   => Role::withCount('users')->get()->map(fn($r) => [
                'role'  => $r->name,
                'label' => $this->roleLabel($r->name),
                'count' => $r->users_count,
                'color' => $this->roleColor($r->name),
            ]),
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

        return response()->json(['user' => $user->fresh('roles','employee')]);
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
        $roles = Role::with('permissions')->withCount('users')->get()->map(fn($r) => [
            'id'          => $r->id,
            'name'        => $r->name,
            'label'       => $this->roleLabel($r->name),
            'color'       => $this->roleColor($r->name),
            'icon'        => $this->roleIcon($r->name),
            'description' => $this->roleDescription($r->name),
            'users_count' => $r->users_count,
            'permissions' => $r->permissions->pluck('name'),
        ]);
        return response()->json(['roles' => $roles]);
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
        $perms = Permission::all()->groupBy(fn($p) => explode('.', $p->name)[0]);
        return response()->json(['permissions' => $perms]);
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
