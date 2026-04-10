<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoginTest extends TestCase
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

    public function test_login_page_renders(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_successful_login(): void
    {
        $this->createUser();

        Livewire::test(\App\Livewire\Auth\LoginForm::class)
            ->set('username', 'testuser')
            ->set('password', 'TestPassword123!@#')
            ->call('login')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $this->createUser();

        Livewire::test(\App\Livewire\Auth\LoginForm::class)
            ->set('username', 'testuser')
            ->set('password', 'wrongpassword')
            ->call('login')
            ->assertSet('errorMessage', 'Invalid username or password.');

        $this->assertGuest();
    }

    public function test_login_fails_for_nonexistent_user(): void
    {
        Livewire::test(\App\Livewire\Auth\LoginForm::class)
            ->set('username', 'nonexistent')
            ->set('password', 'somepassword')
            ->call('login')
            ->assertSet('errorMessage', 'Invalid username or password.');

        $this->assertGuest();
    }

    public function test_login_fails_for_disabled_account(): void
    {
        $this->createUser(['is_active' => false]);

        Livewire::test(\App\Livewire\Auth\LoginForm::class)
            ->set('username', 'testuser')
            ->set('password', 'TestPassword123!@#')
            ->call('login')
            ->assertSet('errorMessage', 'Your account has been disabled. Contact an administrator.');
    }

    public function test_account_locks_after_5_failed_attempts(): void
    {
        $user = $this->createUser(['failed_login_attempts' => 4]);

        // Generate valid CAPTCHA so it passes, but password is wrong -> triggers lockout
        app(\App\Services\CaptchaService::class)->generate();
        $captchaCode = session('captcha_code');

        Livewire::test(\App\Livewire\Auth\LoginForm::class)
            ->set('username', 'testuser')
            ->set('password', 'wrongpassword')
            ->set('showCaptcha', true)
            ->set('captchaInput', $captchaCode)
            ->call('login');

        $user->refresh();
        $this->assertTrue($user->isLocked());
        $this->assertEquals(5, $user->failed_login_attempts);
    }

    public function test_locked_account_cannot_login(): void
    {
        $this->createUser([
            'locked_until' => now()->addMinutes(15),
            'failed_login_attempts' => 5,
        ]);

        Livewire::test(\App\Livewire\Auth\LoginForm::class)
            ->set('username', 'testuser')
            ->set('password', 'TestPassword123!@#')
            ->call('login')
            ->assertSee('Account is locked');

        $this->assertGuest();
    }

    public function test_captcha_required_after_3_failed_attempts(): void
    {
        $this->createUser(['failed_login_attempts' => 3]);

        Livewire::test(\App\Livewire\Auth\LoginForm::class)
            ->set('username', 'testuser')
            ->set('password', 'wrongpassword')
            ->call('login')
            ->assertSet('showCaptcha', true);
    }

    public function test_redirects_to_password_change_on_expired_password(): void
    {
        $this->createUser(['password_updated_at' => now()->subDays(91)]);

        Livewire::test(\App\Livewire\Auth\LoginForm::class)
            ->set('username', 'testuser')
            ->set('password', 'TestPassword123!@#')
            ->call('login')
            ->assertRedirect(route('auth.password.change'));
    }

    public function test_authenticated_user_redirected_from_login(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        Livewire::test(\App\Livewire\Auth\LoginForm::class)
            ->assertRedirect(route('dashboard'));
    }
}
