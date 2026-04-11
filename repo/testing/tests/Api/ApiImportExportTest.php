<?php

namespace Tests\Api;

use App\Enums\UserRole;
use App\Models\ImportBatch;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiImportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
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

    public function test_export_requires_admin_role(): void
    {
        $this->actingAs($this->learner);

        $response = $this->postJson('/api/admin/export', [
            'entity' => 'services',
            'format' => 'csv',
        ]);

        $response->assertStatus(403);
    }

    public function test_export_requires_step_up(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/admin/export', [
            'entity' => 'services',
            'format' => 'csv',
        ]);

        $response->assertStatus(403);
    }

    public function test_export_services_as_csv(): void
    {
        $this->actingAs($this->admin);

        Service::create([
            'title' => 'Export Test Service',
            'description' => 'A service to test exports.',
            'service_type' => 'consultation',
            'price' => 0,
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $response = $this->withSession(['step_up_verified_at' => now()])
            ->postJson('/api/admin/export', [
                'entity' => 'services',
                'format' => 'csv',
            ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['filename', 'content', 'format']]);

        $this->assertStringContainsString('Export Test Service', $response->json('data.content'));
    }

    public function test_export_creates_audit_log(): void
    {
        $this->actingAs($this->admin);

        $response = $this->withSession(['step_up_verified_at' => now()])
            ->postJson('/api/admin/export', [
                'entity' => 'services',
                'format' => 'json',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'data_export',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_import_process_requires_admin(): void
    {
        $this->actingAs($this->learner);

        $response = $this->postJson('/api/admin/import/1/process', [
            'field_mapping' => ['title' => 'title'],
        ]);

        $response->assertStatus(403);
    }

    public function test_import_batch_status(): void
    {
        $this->actingAs($this->admin);

        $batch = ImportBatch::create([
            'user_id' => $this->admin->id,
            'entity' => 'services',
            'filename' => 'test.csv',
            'stored_path' => 'imports/test.csv',
            'format' => 'csv',
            'status' => 'processing',
            'total_rows' => 10,
            'processed_rows' => 5,
            'success_count' => 3,
            'error_count' => 1,
            'duplicate_count' => 1,
            'conflict_strategy' => 'prefer_newest',
        ]);

        $response = $this->withSession(['step_up_verified_at' => now()])
            ->getJson("/api/admin/import/{$batch->id}/status");

        $response->assertOk()
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.total_rows', 10)
            ->assertJsonPath('data.processed_rows', 5);
    }

    public function test_import_finish_requires_no_unresolved_conflicts(): void
    {
        $this->actingAs($this->admin);

        $batch = ImportBatch::create([
            'user_id' => $this->admin->id,
            'entity' => 'services',
            'filename' => 'test.csv',
            'stored_path' => 'imports/test.csv',
            'format' => 'csv',
            'status' => 'pending_review',
            'total_rows' => 1,
            'processed_rows' => 1,
            'success_count' => 0,
            'error_count' => 0,
            'duplicate_count' => 1,
            'conflict_strategy' => 'admin_override',
        ]);

        $batch->conflicts()->create([
            'entity' => 'services',
            'existing_id' => 1,
            'incoming_data' => ['title' => 'test'],
            'existing_data' => ['title' => 'existing'],
            'similarity_score' => 0.9,
            'match_type' => 'title_similarity',
            'resolved' => false,
        ]);

        $response = $this->withSession(['step_up_verified_at' => now()])
            ->postJson("/api/admin/import/{$batch->id}/finish");

        $response->assertStatus(422)
            ->assertJsonPath('error', 'Please resolve all conflicts before finishing.');
    }
}
