<?php

namespace Tests\Feature\Security;

use App\Actions\Auth\PerformLogout;
use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SingleLogoutTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'username' => 'multidevice',
            'name' => 'Multi Device User',
            'email' => 'multi@test.local',
            'password' => 'MultiDevice123!@#',
            'role' => UserRole::Learner,
            'password_updated_at' => now(),
            'is_active' => true,
        ]);
    }

    /**
     * PerformLogout action purges all session rows for the user.
     */
    public function test_perform_logout_purges_all_sessions(): void
    {
        // Simulate multiple active sessions in DB
        DB::table('sessions')->insert([
            ['id' => 'sess_1', 'user_id' => $this->user->id, 'ip_address' => '10.0.0.1', 'user_agent' => 'A', 'payload' => '', 'last_activity' => now()->timestamp],
            ['id' => 'sess_2', 'user_id' => $this->user->id, 'ip_address' => '10.0.0.2', 'user_agent' => 'B', 'payload' => '', 'last_activity' => now()->timestamp],
            ['id' => 'sess_3', 'user_id' => $this->user->id, 'ip_address' => '10.0.0.3', 'user_agent' => 'C', 'payload' => '', 'last_activity' => now()->timestamp],
        ]);

        $this->assertEquals(3, DB::table('sessions')->where('user_id', $this->user->id)->count());

        // Authenticate and perform logout
        Auth::login($this->user);
        $action = app(PerformLogout::class);
        $action->execute(allDevices: true);

        // All session rows for this user should be gone
        $this->assertEquals(0, DB::table('sessions')->where('user_id', $this->user->id)->count());
    }

    /**
     * Single-logout does NOT affect other users' sessions.
     */
    public function test_logout_does_not_affect_other_users(): void
    {
        $otherUser = User::create([
            'username' => 'otheruser',
            'name' => 'Other User',
            'email' => 'other@test.local',
            'password' => 'OtherPass123!@#',
            'role' => UserRole::Learner,
            'password_updated_at' => now(),
            'is_active' => true,
        ]);

        DB::table('sessions')->insert([
            ['id' => 'my_sess', 'user_id' => $this->user->id, 'ip_address' => '10.0.0.1', 'user_agent' => 'A', 'payload' => '', 'last_activity' => now()->timestamp],
            ['id' => 'other_sess', 'user_id' => $otherUser->id, 'ip_address' => '10.0.0.2', 'user_agent' => 'B', 'payload' => '', 'last_activity' => now()->timestamp],
        ]);

        Auth::login($this->user);
        app(PerformLogout::class)->execute(allDevices: true);

        // Other user's session is untouched
        $this->assertEquals(1, DB::table('sessions')->where('user_id', $otherUser->id)->count());
        $this->assertEquals(0, DB::table('sessions')->where('user_id', $this->user->id)->count());
    }

    /**
     * Logout creates both logout and logout_all audit entries.
     */
    public function test_logout_creates_dual_audit_entries(): void
    {
        Auth::login($this->user);
        app(PerformLogout::class)->execute(allDevices: true);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'action' => AuditAction::LogoutAll->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'action' => AuditAction::Logout->value,
        ]);
    }

    /**
     * After logout, the auth guard reports no authenticated user.
     */
    public function test_auth_guard_cleared_after_logout(): void
    {
        Auth::login($this->user);
        $this->assertTrue(Auth::check());

        app(PerformLogout::class)->execute(allDevices: true);

        $this->assertFalse(Auth::check());
    }

    /**
     * Protected routes redirect to login for unauthenticated users.
     */
    public function test_protected_routes_require_auth(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/catalog')->assertRedirect('/login');
        $this->get('/reservations')->assertRedirect('/login');
    }
}
