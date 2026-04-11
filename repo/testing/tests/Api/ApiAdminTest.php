<?php

namespace Tests\Api;

use App\Enums\UserRole;
use App\Models\DataDictionary;
use App\Models\FormRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $editor;
    private User $learner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'username' => 'admin',
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => 'TestPassword123!@#',
            'role' => UserRole::Admin,
            'password_updated_at' => now(),
            'is_active' => true,
        ]);

        $this->editor = User::create([
            'username' => 'editor',
            'name' => 'Editor',
            'email' => 'editor@test.local',
            'password' => 'TestPassword123!@#',
            'role' => UserRole::Editor,
            'password_updated_at' => now(),
            'is_active' => true,
        ]);

        $this->learner = User::create([
            'username' => 'learner',
            'name' => 'Learner',
            'email' => 'learner@test.local',
            'password' => 'TestPassword123!@#',
            'role' => UserRole::Learner,
            'password_updated_at' => now(),
            'is_active' => true,
        ]);
    }

    public function test_api_admin_users_requires_admin_role(): void
    {
        $this->actingAs($this->editor);

        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(403);
    }

    public function test_api_admin_users_requires_step_up(): void
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/admin/users');

        // Step-up middleware returns 403 when not elevated
        $response->assertStatus(403);
    }

    public function test_api_admin_users_accessible_with_step_up(): void
    {
        $this->actingAs($this->admin);

        $response = $this->withSession(['step_up_verified_at' => now()])
            ->getJson('/api/admin/users');

        $response->assertOk();
    }

    public function test_api_admin_change_role(): void
    {
        $this->actingAs($this->admin);

        $response = $this->withSession(['step_up_verified_at' => now()])
            ->putJson("/api/admin/users/{$this->learner->id}/role", [
                'role' => 'editor',
            ]);

        $response->assertOk();
        $this->assertEquals('editor', $this->learner->fresh()->role->value);
    }

    public function test_api_admin_deactivate_user(): void
    {
        $this->actingAs($this->admin);

        $response = $this->withSession(['step_up_verified_at' => now()])
            ->deleteJson("/api/admin/users/{$this->learner->id}");

        $response->assertOk();
        $this->assertFalse($this->learner->fresh()->is_active);
    }

    public function test_api_admin_cannot_deactivate_self(): void
    {
        $this->actingAs($this->admin);

        $response = $this->withSession(['step_up_verified_at' => now()])
            ->deleteJson("/api/admin/users/{$this->admin->id}");

        $response->assertStatus(422);
    }

    public function test_api_admin_dictionary_crud(): void
    {
        $this->actingAs($this->admin);
        $session = ['step_up_verified_at' => now()];

        // Create
        $response = $this->withSession($session)->postJson('/api/admin/dictionaries', [
            'type' => 'service_type',
            'key' => 'workshop',
            'label' => 'Workshop',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $response->assertStatus(201);
        $dictId = $response->json('data.id');

        // Read
        $response = $this->withSession($session)->getJson('/api/admin/dictionaries?type=service_type');
        $response->assertOk();

        // Update
        $response = $this->withSession($session)->putJson("/api/admin/dictionaries/{$dictId}", [
            'label' => 'Updated Workshop',
        ]);
        $response->assertOk()
            ->assertJsonPath('data.label', 'Updated Workshop');

        // Delete
        $response = $this->withSession($session)->deleteJson("/api/admin/dictionaries/{$dictId}");
        $response->assertOk();
        $this->assertDatabaseMissing('data_dictionaries', ['id' => $dictId]);
    }

    public function test_api_admin_form_rule_crud(): void
    {
        $this->actingAs($this->admin);
        $session = ['step_up_verified_at' => now()];

        // Create
        $response = $this->withSession($session)->postJson('/api/admin/form-rules', [
            'entity' => 'service',
            'field' => 'title',
            'rules' => ['required' => true, 'min' => 5, 'max' => 200],
            'is_active' => true,
        ]);
        $response->assertStatus(201);
        $ruleId = $response->json('data.id');

        // Read
        $response = $this->withSession($session)->getJson('/api/admin/form-rules?entity=service');
        $response->assertOk();

        // Update
        $response = $this->withSession($session)->putJson("/api/admin/form-rules/{$ruleId}", [
            'rules' => ['required' => true, 'min' => 10],
        ]);
        $response->assertOk();

        // Delete
        $response = $this->withSession($session)->deleteJson("/api/admin/form-rules/{$ruleId}");
        $response->assertOk();
        $this->assertDatabaseMissing('form_rules', ['id' => $ruleId]);
    }

    public function test_api_admin_learner_cannot_access_admin_routes(): void
    {
        $this->actingAs($this->learner);

        $routes = [
            ['GET', '/api/admin/users'],
            ['GET', '/api/admin/dictionaries'],
            ['GET', '/api/admin/form-rules'],
        ];

        foreach ($routes as [$method, $uri]) {
            $response = $this->json($method, $uri);
            $this->assertTrue(
                in_array($response->status(), [401, 403]),
                "Expected 401 or 403 for {$method} {$uri}, got {$response->status()}"
            );
        }
    }
}
