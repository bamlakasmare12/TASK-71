<?php

namespace Tests\Api;

use App\Enums\UserRole;
use Tests\Api\Concerns\CreatesTestData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    // ── POST /api/auth/login ──

    public function test_login_returns_user_on_success(): void
    {
        $user = $this->createLearner();

        $response = $this->postJson('/api/auth/login', [
            'username' => $user->username,
            'password' => 'TestPassword123!@#',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user']);

        // The user resource may be nested or flat depending on implementation
        $userData = $response->json('user.data') ?? $response->json('user');
        $this->assertEquals($user->username, $userData['username'] ?? null);
    }

    public function test_login_returns_401_on_wrong_password(): void
    {
        $user = $this->createLearner();

        $this->postJson('/api/auth/login', [
            'username' => $user->username,
            'password' => 'WrongPassword123!@#',
        ])->assertStatus(401)
          ->assertJsonPath('error', 'failed');
    }

    public function test_login_returns_401_for_nonexistent_user(): void
    {
        $this->postJson('/api/auth/login', [
            'username' => 'ghost',
            'password' => 'Whatever123!@#',
        ])->assertStatus(401);
    }

    public function test_login_returns_423_when_locked(): void
    {
        $user = $this->createLearner([
            'failed_login_attempts' => 5,
            'locked_until' => now()->addMinutes(15),
        ]);

        $this->postJson('/api/auth/login', [
            'username' => $user->username,
            'password' => 'TestPassword123!@#',
        ])->assertStatus(423)
          ->assertJsonPath('error', 'locked');
    }

    public function test_login_returns_422_captcha_required_after_3_failures(): void
    {
        $user = $this->createLearner(['failed_login_attempts' => 3]);

        $this->postJson('/api/auth/login', [
            'username' => $user->username,
            'password' => 'TestPassword123!@#',
        ])->assertStatus(422)
          ->assertJsonPath('error', 'captcha_required');
    }

    public function test_login_returns_403_when_password_expired(): void
    {
        $user = $this->createLearner(['password_updated_at' => now()->subDays(91)]);

        $this->postJson('/api/auth/login', [
            'username' => $user->username,
            'password' => 'TestPassword123!@#',
        ])->assertStatus(403)
          ->assertJsonPath('error', 'password_expired');
    }

    public function test_login_returns_401_for_disabled_account(): void
    {
        $user = $this->createLearner(['is_active' => false]);

        $this->postJson('/api/auth/login', [
            'username' => $user->username,
            'password' => 'TestPassword123!@#',
        ])->assertStatus(401);
    }

    public function test_login_validation_requires_username_and_password(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['username', 'password']);
    }

    public function test_login_increments_failed_attempts(): void
    {
        $user = $this->createLearner();

        $this->postJson('/api/auth/login', [
            'username' => $user->username,
            'password' => 'wrong',
        ]);

        $this->assertEquals(1, $user->fresh()->failed_login_attempts);
    }

    public function test_login_resets_failed_attempts_on_success(): void
    {
        $user = $this->createLearner(['failed_login_attempts' => 2]);

        $this->postJson('/api/auth/login', [
            'username' => $user->username,
            'password' => 'TestPassword123!@#',
        ])->assertOk();

        $this->assertEquals(0, $user->fresh()->failed_login_attempts);
    }

    // ── GET /api/auth/me ──

    public function test_me_returns_authenticated_user(): void
    {
        $user = $this->createLearner();

        $this->actingAs($user)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.username', $user->username)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_me_returns_401_for_guest(): void
    {
        $this->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    public function test_me_does_not_expose_password(): void
    {
        $user = $this->createLearner();

        $response = $this->actingAs($user)->getJson('/api/auth/me');

        $this->assertArrayNotHasKey('password', $response->json('data'));
    }

    // ── POST /api/auth/logout ──

    public function test_logout_returns_success(): void
    {
        $user = $this->createLearner();

        $this->actingAs($user)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out.');
    }

    public function test_logout_creates_audit_log(): void
    {
        $user = $this->createLearner();

        $this->actingAs($user)->postJson('/api/auth/logout');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'logout',
        ]);
    }

    // ── POST /api/auth/step-up ──

    public function test_step_up_verify_success(): void
    {
        $user = $this->createAdmin();

        $this->actingAs($user)
            ->postJson('/api/auth/step-up', ['password' => 'TestPassword123!@#'])
            ->assertOk()
            ->assertJsonStructure(['message', 'elevated_until']);
    }

    public function test_step_up_verify_wrong_password(): void
    {
        $user = $this->createAdmin();

        $this->actingAs($user)
            ->postJson('/api/auth/step-up', ['password' => 'WrongPass123!@#'])
            ->assertStatus(403)
            ->assertJsonPath('error', 'Invalid password.');
    }

    public function test_step_up_creates_audit_on_success(): void
    {
        $user = $this->createAdmin();

        $this->actingAs($user)
            ->postJson('/api/auth/step-up', ['password' => 'TestPassword123!@#']);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'step_up_verified',
        ]);
    }

    public function test_step_up_creates_audit_on_failure(): void
    {
        $user = $this->createAdmin();

        $this->actingAs($user)
            ->postJson('/api/auth/step-up', ['password' => 'wrong']);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'step_up_failed',
        ]);
    }
}
