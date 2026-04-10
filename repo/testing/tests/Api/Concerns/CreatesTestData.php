<?php

namespace Tests\Api\Concerns;

use App\Enums\UserRole;
use App\Models\DataDictionary;
use App\Models\PasswordHistory;
use App\Models\Service;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

trait CreatesTestData
{
    protected function createUser(UserRole $role, array $overrides = []): User
    {
        static $counter = 0;
        $counter++;

        $password = 'TestPassword123!@#';
        $hashed = Hash::make($password);

        $user = User::create(array_merge([
            'username' => "{$role->value}_{$counter}",
            'name' => ucfirst($role->value) . " User {$counter}",
            'email' => "{$role->value}_{$counter}@test.local",
            'password' => $password,
            'role' => $role,
            'password_updated_at' => now(),
            'is_active' => true,
        ], $overrides));

        PasswordHistory::create([
            'user_id' => $user->id,
            'password_hash' => $hashed,
            'created_at' => now(),
        ]);

        return $user;
    }

    protected function createAdmin(array $overrides = []): User
    {
        return $this->createUser(UserRole::Admin, $overrides);
    }

    protected function createEditor(array $overrides = []): User
    {
        return $this->createUser(UserRole::Editor, $overrides);
    }

    protected function createLearner(array $overrides = []): User
    {
        return $this->createUser(UserRole::Learner, $overrides);
    }

    protected function createService(User $editor, array $overrides = []): Service
    {
        static $svcCounter = 0;
        $svcCounter++;

        return Service::create(array_merge([
            'title' => "Test Service {$svcCounter}",
            'description' => "Description for test service {$svcCounter}.",
            'service_type' => 'consultation',
            'target_audience' => ['faculty', 'graduate'],
            'price' => 0,
            'is_active' => true,
            'created_by' => $editor->id,
            'updated_by' => $editor->id,
        ], $overrides));
    }

    protected function createTimeSlot(Service $service, User $editor, array $overrides = []): TimeSlot
    {
        return TimeSlot::create(array_merge([
            'service_id' => $service->id,
            'start_time' => now()->addDays(3)->setTime(10, 0),
            'end_time' => now()->addDays(3)->setTime(11, 0),
            'capacity' => 5,
            'is_active' => true,
            'created_by' => $editor->id,
        ], $overrides));
    }

    protected function seedDictionaries(): void
    {
        $entries = [
            ['type' => 'service_type', 'key' => 'consultation', 'label' => 'Consultation', 'sort_order' => 1],
            ['type' => 'service_type', 'key' => 'equipment', 'label' => 'Equipment', 'sort_order' => 2],
            ['type' => 'service_type', 'key' => 'editorial', 'label' => 'Editorial Review', 'sort_order' => 3],
            ['type' => 'eligibility', 'key' => 'faculty', 'label' => 'Faculty', 'sort_order' => 1],
            ['type' => 'eligibility', 'key' => 'graduate', 'label' => 'Graduate', 'sort_order' => 2],
        ];

        foreach ($entries as $e) {
            DataDictionary::create($e);
        }
    }

    protected function loginViaApi(User $user): array
    {
        $response = $this->postJson('/api/auth/login', [
            'username' => $user->username,
            'password' => 'TestPassword123!@#',
        ]);

        return $response->json();
    }

    protected function actingAsWithSession(User $user): static
    {
        return $this->actingAs($user);
    }

    protected function withStepUp(): static
    {
        return $this->withSession(['step_up_verified_at' => now()]);
    }
}
