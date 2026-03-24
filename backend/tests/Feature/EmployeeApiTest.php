<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature tests for the Employee API resource (/api/v1/employees).
 *
 * Each test covers the full HTTP lifecycle: request → middleware →
 * controller → service → repository → response.
 *
 * @group employees
 */
class EmployeeApiTest extends TestCase
{
    use RefreshDatabase;

    private User $hrManager;
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed required roles
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'hr_manager',  'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'employee',    'guard_name' => 'web']);

        $this->hrManager = User::factory()->create();
        $this->hrManager->assignRole('hr_manager');

        $this->employee = User::factory()->create();
        $this->employee->assignRole('employee');
    }

    // ── index ────────────────────────────────────────────────────────────

    /** @test */
    public function unauthenticated_users_cannot_list_employees(): void
    {
        $this->getJson('/api/v1/employees')
            ->assertStatus(401);
    }

    /** @test */
    public function hr_manager_can_list_employees_with_pagination(): void
    {
        Employee::factory(5)->create();

        Sanctum::actingAs($this->hrManager);

        $response = $this->getJson('/api/v1/employees');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'employee_code', 'first_name', 'last_name', 'email', 'status']],
                'meta' => ['total', 'per_page', 'current_page'],
            ]);
    }

    /** @test */
    public function employees_list_can_be_filtered_by_status(): void
    {
        Employee::factory(3)->create(['status' => 'active']);
        Employee::factory(2)->create(['status' => 'terminated']);

        Sanctum::actingAs($this->hrManager);

        $response = $this->getJson('/api/v1/employees?status=active');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    // ── store ────────────────────────────────────────────────────────────

    /** @test */
    public function hr_manager_can_create_employee(): void
    {
        $dept = Department::factory()->create();
        Sanctum::actingAs($this->hrManager);

        $response = $this->postJson('/api/v1/employees', [
            'first_name'      => 'Ahmed',
            'last_name'       => 'Hassan',
            'email'           => 'ahmed.hassan@example.com',
            'hire_date'       => now()->toDateString(),
            'employment_type' => 'full_time',
            'salary'          => 10000,
            'department_id'   => $dept->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('employee.email', 'ahmed.hassan@example.com')
            ->assertJsonStructure(['employee', 'temp_password'])
            ->assertJsonMissing(['temp_password' => 'Password@123']); // never hardcoded

        $this->assertDatabaseHas('employees', ['email' => 'ahmed.hassan@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'ahmed.hassan@example.com']);
    }

    /** @test */
    public function temp_password_is_random_and_meets_minimum_length(): void
    {
        Sanctum::actingAs($this->hrManager);

        $r1 = $this->postJson('/api/v1/employees', $this->validEmployeePayload('emp1@test.com'));
        $r2 = $this->postJson('/api/v1/employees', $this->validEmployeePayload('emp2@test.com'));

        $pw1 = $r1->json('temp_password');
        $pw2 = $r2->json('temp_password');

        $this->assertNotEquals($pw1, $pw2, 'Each employee should receive a unique temp password');
        $this->assertGreaterThanOrEqual(12, strlen($pw1), 'Temp password must be at least 12 chars');
    }

    /** @test */
    public function creating_employee_generates_unique_employee_code(): void
    {
        Sanctum::actingAs($this->hrManager);

        $this->postJson('/api/v1/employees', $this->validEmployeePayload('a@test.com'))->assertStatus(201);
        $this->postJson('/api/v1/employees', $this->validEmployeePayload('b@test.com'))->assertStatus(201);

        $codes = Employee::pluck('employee_code')->sort()->values();
        $this->assertCount(2, $codes->unique());
        $this->assertStringStartsWith('EMP', $codes[0]);
    }

    /** @test */
    public function store_returns_422_for_duplicate_email(): void
    {
        Employee::factory()->create(['email' => 'exists@test.com']);
        Sanctum::actingAs($this->hrManager);

        $this->postJson('/api/v1/employees', $this->validEmployeePayload('exists@test.com'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function store_returns_422_when_required_fields_missing(): void
    {
        Sanctum::actingAs($this->hrManager);

        $this->postJson('/api/v1/employees', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'last_name', 'email', 'hire_date', 'employment_type', 'salary']);
    }

    /** @test */
    public function regular_employee_cannot_create_other_employees(): void
    {
        Sanctum::actingAs($this->employee);

        $this->postJson('/api/v1/employees', $this->validEmployeePayload('new@test.com'))
            ->assertStatus(403);
    }

    // ── show ─────────────────────────────────────────────────────────────

    /** @test */
    public function hr_manager_can_view_employee_detail(): void
    {
        $emp = Employee::factory()->create();
        Sanctum::actingAs($this->hrManager);

        $this->getJson("/api/v1/employees/{$emp->id}")
            ->assertOk()
            ->assertJsonPath('employee.id', $emp->id)
            ->assertJsonStructure(['employee' => ['id', 'employee_code', 'department', 'designation']]);
    }

    /** @test */
    public function requesting_non_existent_employee_returns_404(): void
    {
        Sanctum::actingAs($this->hrManager);

        $this->getJson('/api/v1/employees/99999')
            ->assertStatus(404);
    }

    // ── update ───────────────────────────────────────────────────────────

    /** @test */
    public function hr_manager_can_update_employee(): void
    {
        $emp = Employee::factory()->create(['first_name' => 'OldName']);
        Sanctum::actingAs($this->hrManager);

        $this->putJson("/api/v1/employees/{$emp->id}", ['first_name' => 'NewName'])
            ->assertOk()
            ->assertJsonPath('employee.first_name', 'NewName');
    }

    /** @test */
    public function update_ignores_immutable_employee_code_field(): void
    {
        $emp = Employee::factory()->create(['employee_code' => 'EMP0001']);
        Sanctum::actingAs($this->hrManager);

        $this->putJson("/api/v1/employees/{$emp->id}", ['employee_code' => 'HACKED'])
            ->assertOk();

        $this->assertDatabaseHas('employees', ['id' => $emp->id, 'employee_code' => 'EMP0001']);
    }

    // ── destroy ──────────────────────────────────────────────────────────

    /** @test */
    public function hr_manager_can_terminate_employee(): void
    {
        $emp = Employee::factory()->create(['status' => 'active']);
        Sanctum::actingAs($this->hrManager);

        $this->deleteJson("/api/v1/employees/{$emp->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Employee terminated and archived.');

        $this->assertSoftDeleted('employees', ['id' => $emp->id]);
        $this->assertDatabaseHas('employees', ['id' => $emp->id, 'status' => 'terminated']);
    }

    // ── Sensitive field visibility ────────────────────────────────────────

    /** @test */
    public function salary_is_hidden_from_regular_employees(): void
    {
        $empModel = Employee::factory()->create(['salary' => 15000]);
        $this->employee->employee()->associate($empModel)->save();

        Sanctum::actingAs($this->employee);

        $this->getJson("/api/v1/employees/{$empModel->id}")
            ->assertOk()
            ->assertJsonPath('employee.salary', null);
    }

    /** @test */
    public function salary_is_visible_to_hr_manager(): void
    {
        $emp = Employee::factory()->create(['salary' => 15000]);
        Sanctum::actingAs($this->hrManager);

        $this->getJson("/api/v1/employees/{$emp->id}")
            ->assertOk()
            ->assertJsonPath('employee.salary', '15000.00');
    }

    // ── Rate limiting ─────────────────────────────────────────────────────

    /** @test */
    public function login_endpoint_returns_429_after_ten_attempts(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'x@x.com', 'password' => 'wrong']);
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'x@x.com', 'password' => 'wrong'])
            ->assertStatus(429);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Build a minimal valid employee creation payload.
     *
     * @param  string $email
     * @return array<string,mixed>
     */
    private function validEmployeePayload(string $email): array
    {
        return [
            'first_name'      => 'Test',
            'last_name'       => 'User',
            'email'           => $email,
            'hire_date'       => now()->toDateString(),
            'employment_type' => 'full_time',
            'salary'          => 5000,
        ];
    }
}
