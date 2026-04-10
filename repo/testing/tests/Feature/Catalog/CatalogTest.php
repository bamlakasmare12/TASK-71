<?php

namespace Tests\Feature\Catalog;

use App\Enums\UserRole;
use App\Models\Service;
use App\Models\Tag;
use App\Models\User;
use App\Models\UserFavorite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogTest extends TestCase
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
            'title' => 'Test Consultation',
            'description' => 'A test service for research consultation.',
            'service_type' => 'consultation',
            'target_audience' => ['faculty', 'graduate'],
            'price' => 0,
            'is_active' => true,
            'created_by' => $this->editor->id,
            'updated_by' => $this->editor->id,
        ], $overrides));
    }

    public function test_catalog_page_renders_for_authenticated_user(): void
    {
        $this->actingAs($this->learner);
        $response = $this->get('/catalog');
        $response->assertStatus(200);
    }

    public function test_catalog_requires_authentication(): void
    {
        $response = $this->get('/catalog');
        $response->assertRedirect('/login');
    }

    public function test_catalog_displays_active_services(): void
    {
        $this->createService(['title' => 'Active Service']);
        $this->createService(['title' => 'Inactive Service', 'slug' => 'inactive', 'is_active' => false]);

        $this->actingAs($this->learner);

        Livewire::test(\App\Livewire\Catalog\CatalogList::class)
            ->assertSee('Active Service')
            ->assertDontSee('Inactive Service');
    }

    public function test_catalog_search_filters_by_title(): void
    {
        $this->createService(['title' => 'Statistics Help']);
        $this->createService(['title' => 'Writing Workshop', 'slug' => 'writing-workshop']);

        $this->actingAs($this->learner);

        Livewire::test(\App\Livewire\Catalog\CatalogList::class)
            ->set('search', 'Statistics')
            ->assertSee('Statistics Help')
            ->assertDontSee('Writing Workshop');
    }

    public function test_catalog_filters_by_audience(): void
    {
        $this->createService(['title' => 'Faculty Only', 'target_audience' => ['faculty']]);
        $this->createService(['title' => 'Graduate Only', 'slug' => 'grad-only', 'target_audience' => ['graduate']]);

        $this->actingAs($this->learner);

        Livewire::test(\App\Livewire\Catalog\CatalogList::class)
            ->set('audience', 'faculty')
            ->assertSee('Faculty Only')
            ->assertDontSee('Graduate Only');
    }

    public function test_toggle_favorite(): void
    {
        $service = $this->createService();
        $this->actingAs($this->learner);

        // Favorite
        Livewire::test(\App\Livewire\Catalog\CatalogList::class)
            ->call('toggleFavorite', $service->id);

        $this->assertDatabaseHas('user_favorites', [
            'user_id' => $this->learner->id,
            'service_id' => $service->id,
        ]);

        // Unfavorite
        Livewire::test(\App\Livewire\Catalog\CatalogList::class)
            ->call('toggleFavorite', $service->id);

        $this->assertDatabaseMissing('user_favorites', [
            'user_id' => $this->learner->id,
            'service_id' => $service->id,
        ]);
    }

    public function test_learner_cannot_access_service_manager(): void
    {
        $this->actingAs($this->learner);
        $response = $this->get('/services-manage/create');
        $response->assertStatus(403);
    }

    public function test_editor_can_access_service_manager(): void
    {
        $this->actingAs($this->editor);
        $response = $this->get('/services-manage/create');
        $response->assertStatus(200);
    }

    public function test_service_detail_records_recently_viewed(): void
    {
        $service = $this->createService();
        $this->actingAs($this->learner);

        Livewire::test(\App\Livewire\Catalog\ServiceDetail::class, ['service' => $service]);

        $this->assertDatabaseHas('user_recently_viewed', [
            'user_id' => $this->learner->id,
            'service_id' => $service->id,
        ]);
    }
}
