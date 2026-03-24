<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller {

    public function login(Request $request) {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
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

    public function logout(Request $request) {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function me(Request $request) {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    public function changePassword(Request $request) {
        $request->validate([
            'current_password' => 'required',
            'password'         => 'required|min:8|confirmed',
        ]);

        if (!Hash::check($request->current_password, $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => ['Current password is incorrect.']]);
        }

        $request->user()->update(['password' => Hash::make($request->password)]);
        return response()->json(['message' => 'Password changed successfully']);
    }

    public function forgotPassword(Request $request) {
        $request->validate(['email' => 'required|email']);
        $status = Password::sendResetLink($request->only('email'));
        return response()->json(['message' => __($status)]);
    }

    public function resetPassword(Request $request) {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['message' => 'Password has been reset successfully.']);
        }

        throw ValidationException::withMessages(['email' => [__($status)]]);
    }

    protected function userPayload(User $user) {
        $user->load('employee.department', 'roles');
        return [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'roles'      => $user->getRoleNames()->values()->toArray(),  // plain array, not Collection
            'permissions'=> $user->getAllPermissions()->pluck('name')->values()->toArray(),
            'employee'   => $user->employee ? [
                'id'          => $user->employee->id,
                'code'        => $user->employee->employee_code,
                'full_name'   => $user->employee->full_name,
                'avatar_url'  => $user->employee->avatar_url,
                'department'  => optional($user->employee->department)->name,
            ] : null,
        ];
    }
}
