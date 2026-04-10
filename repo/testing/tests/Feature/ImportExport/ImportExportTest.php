<?php

namespace Tests\Feature\ImportExport;

use App\Enums\UserRole;
use App\Models\ImportBatch;
use App\Models\Service;
use App\Models\User;
use App\Services\ImportExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class ImportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $learner;
    private ImportExportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'username' => 'admin',
            'name' => 'Admin User',
            'email' => 'admin@test.local',
            'password' => 'Admin123!@#456',
            'role' => UserRole::Admin,
            'password_updated_at' => now(),
            'is_active' => true,
        ]);

        $this->learner = User::create([
            'username' => 'learner',
            'name' => 'Learner User',
            'email' => 'learner@test.local',
            'password' => 'Learner123!@#456',
            'role' => UserRole::Learner,
            'password_updated_at' => now(),
            'is_active' => true,
        ]);

        $this->service = app(ImportExportService::class);
    }

    // ── EXPORT ──

    public function test_export_services_as_csv(): void
    {
        Service::create([
            'title' => 'Test Service',
            'description' => 'Description',
            'service_type' => 'consultation',
            'target_audience' => ['faculty'],
            'price' => 0,
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $csv = $this->service->exportServices('csv');

        $this->assertStringContainsString('title', $csv);
        $this->assertStringContainsString('Test Service', $csv);
    }

    public function test_export_services_as_json(): void
    {
        Service::create([
            'title' => 'JSON Service',
            'description' => 'Desc',
            'service_type' => 'editorial',
            'target_audience' => ['graduate'],
            'price' => 25.00,
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $json = $this->service->exportServices('json');
        $data = json_decode($json, true);

        $this->assertCount(1, $data);
        $this->assertEquals('JSON Service', $data[0]['title']);
    }

    public function test_export_users_excludes_passwords(): void
    {
        $csv = $this->service->exportUsers('csv');

        $this->assertStringNotContainsString('password', strtolower($csv));
        $this->assertStringContainsString('admin', $csv);
    }

    public function test_export_users_sensitive_only_when_opted_in(): void
    {
        $this->admin->update(['phone_encrypted' => '555-1234']);

        $csvWithout = $this->service->exportUsers('csv');
        $this->assertStringNotContainsString('phone', $csvWithout);

        $csvWith = $this->service->exportUsers('csv', ['include_sensitive' => true]);
        $this->assertStringContainsString('phone', $csvWith);
    }

    public function test_incremental_export_filters_by_date(): void
    {
        // Create old service with timestamps disabled to prevent auto-setting
        $oldService = new Service([
            'title' => 'Old Service',
            'description' => 'Old',
            'service_type' => 'consultation',
            'price' => 0,
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
        $oldService->timestamps = false;
        $oldService->created_at = now()->subDays(30);
        $oldService->updated_at = now()->subDays(30);
        $oldService->save();

        Service::create([
            'title' => 'New Service',
            'slug' => 'new-service',
            'description' => 'New',
            'service_type' => 'consultation',
            'price' => 0,
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $csv = $this->service->exportServices('csv', ['since' => now()->subDay()->toDateString()]);

        $this->assertStringContainsString('New Service', $csv);
        $this->assertStringNotContainsString('Old Service', $csv);
    }

    // ── IMPORT ──

    public function test_parse_csv_file(): void
    {
        $path = $this->createTempCsv([
            ['title' => 'Imported Service', 'description' => 'Desc', 'service_type' => 'consultation', 'price' => '0'],
        ]);

        $rows = $this->service->parseFile($path, 'csv');

        $this->assertCount(1, $rows);
        $this->assertEquals('Imported Service', $rows[0]['title']);
    }

    public function test_parse_json_file(): void
    {
        $path = $this->createTempJson([
            ['title' => 'JSON Import', 'description' => 'Desc', 'service_type' => 'training', 'price' => '50'],
        ]);

        $rows = $this->service->parseFile($path, 'json');

        $this->assertCount(1, $rows);
        $this->assertEquals('JSON Import', $rows[0]['title']);
    }

    public function test_detect_headers_from_csv(): void
    {
        $path = $this->createTempCsv([
            ['title' => 'Test', 'description' => 'Desc', 'price' => '10'],
        ]);

        $headers = $this->service->detectHeaders($path, 'csv');

        $this->assertContains('title', $headers);
        $this->assertContains('description', $headers);
        $this->assertContains('price', $headers);
    }

    public function test_process_row_creates_new_service(): void
    {
        $this->actingAs($this->admin);

        $batch = ImportBatch::create([
            'user_id' => $this->admin->id,
            'entity' => 'services',
            'filename' => 'test.csv',
            'format' => 'csv',
            'status' => 'processing',
            'total_rows' => 1,
            'conflict_strategy' => 'skip',
        ]);

        $result = $this->service->processRow(
            ['title' => 'Brand New Service', 'description' => 'Imported desc', 'service_type' => 'consultation', 'price' => '0'],
            'services',
            ['title' => 'title', 'description' => 'description', 'service_type' => 'service_type', 'price' => 'price'],
            'skip',
            $batch,
        );

        $this->assertEquals('created', $result['status']);
        $this->assertDatabaseHas('services', ['title' => 'Brand New Service']);
    }

    public function test_process_row_detects_duplicate_and_skips(): void
    {
        $this->actingAs($this->admin);

        Service::create([
            'title' => 'Existing Service',
            'description' => 'Already here',
            'service_type' => 'consultation',
            'price' => 0,
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $batch = ImportBatch::create([
            'user_id' => $this->admin->id,
            'entity' => 'services',
            'filename' => 'test.csv',
            'format' => 'csv',
            'status' => 'processing',
            'total_rows' => 1,
            'conflict_strategy' => 'skip',
        ]);

        $result = $this->service->processRow(
            ['title' => 'Existing Service', 'description' => 'Duplicate', 'service_type' => 'consultation', 'price' => '0'],
            'services',
            ['title' => 'title', 'description' => 'description', 'service_type' => 'service_type', 'price' => 'price'],
            'skip',
            $batch,
        );

        $this->assertEquals('skipped', $result['status']);
    }

    // ── ACCESS CONTROL ──

    public function test_import_page_requires_admin(): void
    {
        $this->actingAs($this->learner);
        $response = $this->get('/admin/import');
        $response->assertStatus(403);
    }

    public function test_import_page_accessible_by_admin(): void
    {
        $this->actingAs($this->admin);
        $response = $this->withSession(['step_up_verified_at' => now()])
            ->get('/admin/import');
        $response->assertStatus(200);
    }

    public function test_export_requires_step_up_auth(): void
    {
        $this->actingAs($this->admin);
        $response = $this->get('/admin/export');
        // Should redirect to step-up verification
        $response->assertRedirect(route('auth.step-up'));
    }

    // ── HELPERS ──

    private function createTempCsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'csv_');
        $handle = fopen($path, 'w');
        fputcsv($handle, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
        return $path;
    }

    private function createTempJson(array $data): string
    {
        $path = tempnam(sys_get_temp_dir(), 'json_');
        file_put_contents($path, json_encode($data));
        return $path;
    }
}
