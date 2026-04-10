<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\FormRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDynamicValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->editor = User::create([
            'username' => 'editor',
            'name' => 'Editor',
            'email' => 'editor@test.local',
            'password' => 'TestPassword123!@#',
            'role' => UserRole::Editor,
            'password_updated_at' => now(),
            'is_active' => true,
        ]);
    }

    public function test_dynamic_form_rules_enforce_min_length_on_service_create(): void
    {
        // Create a dynamic form rule requiring title to be at least 20 characters
        FormRule::create([
            'entity' => 'service',
            'field' => 'title',
            'rules' => ['required' => true, 'type' => 'string', 'min' => 20],
            'is_active' => true,
        ]);

        $this->actingAs($this->editor);

        // Short title should fail validation
        $response = $this->postJson('/api/catalog', [
            'title' => 'Short',
            'description' => 'A detailed description of the service.',
            'service_type' => 'consultation',
            'price' => 0,
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('title', $response->json('errors', []));
    }

    public function test_dynamic_form_rules_allow_valid_data(): void
    {
        // Create a dynamic form rule requiring title min 5
        FormRule::create([
            'entity' => 'service',
            'field' => 'title',
            'rules' => ['required' => true, 'type' => 'string', 'min' => 5],
            'is_active' => true,
        ]);

        $this->actingAs($this->editor);

        $response = $this->postJson('/api/catalog', [
            'title' => 'Valid Service Title Here',
            'description' => 'A detailed description of the service.',
            'service_type' => 'consultation',
            'price' => 0,
        ]);

        $response->assertSuccessful();
        $this->assertDatabaseHas('services', ['title' => 'Valid Service Title Here']);
    }

    public function test_inactive_form_rules_are_not_enforced(): void
    {
        // Create an inactive form rule
        FormRule::create([
            'entity' => 'service',
            'field' => 'title',
            'rules' => ['required' => true, 'type' => 'string', 'min' => 100],
            'is_active' => false,
        ]);

        $this->actingAs($this->editor);

        $response = $this->postJson('/api/catalog', [
            'title' => 'Normal Title',
            'description' => 'A detailed description of the service.',
            'service_type' => 'consultation',
            'price' => 0,
        ]);

        $response->assertSuccessful();
    }
}
