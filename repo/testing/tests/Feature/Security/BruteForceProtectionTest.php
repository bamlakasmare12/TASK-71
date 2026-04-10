<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * E2E Brute-force protection tests.
 * Validates lockout after 5 failed attempts, CAPTCHA enforcement after 3,
 * and audit trail for all security events.
 */
class BruteForceProtectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'username' => 'target',
            'name' => 'Target User',
            'email' => 'target@test.local',
            'password' => 'ValidPassword123!@#',
            'role' => UserRole::Learner,
            'password_updated_at' => now(),
            'is_active' => true,
        ]);
    }

    /**
     * First 2 failed attempts: no CAPTCHA shown, error message displayed.
     */
    public function test_first_two_failures_show_error_only(): void
    {
        for ($i = 0; $i < 2; $i++) {
            Livewire::test(\App\Livewire\Auth\LoginForm::class)
                ->set('username', 'target')
                ->set('password', 'wrong_password')
                ->call('login')
                ->assertSet('showCaptcha', false)
                ->assertSet('errorMessage', 'Invalid username or password.');
        }

        $this->user->refresh();
        $this->assertEquals(2, $this->user->failed_login_attempts);
        $this->assertNull($this->user->locked_until);
    }

    /**
     * 3rd failed attempt: CAPTCHA is now required.
     */
    public function test_third_failure_triggers_captcha(): void
    {
        $this->user->update(['failed_login_attempts' => 2]);

        Livewire::test(\App\Livewire\Auth\LoginForm::class)
            ->set('username', 'target')
            ->set('password', 'wrong_password')
            ->call('login')
            ->assertSet('showCaptcha', true);
    }

    /**
     * Attempts 4 and 5 with CAPTCHA required but not provided: still rejected.
     */
    public function test_login_without_captcha_when_required_is_rejected(): void
    {
        $this->user->update(['failed_login_attempts' => 4]);

        Livewire::test(\App\Livewire\Auth\LoginForm::class)
            ->set('username', 'target')
            ->set('password', 'ValidPassword123!@#')
            ->call('login')
            ->assertSet('showCaptcha', true)
            ->assertSet('errorMessage', 'Please complete the CAPTCHA to continue.');

        // Even correct password without CAPTCHA should not authenticate
        $this->assertGuest();
    }

    /**
     * 5th failed attempt: account becomes locked for 15 minutes.
     */
    public function test_fifth_failure_locks_account(): void
    {
        $this->user->update(['failed_login_attempts' => 4]);

        // Generate a valid CAPTCHA so it passes, but password is wrong -> triggers lockout
        $captcha = app(\App\Services\CaptchaService::class);
        $captcha->generate();
        $validCode = session('captcha_code');

        Livewire::test(\App\Livewire\Auth\LoginForm::class)
            ->set('username', 'target')
            ->set('password', 'wrong_password')
            ->set('showCaptcha', true)
            ->set('captchaInput', $validCode)
            ->call('login');

        $this->user->refresh();
        $this->assertEquals(5, $this->user->failed_login_attempts);
        $this->assertNotNull($this->user->locked_until);
        $this->assertTrue($this->user->isLocked());
    }

    /**
     * 6th attempt on locked account: rejected with lockout message.
     */
    public function test_sixth_attempt_returns_lockout_message(): void
    {
        $this->user->update([
            'failed_login_attempts' => 5,
            'locked_until' => now()->addMinutes(15),
        ]);

        Livewire::test(\App\Livewire\Auth\LoginForm::class)
            ->set('username', 'target')
            ->set('password', 'ValidPassword123!@#')
            ->call('login')
            ->assertSee('Account is locked');

        $this->assertGuest();
    }

    /**
     * Lockout audit log is written with critical severity.
     */
    public function test_lockout_creates_critical_audit_log(): void
    {
        $this->user->update(['failed_login_attempts' => 4]);

        $captcha = app(\App\Services\CaptchaService::class);
        $captcha->generate();
        $validCode = session('captcha_code');

        Livewire::test(\App\Livewire\Auth\LoginForm::class)
            ->set('username', 'target')
            ->set('password', 'wrong_password')
            ->set('showCaptcha', true)
            ->set('captchaInput', $validCode)
            ->call('login');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'action' => 'account_locked',
            'severity' => 'critical',
        ]);
    }

    /**
     * Successful login after lockout expiry resets failed attempts.
     */
    public function test_login_succeeds_after_lockout_expires(): void
    {
        $this->user->update([
            'failed_login_attempts' => 5,
            'locked_until' => now()->subMinute(), // Expired
        ]);

        // User still has 5 failed attempts so CAPTCHA is required
        $captcha = app(\App\Services\CaptchaService::class);
        $captcha->generate();
        $validCode = session('captcha_code');

        Livewire::test(\App\Livewire\Auth\LoginForm::class)
            ->set('username', 'target')
            ->set('password', 'ValidPassword123!@#')
            ->set('showCaptcha', true)
            ->set('captchaInput', $validCode)
            ->call('login')
            ->assertRedirect(route('dashboard'));

        $this->user->refresh();
        $this->assertEquals(0, $this->user->failed_login_attempts);
        $this->assertNull($this->user->locked_until);
    }

    /**
     * All failed login attempts are individually logged to audit_logs.
     */
    public function test_each_failure_creates_audit_log(): void
    {
        for ($i = 0; $i < 3; $i++) {
            Livewire::test(\App\Livewire\Auth\LoginForm::class)
                ->set('username', 'target')
                ->set('password', 'wrong_password')
                ->call('login');
        }

        $failedLogs = AuditLog::where('user_id', $this->user->id)
            ->where('action', 'login_failed')
            ->count();

        $this->assertEquals(3, $failedLogs);
    }
}
