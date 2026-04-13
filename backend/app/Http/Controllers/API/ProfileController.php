<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Handles the authenticated user's own profile.
 *
 * Routes:
 *   GET    /api/v1/profile           → show()
 *   PUT    /api/v1/profile           → update()
 *   POST   /api/v1/profile/avatar    → uploadAvatar()
 *   PUT    /api/v1/profile/password  → changePassword()
 */
class ProfileController extends Controller
{
    // ── Show ──────────────────────────────────────────────────────────────

    /**
     * Return the authenticated user's profile including linked employee data.
     */
    public function show(Request $request): JsonResponse
    {
        $user     = $request->user();
        $employee = Employee::with(['department', 'designation'])
            ->where('user_id', $user->id)
            ->first();

        return response()->json([
            'user' => [
                'id'          => $user->id,
                'name'        => $user->name,
                'email'       => $user->email,
                'roles'       => $user->getRoleNames()->values()->toArray(),
                'permissions' => $user->getAllPermissions()->pluck('name')->values()->toArray(),
                'created_at'  => $user->created_at,
            ],
            'employee' => $employee ? [
                'id'                      => $employee->id,
                'employee_code'           => $employee->employee_code,
                'first_name'              => $employee->first_name,
                'last_name'               => $employee->last_name,
                'arabic_name'             => $employee->arabic_name,
                'full_name'               => $employee->full_name,
                'email'                   => $employee->email,
                'phone'                   => $employee->phone,
                'work_phone'              => $employee->work_phone,
                'gender'                  => $employee->gender,
                'nationality'             => $employee->nationality,
                'hire_date'               => $employee->hire_date?->format('Y-m-d'),
                'employment_type'         => $employee->employment_type,
                'status'                  => $employee->status,
                'department'              => $employee->department?->name,
                'designation'             => $employee->designation?->title,
                'avatar_url'              => $employee->avatar_url,
                'bank_name'               => $employee->bank_name,
                'national_id'             => $employee->national_id,
            ] : null,
        ]);
    }

    // ── Update ────────────────────────────────────────────────────────────

    /**
     * Update the authenticated user's own editable profile fields.
     * Employees can only update: name, phone, arabic_name, address, city, country.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'name'         => 'sometimes|string|max:200',
            'phone'        => 'sometimes|nullable|string|max:20',
            'arabic_name'  => 'sometimes|nullable|string|max:200',
            'address'      => 'sometimes|nullable|string|max:500',
            'city'         => 'sometimes|nullable|string|max:100',
            'country'      => 'sometimes|nullable|string|max:100',
        ]);

        // Update User name
        if ($request->has('name')) {
            $user->update(['name' => $request->name]);
        }

        // Update linked Employee record
        $employee = Employee::where('user_id', $user->id)->first();
        if ($employee) {
            $employee->update($request->only([
                'phone', 'arabic_name', 'address', 'city', 'country',
            ]));

            // Keep user name in sync with employee name if name changed
            if ($request->has('name')) {
                $parts = explode(' ', $request->name, 2);
                $employee->update([
                    'first_name' => $parts[0],
                    'last_name'  => $parts[1] ?? $employee->last_name,
                ]);
            }
        }

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user'    => [
                'id'    => $user->id,
                'name'  => $user->fresh()->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames()->values()->toArray(),
            ],
        ]);
    }

    // ── Avatar ────────────────────────────────────────────────────────────

    /**
     * Upload a new avatar for the authenticated user's employee record.
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png,gif,webp|max:2048',
        ]);

        $user     = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (!$employee) {
            return response()->json(['message' => 'No employee record linked to this account.'], 404);
        }

        // Delete old avatar
        if ($employee->avatar && Storage::disk('public')->exists($employee->avatar)) {
            Storage::disk('public')->delete($employee->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $employee->update(['avatar' => $path]);

        return response()->json([
            'message'    => 'Avatar updated successfully.',
            'avatar_url' => asset('storage/' . $path),
        ]);
    }

    // ── Change Password ───────────────────────────────────────────────────

    /**
     * Change the authenticated user's password.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password'      => 'required|string',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update(['password' => Hash::make($request->password)]);

        // Revoke all other tokens so other sessions are logged out
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->json(['message' => 'Password changed successfully.']);
    }
}
