<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\PasswordHistory;
use App\Models\User;
use App\Services\PasswordPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    private PasswordPolicyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PasswordPolicyService();
    }

    public function test_password_must_be_at_least_12_characters(): void
    {
        $errors = $this->service->validate('Short1!');
        $this->assertContains('Password must be at least 12 characters.', $errors);
    }

    public function test_password_requires_uppercase(): void
    {
        $errors = $this->service->validate('alllowercase1!@#');
        $this->assertContains('Password must contain at least one uppercase letter.', $errors);
    }

    public function test_password_requires_lowercase(): void
    {
        $errors = $this->service->validate('ALLUPPERCASE1!@#');
        $this->assertContains('Password must contain at least one lowercase letter.', $errors);
    }

    public function test_password_requires_number(): void
    {
        $errors = $this->service->validate('NoNumbersHere!@#');
        $this->assertContains('Password must contain at least one number.', $errors);
    }

    public function test_password_requires_special_character(): void
    {
        $errors = $this->service->validate('NoSpecialChar123');
        $this->assertContains('Password must contain at least one special character.', $errors);
    }

    public function test_valid_password_passes(): void
    {
        $errors = $this->service->validate('ValidPass123!@#');
        $this->assertEmpty($errors);
    }

    public function test_password_cannot_match_last_5(): void
    {
        $user = User::create([
            'username' => 'testuser',
            'name' => 'Test',
            'email' => 'test@test.local',
            'password' => 'CurrentPass1!@#',
            'role' => UserRole::Learner,
            'password_updated_at' => now(),
            'is_active' => true,
        ]);

        $oldPassword = 'OldPassword123!@';
        PasswordHistory::create([
            'user_id' => $user->id,
            'password_hash' => Hash::make($oldPassword),
            'created_at' => now(),
        ]);

        $errors = $this->service->validate($oldPassword, $user);
        $this->assertContains('Password cannot match any of your last 5 passwords.', $errors);
    }

    public function test_password_history_keeps_only_last_5(): void
    {
        $user = User::create([
            'username' => 'testuser',
            'name' => 'Test',
            'email' => 'test@test.local',
            'password' => 'CurrentPass1!@#',
            'role' => UserRole::Learner,
            'password_updated_at' => now(),
            'is_active' => true,
        ]);

        // Record 7 passwords
        for ($i = 0; $i < 7; $i++) {
            $this->service->recordPassword($user, Hash::make("Password{$i}!@#"));
        }

        $this->assertEquals(5, PasswordHistory::where('user_id', $user->id)->count());
    }
}
