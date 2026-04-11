<?php

namespace Tests\Api;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'username' => 'testuser',
            'name' => 'Test User',
            'email' => 'test@researchhub.local',
            'password' => 'TestPassword123!@#',
            'role' => UserRole::Learner,
            'password_updated_at' => now(),
            'is_active' => true,
        ], $overrides));
    }

    public function test_api_login_success_returns_user(): void
    {
        $this->createUser();

        $response = $this->postJson('/api/auth/login', [
            'username' => 'testuser',
            'password' => 'TestPassword123!@#',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user' => ['id', 'username', 'name', 'email', 'role']]);
    }

    public function test_api_login_creates_audit_log(): void
    {
        $this->createUser();

        $this->postJson('/api/auth/login', [
            'username' => 'testuser',
            'password' => 'TestPassword123!@#',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::Login->value,
        ]);
    }

    public function test_api_login_fails_with_wrong_password(): void
    {
        $this->createUser();

        $response = $this->postJson('/api/auth/login', [
            'username' => 'testuser',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error', 'failed');
    }

    public function test_api_login_fails_for_disabled_account(): void
    {
        $this->createUser(['is_active' => false]);

        $response = $this->postJson('/api/auth/login', [
            'username' => 'testuser',
            'password' => 'TestPassword123!@#',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error', 'failed');
    }

    public function test_api_login_locks_after_5_failed_attempts(): void
    {
        $this->createUser([
            'locked_until' => now()->addMinutes(15),
            'failed_login_attempts' => 5,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'username' => 'testuser',
            'password' => 'TestPassword123!@#',
        ]);

        $response->assertStatus(423)
            ->assertJsonPath('error', 'locked');
    }

    public function test_api_login_returns_captcha_required(): void
    {
        $this->createUser(['failed_login_attempts' => 3]);

        $response = $this->postJson('/api/auth/login', [
            'username' => 'testuser',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'captcha_required');
    }

    public function test_api_login_returns_password_expired(): void
    {
        $this->createUser(['password_updated_at' => now()->subDays(91)]);

        $response = $this->postJson('/api/auth/login', [
            'username' => 'testuser',
            'password' => 'TestPassword123!@#',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error', 'password_expired');
    }

    public function test_api_me_returns_authenticated_user(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $response = $this->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.username', 'testuser');
    }

    public function test_api_me_returns_401_for_guest(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertUnauthorized();
    }

    public function test_api_logout(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $response = $this->postJson('/api/auth/logout');
        $response->assertOk()
            ->assertJsonPath('message', 'Logged out.');
    }

    public function test_api_protected_routes_return_403_for_expired_password(): void
    {
        $user = $this->createUser(['password_updated_at' => now()->subDays(91)]);
        $this->actingAs($user);

        $response = $this->getJson('/api/catalog');

        $response->assertStatus(403)
            ->assertJsonPath('error', 'password_expired');
    }

    public function test_api_step_up_verify_with_correct_password(): void
    {
        $user = $this->createUser(['role' => UserRole::Admin]);
        $this->actingAs($user);

        $response = $this->postJson('/api/auth/step-up', [
            'password' => 'TestPassword123!@#',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['elevated_until']);
    }

    public function test_api_step_up_verify_rejects_wrong_password(): void
    {
        $user = $this->createUser(['role' => UserRole::Admin]);
        $this->actingAs($user);

        $response = $this->postJson('/api/auth/step-up', [
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(403);
    }
}
