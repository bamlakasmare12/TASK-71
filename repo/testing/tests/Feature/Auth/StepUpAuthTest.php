<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StepUpAuthTest extends TestCase
{
    use RefreshDatabase;

    private function createAndAuthUser(): User
    {
        $user = User::create([
            'username' => 'testuser',
            'name' => 'Test User',
            'email' => 'test@researchhub.local',
            'password' => 'TestPassword123!@#',
            'role' => UserRole::Admin,
            'password_updated_at' => now(),
            'is_active' => true,
        ]);

        $this->actingAs($user);

        return $user;
    }

    public function test_step_up_page_renders_for_authenticated_user(): void
    {
        $this->createAndAuthUser();

        $response = $this->get(route('auth.step-up'));
        $response->assertStatus(200);
    }

    public function test_step_up_verification_succeeds_with_correct_password(): void
    {
        $this->createAndAuthUser();

        Livewire::test(\App\Livewire\Auth\StepUpVerification::class)
            ->set('password', 'TestPassword123!@#')
            ->call('verify')
            ->assertRedirect(route('dashboard'));
    }

    public function test_step_up_verification_fails_with_wrong_password(): void
    {
        $this->createAndAuthUser();

        Livewire::test(\App\Livewire\Auth\StepUpVerification::class)
            ->set('password', 'wrongpassword')
            ->call('verify')
            ->assertSet('errorMessage', 'Password is incorrect.');
    }

    public function test_step_up_creates_audit_log_on_failure(): void
    {
        $user = $this->createAndAuthUser();

        Livewire::test(\App\Livewire\Auth\StepUpVerification::class)
            ->set('password', 'wrongpassword')
            ->call('verify');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'step_up_failed',
        ]);
    }
}
