<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiCatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $learner;
    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->learner = User::create([
            'username' => 'learner',
            'name' => 'Test Learner',
            'email' => 'learner@test.local',
            'password' => 'TestPassword123!@#',
            'role' => UserRole::Learner,
            'password_updated_at' => now(),
            'is_active' => true,
        ]);

        $this->editor = User::create([
            'username' => 'editor',
            'name' => 'Test Editor',
            'email' => 'editor@test.local',
            'password' => 'TestPassword123!@#',
            'role' => UserRole::Editor,
            'password_updated_at' => now(),
            'is_active' => true,
        ]);
    }

    private function createService(array $overrides = []): Service
    {
        return Service::create(array_merge([
            'title' => 'Statistics Consultation',
            'description' => 'Expert statistical analysis and methodology consulting.',
            'service_type' => 'consultation',
            'target_audience' => ['faculty', 'graduate_learner'],
            'price' => 0,
            'category' => 'research',
            'is_active' => true,
            'created_by' => $this->editor->id,
            'updated_by' => $this->editor->id,
        ], $overrides));
    }

    public function test_api_catalog_index_requires_auth(): void
    {
        $response = $this->getJson('/api/catalog');
        $response->assertUnauthorized();
    }

    public function test_api_catalog_index_returns_paginated_services(): void
    {
        $this->createService();
        $this->actingAs($this->learner);

        $response = $this->getJson('/api/catalog');

        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_api_catalog_search_filters_by_title(): void
    {
        $this->createService(['title' => 'Statistics Consultation']);
        $this->createService(['title' => 'Writing Workshop', 'slug' => 'writing-workshop']);
        $this->actingAs($this->learner);

        $response = $this->getJson('/api/catalog?search=Statistics');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Statistics Consultation', $data[0]['title']);
    }

    public function test_api_catalog_show_returns_service_detail(): void
    {
        $service = $this->createService();
        $this->actingAs($this->learner);

        $response = $this->getJson("/api/catalog/{$service->id}");

        $response->assertOk()
            ->assertJsonPath('data.title', 'Statistics Consultation');
    }

    public function test_api_catalog_toggle_favorite(): void
    {
        $service = $this->createService();
        $this->actingAs($this->learner);

        $response = $this->postJson("/api/catalog/{$service->id}/favorite");
        $response->assertOk()->assertJsonPath('favorited', true);

        $response = $this->postJson("/api/catalog/{$service->id}/favorite");
        $response->assertOk()->assertJsonPath('favorited', false);
    }

    public function test_api_catalog_store_requires_editor_role(): void
    {
        $this->actingAs($this->learner);

        $response = $this->postJson('/api/catalog', [
            'title' => 'New Service',
            'description' => 'A detailed description of the new service.',
            'service_type' => 'consultation',
            'price' => 0,
        ]);

        $response->assertStatus(403);
    }

    public function test_api_catalog_store_succeeds_for_editor(): void
    {
        $this->actingAs($this->editor);

        $response = $this->postJson('/api/catalog', [
            'title' => 'New Service',
            'description' => 'A detailed description of the new service.',
            'service_type' => 'consultation',
            'price' => 25.00,
        ]);

        $response->assertSuccessful();
        $this->assertDatabaseHas('services', ['title' => 'New Service']);
    }

    public function test_api_catalog_update_succeeds_for_editor(): void
    {
        $service = $this->createService();
        $this->actingAs($this->editor);

        $response = $this->putJson("/api/catalog/{$service->id}", [
            'title' => 'Updated Title',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Updated Title');
    }

    public function test_api_catalog_update_forbidden_for_learner(): void
    {
        $service = $this->createService();
        $this->actingAs($this->learner);

        $response = $this->putJson("/api/catalog/{$service->id}", [
            'title' => 'Hacked Title',
        ]);

        $response->assertStatus(403);
    }

    public function test_api_catalog_store_with_project_patent_numbers(): void
    {
        $this->actingAs($this->editor);

        $response = $this->postJson('/api/catalog', [
            'title' => 'Patent Service',
            'description' => 'A service linked to a patent and project.',
            'service_type' => 'consultation',
            'price' => 0,
            'project_number' => 'PRJ-2026-001',
            'patent_number' => 'PAT-12345',
        ]);

        $response->assertSuccessful();
        $this->assertDatabaseHas('services', [
            'project_number' => 'PRJ-2026-001',
            'patent_number' => 'PAT-12345',
        ]);
    }
}
