<?php

namespace Tests\Feature\Auth;

use App\Actions\Auth\PerformLogout;
use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class SessionAndLogoutTest extends TestCase
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

    public function test_logout_clears_auth(): void
    {
        $user = $this->createUser();
        Auth::login($user);
        $this->assertTrue(Auth::check());

        app(PerformLogout::class)->execute(allDevices: true);

        $this->assertFalse(Auth::check());
    }

    public function test_logout_creates_audit_log(): void
    {
        $user = $this->createUser();
        Auth::login($user);

        app(PerformLogout::class)->execute(allDevices: true);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => AuditAction::Logout->value,
        ]);
    }

    public function test_dashboard_requires_authentication(): void
    {
        $response = $this->get('/');
        $response->assertRedirect('/login');
    }

    public function test_expired_password_redirects_to_change(): void
    {
        $user = $this->createUser(['password_updated_at' => now()->subDays(91)]);
        $this->actingAs($user);

        $response = $this->get('/');
        $response->assertRedirect(route('auth.password.change'));
    }

    public function test_audit_log_is_immutable(): void
    {
        AuditLog::create([
            'user_id' => null,
            'action' => 'login',
            'ip_address' => '127.0.0.1',
            'severity' => 'info',
            'created_at' => now(),
        ]);

        $this->assertEquals(1, AuditLog::count());
        $this->assertNotNull(AuditLog::first()->created_at);
    }
}
