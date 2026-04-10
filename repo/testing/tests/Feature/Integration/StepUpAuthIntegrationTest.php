<?php

namespace Tests\Feature\Integration;

use App\Enums\UserRole;
use App\Http\Middleware\StepUpAuth;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Step-up authentication integration tests.
 * Validates that admin export and critical routes require
 * password re-entry and elevated session token.
 */
class StepUpAuthIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'username' => 'admin',
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => 'Admin123!@#456',
            'role' => UserRole::Admin,
            'password_updated_at' => now(),
            'is_active' => true,
        ]);
    }

    /**
     * Accessing export without step-up must redirect to verification.
     */
    public function test_export_without_elevation_redirects_to_step_up(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get('/admin/export');
        $response->assertRedirect(route('auth.step-up'));
    }

    /**
     * After step-up verification, export route becomes accessible.
     */
    public function test_export_accessible_after_step_up(): void
    {
        $this->actingAs($this->admin);

        // Simulate step-up elevation
        $response = $this->withSession(['step_up_verified_at' => now()])
            ->get('/admin/export');
        $response->assertStatus(200);
    }

    /**
     * Step-up elevation expires after 5 minutes.
     */
    public function test_step_up_expires_after_5_minutes(): void
    {
        $this->actingAs($this->admin);

        // Test the StepUpAuth::isElevated() static method directly
        // (session middleware + Carbon serialization makes HTTP-level testing unreliable)
        \App\Http\Middleware\StepUpAuth::elevate();
        $this->assertTrue(\App\Http\Middleware\StepUpAuth::isElevated());

        // Advance clock past the 5-minute window
        \Carbon\Carbon::setTestNow(now()->addMinutes(6));
        $this->assertFalse(\App\Http\Middleware\StepUpAuth::isElevated());

        \Carbon\Carbon::setTestNow();
    }

    /**
     * Step-up verification component accepts correct password.
     */
    public function test_step_up_verification_with_correct_password(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(\App\Livewire\Auth\StepUpVerification::class)
            ->set('password', 'Admin123!@#456')
            ->call('verify')
            ->assertRedirect(route('dashboard'));

        // Verify elevation is stored in session
        $this->assertTrue(StepUpAuth::isElevated());
    }

    /**
     * Step-up verification rejects wrong password.
     */
    public function test_step_up_verification_rejects_wrong_password(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(\App\Livewire\Auth\StepUpVerification::class)
            ->set('password', 'WrongPassword123!@#')
            ->call('verify')
            ->assertSet('errorMessage', 'Password is incorrect.')
            ->assertNoRedirect();

        $this->assertFalse(StepUpAuth::isElevated());
    }

    /**
     * Step-up redirects back to the originally requested URL after verification.
     */
    public function test_step_up_redirects_to_original_url(): void
    {
        $this->actingAs($this->admin);

        // Hit export — gets redirected to step-up with redirect stored in session
        $this->get('/admin/export');
        $this->assertEquals(url('/admin/export'), session('step_up_redirect'));
    }

    /**
     * Import route requires step-up (all admin routes do).
     */
    public function test_import_requires_step_up(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get('/admin/import');
        $response->assertRedirect(route('auth.step-up'));
    }

    /**
     * Non-admin cannot access export even with step-up elevation.
     */
    public function test_editor_cannot_access_export_even_with_elevation(): void
    {
        $editor = User::create([
            'username' => 'editor',
            'name' => 'Editor',
            'email' => 'editor@test.local',
            'password' => 'Editor123!@#456',
            'role' => UserRole::Editor,
            'password_updated_at' => now(),
            'is_active' => true,
        ]);

        $this->actingAs($editor);

        $response = $this->withSession(['step_up_verified_at' => now()])
            ->get('/admin/export');
        $response->assertStatus(403);
    }
}
