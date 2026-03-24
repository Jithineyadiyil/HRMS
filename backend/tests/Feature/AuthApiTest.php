<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Feature tests for authentication endpoints (/api/v1/auth/*).
 *
 * @group auth
 */
class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    // ── login ────────────────────────────────────────────────────────────

    /** @test */
    public function user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email'    => 'hr@hrms.com',
            'password' => Hash::make('Secret@123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'hr@hrms.com',
            'password' => 'Secret@123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'roles', 'permissions']]);

        $this->assertNotEmpty($response->json('token'));
    }

    /** @test */
    public function login_returns_422_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'hr@hrms.com']);

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'hr@hrms.com',
            'password' => 'wrong-password',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function login_returns_422_when_email_missing(): void
    {
        $this->postJson('/api/v1/auth/login', ['password' => 'something'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function login_returns_422_when_password_too_short(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'a@b.com', 'password' => '123'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    // ── logout ───────────────────────────────────────────────────────────

    /** @test */
    public function authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully.');
    }

    /** @test */
    public function unauthenticated_user_cannot_access_logout(): void
    {
        $this->postJson('/api/v1/auth/logout')
            ->assertStatus(401);
    }

    // ── me ───────────────────────────────────────────────────────────────

    /** @test */
    public function me_returns_authenticated_user_profile(): void
    {
        $user = User::factory()->create(['name' => 'Ali Ahmed']);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.name', 'Ali Ahmed')
            ->assertJsonStructure(['user' => ['id', 'email', 'roles', 'permissions']]);
    }

    // ── changePassword ───────────────────────────────────────────────────

    /** @test */
    public function user_can_change_their_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPass@123')]);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/auth/password', [
            'current_password'      => 'OldPass@123',
            'password'              => 'NewPass@456',
            'password_confirmation' => 'NewPass@456',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPass@456', $user->fresh()->password));
    }

    /** @test */
    public function change_password_fails_when_current_password_is_wrong(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Correct@123')]);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/auth/password', [
            'current_password'      => 'Wrong@123',
            'password'              => 'New@12345',
            'password_confirmation' => 'New@12345',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['current_password']);
    }

    /** @test */
    public function change_password_fails_when_confirmation_mismatches(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Correct@123')]);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/auth/password', [
            'current_password'      => 'Correct@123',
            'password'              => 'New@12345',
            'password_confirmation' => 'Different@12345',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['password']);
    }

    // ── Rate limiting ────────────────────────────────────────────────────

    /** @test */
    public function login_is_rate_limited_after_ten_failures(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email'    => 'brute@test.com',
                'password' => 'wrong',
            ]);
        }

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'brute@test.com',
            'password' => 'wrong',
        ])->assertStatus(429); // Too Many Requests
    }

    /** @test */
    public function forgot_password_is_rate_limited_after_five_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/forgot-password', ['email' => 'user@test.com']);
        }

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'user@test.com'])
            ->assertStatus(429);
    }
}
