<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * Handles all authentication actions: login, logout, password management.
 *
 * Rate limiting is applied at the route level in routes/api.php via
 * Laravel's built-in 'throttle' middleware, eliminating the need for
 * manual attempt counters here.
 *
 * @see routes/api.php
 */
class AuthController extends Controller
{
    /**
     * Authenticate a user and issue a Sanctum API token.
     *
     * @param  LoginRequest $request  Validated login credentials
     * @return JsonResponse           Token + user payload on success; 422 on failure
     *
     * @throws ValidationException When credentials are invalid
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user  = $request->user();
        $token = $user->createToken('hrms-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $this->userPayload($user),
        ]);
    }

    /**
     * Revoke the current access token and log out.
     *
     * @param  Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * Return the authenticated user's profile and permissions.
     *
     * @param  Request $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userPayload($request->user()),
        ]);
    }

    /**
     * Change the authenticated user's password.
     *
     * @param  Request $request
     * @return JsonResponse
     *
     * @throws ValidationException When the current password is incorrect
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($request->current_password, $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $request->user()->update([
            'password' => Hash::make($request->password),
        ]);

        // Revoke all other tokens to force re-login on other devices
        $request->user()
            ->tokens()
            ->where('id', '!=', $request->user()->currentAccessToken()->id)
            ->delete();

        return response()->json(['message' => 'Password changed successfully.']);
    }

    /**
     * Send a password-reset link to the given email address.
     *
     * @param  Request $request
     * @return JsonResponse
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email:rfc'],
        ]);

        $status = Password::sendResetLink($request->only('email'));

        return response()->json(['message' => __($status)]);
    }

    /**
     * Reset a user's password using the token emailed to them.
     *
     * @param  Request $request
     * @return JsonResponse
     *
     * @throws ValidationException When the token is invalid or expired
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token'    => ['required', 'string'],
            'email'    => ['required', 'email:rfc'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            static function (User $user, string $password): void {
                $user->forceFill(['password' => Hash::make($password)])->save();
                // Revoke all existing tokens to require fresh login everywhere
                $user->tokens()->delete();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['message' => 'Password has been reset successfully.']);
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }

    /**
     * Build the authenticated user payload returned on login and /me.
     *
     * @param  User $user
     * @return array<string,mixed>
     */
    protected function userPayload(User $user): array
    {
        $user->loadMissing('employee.department', 'roles');

        return [
            'id'          => $user->id,
            'name'        => $user->name,
            'email'       => $user->email,
            'roles'       => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'employee'    => $user->employee ? [
                'id'         => $user->employee->id,
                'code'       => $user->employee->employee_code,
                'full_name'  => $user->employee->full_name,
                'avatar_url' => $user->employee->avatar_url,
                'department' => $user->employee->department?->name,
            ] : null,
        ];
    }
}
