<?php

namespace Tests\Api;

use App\Models\Service;
use App\Models\Tag;
use Tests\Api\Concerns\CreatesTestData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogApiTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    // ── GET /api/catalog ──

    public function test_catalog_requires_auth(): void
    {
        $this->getJson('/api/catalog')->assertUnauthorized();
    }

    public function test_catalog_returns_paginated_services(): void
    {
        $editor = $this->createEditor();
        $this->seedDictionaries();
        $this->createService($editor);
        $this->createService($editor, ['title' => 'Second Service', 'slug' => 'second']);

        $this->actingAs($this->createLearner())
            ->getJson('/api/catalog')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_catalog_hides_inactive_services(): void
    {
        $editor = $this->createEditor();
        $this->createService($editor, ['is_active' => true]);
        $this->createService($editor, ['title' => 'Hidden', 'slug' => 'hidden', 'is_active' => false]);

        $response = $this->actingAs($this->createLearner())->getJson('/api/catalog');

        $titles = collect($response->json('data'))->pluck('title');
        $this->assertNotContains('Hidden', $titles);
    }

    public function test_catalog_search_by_title(): void
    {
        $editor = $this->createEditor();
        $this->createService($editor, ['title' => 'Statistics Help']);
        $this->createService($editor, ['title' => 'Writing Workshop', 'slug' => 'writing']);

        $response = $this->actingAs($this->createLearner())
            ->getJson('/api/catalog?search=Statistics');

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Statistics Help', $response->json('data.0.title'));
    }

    public function test_catalog_filter_by_audience(): void
    {
        $editor = $this->createEditor();
        $this->createService($editor, ['title' => 'Faculty Only', 'target_audience' => ['faculty']]);
        $this->createService($editor, ['title' => 'Grad Only', 'slug' => 'grad', 'target_audience' => ['graduate']]);

        $response = $this->actingAs($this->createLearner())
            ->getJson('/api/catalog?audience=faculty');

        $titles = collect($response->json('data'))->pluck('title');
        $this->assertContains('Faculty Only', $titles);
        $this->assertNotContains('Grad Only', $titles);
    }

    public function test_catalog_filter_by_price(): void
    {
        $editor = $this->createEditor();
        $this->createService($editor, ['title' => 'Free Service', 'price' => 0]);
        $this->createService($editor, ['title' => 'Paid Service', 'slug' => 'paid', 'price' => 25]);

        $response = $this->actingAs($this->createLearner())
            ->getJson('/api/catalog?price_filter=free');

        $titles = collect($response->json('data'))->pluck('title');
        $this->assertContains('Free Service', $titles);
        $this->assertNotContains('Paid Service', $titles);
    }

    public function test_catalog_sort_by_price(): void
    {
        $editor = $this->createEditor();
        $this->createService($editor, ['title' => 'Expensive', 'price' => 100]);
        $this->createService($editor, ['title' => 'Cheap', 'slug' => 'cheap', 'price' => 5]);

        $response = $this->actingAs($this->createLearner())
            ->getJson('/api/catalog?sort=price_low');

        $this->assertEquals('Cheap', $response->json('data.0.title'));
    }

    // ── GET /api/catalog/{service} ──

    public function test_show_returns_service_detail(): void
    {
        $editor = $this->createEditor();
        $service = $this->createService($editor);
        $service->tags()->sync([Tag::create(['name' => 'Stats'])->id]);

        $this->actingAs($this->createLearner())
            ->getJson("/api/catalog/{$service->id}")
            ->assertOk()
            ->assertJsonPath('data.title', $service->title)
            ->assertJsonPath('data.tags.0.name', 'Stats');
    }

    public function test_show_404_for_nonexistent_service(): void
    {
        $this->actingAs($this->createLearner())
            ->getJson('/api/catalog/99999')
            ->assertNotFound();
    }

    // ── POST /api/catalog (create) ──

    public function test_create_service_requires_editor_role(): void
    {
        $this->actingAs($this->createLearner())
            ->postJson('/api/catalog', ['title' => 'New'])
            ->assertForbidden();
    }

    public function test_create_service_succeeds_for_editor(): void
    {
        $editor = $this->createEditor();
        $this->seedDictionaries();

        $this->actingAs($editor)
            ->postJson('/api/catalog', [
                'title' => 'New Research Service',
                'description' => 'Comprehensive support for researchers.',
                'service_type' => 'consultation',
                'target_audience' => ['faculty'],
                'price' => 0,
                'tags' => ['research', 'support'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'New Research Service');

        $this->assertDatabaseHas('services', ['title' => 'New Research Service']);
    }

    public function test_create_service_validates_required_fields(): void
    {
        $this->actingAs($this->createEditor())
            ->postJson('/api/catalog', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'description']);
    }

    // ── PUT /api/catalog/{service} (update) ──

    public function test_update_service_succeeds_for_editor(): void
    {
        $editor = $this->createEditor();
        $service = $this->createService($editor);

        $this->actingAs($editor)
            ->putJson("/api/catalog/{$service->id}", [
                'title' => 'Updated Title',
                'description' => $service->description,
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated Title');
    }

    public function test_update_service_forbidden_for_learner(): void
    {
        $service = $this->createService($this->createEditor());

        $this->actingAs($this->createLearner())
            ->putJson("/api/catalog/{$service->id}", ['title' => 'Hacked'])
            ->assertForbidden();
    }

    // ── POST /api/catalog/{service}/favorite ──

    public function test_toggle_favorite_on(): void
    {
        $service = $this->createService($this->createEditor());
        $learner = $this->createLearner();

        $this->actingAs($learner)
            ->postJson("/api/catalog/{$service->id}/favorite")
            ->assertOk()
            ->assertJsonPath('favorited', true);

        $this->assertDatabaseHas('user_favorites', [
            'user_id' => $learner->id,
            'service_id' => $service->id,
        ]);
    }

    public function test_toggle_favorite_off(): void
    {
        $service = $this->createService($this->createEditor());
        $learner = $this->createLearner();

        // First toggle on
        $this->actingAs($learner)->postJson("/api/catalog/{$service->id}/favorite");
        // Second toggle off
        $this->actingAs($learner)
            ->postJson("/api/catalog/{$service->id}/favorite")
            ->assertOk()
            ->assertJsonPath('favorited', false);
    }

    // ── GET /api/catalog/favorites ──

    public function test_favorites_returns_user_favorites(): void
    {
        $service = $this->createService($this->createEditor());
        $learner = $this->createLearner();

        $this->actingAs($learner)->postJson("/api/catalog/{$service->id}/favorite");

        $this->actingAs($learner)
            ->getJson('/api/catalog/favorites')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // ── GET /api/catalog/dictionaries ──

    public function test_dictionaries_returns_types_and_audiences(): void
    {
        $this->seedDictionaries();

        $this->actingAs($this->createLearner())
            ->getJson('/api/catalog/dictionaries')
            ->assertOk()
            ->assertJsonStructure(['service_types', 'audiences']);
    }
}
