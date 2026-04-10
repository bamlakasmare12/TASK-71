<?php

namespace Tests\Feature\Integration;

use App\Enums\UserRole;
use App\Models\ImportBatch;
use App\Models\ImportConflict;
use App\Models\Service;
use App\Models\User;
use App\Services\ImportExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Import sync integration tests.
 * Validates pg_trgm similarity detection catches near-duplicates
 * and conflict resolution works correctly.
 */
class ImportSyncConflictTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private ImportExportService $service;

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

        $this->actingAs($this->admin);
        $this->service = app(ImportExportService::class);
    }

    /**
     * An import row with an identical title to an existing service
     * must be detected as a duplicate (exact or near-exact match).
     */
    public function test_exact_title_detected_as_duplicate(): void
    {
        $existing = Service::create([
            'title' => 'Statistical Analysis Consultation',
            'description' => 'Existing service',
            'service_type' => 'consultation',
            'price' => 0,
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $batch = $this->createBatch();

        $result = $this->service->processRow(
            ['title' => 'Statistical Analysis Consultation', 'description' => 'Incoming duplicate', 'service_type' => 'consultation', 'price' => '0'],
            'services',
            ['title' => 'title', 'description' => 'description', 'service_type' => 'service_type', 'price' => 'price'],
            'skip',
            $batch,
        );

        $this->assertEquals('skipped', $result['status']);
    }

    /**
     * A slightly different title (e.g. typo, abbreviation) that exceeds
     * the trigram similarity threshold should still be caught.
     */
    public function test_similar_title_detected_by_trigram(): void
    {
        Service::create([
            'title' => 'Research Methodology Consultation',
            'description' => 'Existing',
            'service_type' => 'consultation',
            'price' => 0,
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $batch = $this->createBatch();

        // Slightly different title — trigram similarity should catch it
        $result = $this->service->processRow(
            ['title' => 'Research Methodology Consulation', 'description' => 'Typo version', 'service_type' => 'consultation', 'price' => '0'],
            'services',
            ['title' => 'title', 'description' => 'description', 'service_type' => 'service_type', 'price' => 'price'],
            'skip',
            $batch,
        );

        $this->assertEquals('skipped', $result['status'], 'Trigram should catch the near-duplicate');
    }

    /**
     * A completely different title should NOT be detected as duplicate.
     */
    public function test_unrelated_title_creates_new_record(): void
    {
        Service::create([
            'title' => 'Statistical Analysis Consultation',
            'description' => 'Existing',
            'service_type' => 'consultation',
            'price' => 0,
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $batch = $this->createBatch();

        $result = $this->service->processRow(
            ['title' => '3D Printer Safety Training', 'description' => 'Completely different', 'service_type' => 'training', 'price' => '50'],
            'services',
            ['title' => 'title', 'description' => 'description', 'service_type' => 'service_type', 'price' => 'price'],
            'skip',
            $batch,
        );

        $this->assertEquals('created', $result['status']);
        $this->assertDatabaseHas('services', ['title' => '3D Printer Safety Training']);
    }

    /**
     * With admin_override strategy, a duplicate should create an ImportConflict
     * record for manual resolution instead of auto-skipping/overwriting.
     */
    public function test_admin_override_creates_conflict_record(): void
    {
        Service::create([
            'title' => 'Grant Writing Workshop',
            'description' => 'Existing',
            'service_type' => 'training',
            'price' => 50,
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $batch = $this->createBatch();

        $result = $this->service->processRow(
            ['title' => 'Grant Writing Workshop', 'description' => 'Updated version', 'service_type' => 'training', 'price' => '75'],
            'services',
            ['title' => 'title', 'description' => 'description', 'service_type' => 'service_type', 'price' => 'price'],
            'admin_override',
            $batch,
        );

        $this->assertEquals('conflict', $result['status']);

        // Verify conflict record was created
        $conflict = ImportConflict::where('import_batch_id', $batch->id)->first();
        $this->assertNotNull($conflict);
        $this->assertFalse($conflict->resolved);
        $this->assertEquals('Grant Writing Workshop', $conflict->incoming_data['title']);
    }

    /**
     * Resolving a conflict with 'overwrite' must update the existing record.
     */
    public function test_resolve_conflict_overwrite_updates_record(): void
    {
        $existing = Service::create([
            'title' => 'Peer Review Service',
            'description' => 'Old description',
            'service_type' => 'editorial',
            'price' => 20,
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $conflict = ImportConflict::create([
            'import_batch_id' => $this->createBatch()->id,
            'entity' => 'services',
            'existing_id' => $existing->id,
            'incoming_data' => ['title' => 'Peer Review Service', 'description' => 'New description', 'price' => '35'],
            'existing_data' => $existing->toArray(),
            'similarity_score' => 1.0,
            'match_type' => 'exact_id',
        ]);

        $this->service->resolveConflict($conflict, 'overwrite');

        $conflict->refresh();
        $this->assertTrue($conflict->resolved);
        $this->assertEquals('overwrite', $conflict->resolution);

        $existing->refresh();
        $this->assertEquals('New description', $existing->description);
    }

    /**
     * Resolving a conflict with 'skip' must leave the existing record untouched.
     */
    public function test_resolve_conflict_skip_preserves_existing(): void
    {
        $existing = Service::create([
            'title' => 'Lab Equipment Booking',
            'description' => 'Original description',
            'service_type' => 'equipment',
            'price' => 15,
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $conflict = ImportConflict::create([
            'import_batch_id' => $this->createBatch()->id,
            'entity' => 'services',
            'existing_id' => $existing->id,
            'incoming_data' => ['title' => 'Lab Equipment Booking', 'description' => 'New data', 'price' => '25'],
            'existing_data' => $existing->toArray(),
            'similarity_score' => 1.0,
            'match_type' => 'exact_id',
        ]);

        $this->service->resolveConflict($conflict, 'skip');

        $existing->refresh();
        $this->assertEquals('Original description', $existing->description);
        $this->assertEquals('15.00', $existing->price);
    }

    /**
     * prefer_newest skips if existing record is more recent than import.
     */
    public function test_prefer_newest_skips_when_existing_is_newer(): void
    {
        $existing = Service::create([
            'title' => 'Fresh Service',
            'description' => 'Very recent',
            'service_type' => 'consultation',
            'price' => 0,
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
            'updated_at' => now(), // Fresh
        ]);

        $batch = $this->createBatch();

        $result = $this->service->processRow(
            [
                'title' => 'Fresh Service',
                'description' => 'Stale import',
                'service_type' => 'consultation',
                'price' => '0',
                'updated_at' => now()->subDays(10)->toIso8601String(), // Older
            ],
            'services',
            ['title' => 'title', 'description' => 'description', 'service_type' => 'service_type', 'price' => 'price', 'updated_at' => 'updated_at'],
            'prefer_newest',
            $batch,
        );

        $this->assertEquals('skipped', $result['status']);

        // Existing should be untouched
        $existing->refresh();
        $this->assertEquals('Very recent', $existing->description);
    }

    /**
     * User import detects exact username match as duplicate.
     */
    public function test_user_import_detects_username_duplicate(): void
    {
        $batch = $this->createBatch('users');

        $result = $this->service->processRow(
            ['username' => 'admin', 'name' => 'Imposter', 'email' => 'imposter@test.local', 'role' => 'admin'],
            'users',
            ['username' => 'username', 'name' => 'name', 'email' => 'email', 'role' => 'role'],
            'skip',
            $batch,
        );

        $this->assertEquals('skipped', $result['status']);
    }

    private function createBatch(string $entity = 'services'): ImportBatch
    {
        return ImportBatch::create([
            'user_id' => $this->admin->id,
            'entity' => $entity,
            'filename' => 'test.csv',
            'format' => 'csv',
            'status' => 'processing',
            'total_rows' => 1,
            'conflict_strategy' => 'skip',
        ]);
    }
}
