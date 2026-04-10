<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\ImportConflict;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ImportExportService
{
    private const SIMILARITY_THRESHOLD = 0.4;

    public function __construct(private DataDictionaryService $dictService) {}

    private function validateImportData(array $data, string $entity): array
    {
        $dynamicRules = $this->dictService->getValidationRules($entity === 'services' ? 'service' : $entity);
        if (empty($dynamicRules)) {
            return [];
        }
        $validator = Validator::make($data, $dynamicRules);
        return $validator->fails() ? $validator->errors()->all() : [];
    }

    // ── EXPORT ──

    public function exportServices(string $format, array $options = []): string
    {
        $query = Service::with('tags')->active();

        if (!empty($options['since'])) {
            $query->where('updated_at', '>=', $options['since']);
        }

        $services = $query->get()->map(function (Service $s) {
            return [
                'id' => $s->id,
                'title' => $s->title,
                'slug' => $s->slug,
                'description' => $s->description,
                'service_type' => $s->service_type,
                'eligibility_notes' => $s->eligibility_notes,
                'target_audience' => implode('|', $s->target_audience ?? []),
                'price' => $s->price,
                'category' => $s->category,
                'project_number' => $s->project_number,
                'patent_number' => $s->patent_number,
                'tags' => $s->tags->pluck('name')->implode('|'),
                'is_active' => $s->is_active ? 'true' : 'false',
                'created_at' => $s->created_at->toIso8601String(),
                'updated_at' => $s->updated_at->toIso8601String(),
            ];
        });

        return $format === 'json'
            ? $this->toJson($services)
            : $this->toCsv($services);
    }

    public function exportUsers(string $format, array $options = []): string
    {
        $query = User::query();

        if (!empty($options['since'])) {
            $query->where('updated_at', '>=', $options['since']);
        }

        $includeSensitive = !empty($options['include_sensitive']);

        $users = $query->get()->map(function (User $u) use ($includeSensitive) {
            $row = [
                'id' => $u->id,
                'username' => $u->username,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role->value,
                'is_active' => $u->is_active ? 'true' : 'false',
                'created_at' => $u->created_at->toIso8601String(),
                'updated_at' => $u->updated_at->toIso8601String(),
            ];

            // Only include decrypted sensitive fields if explicitly mapped by admin
            if ($includeSensitive) {
                $row['phone'] = $u->phone_encrypted;
                $row['external_id'] = $u->external_id_encrypted;
            }

            // NEVER export password hashes
            return $row;
        });

        return $format === 'json'
            ? $this->toJson($users)
            : $this->toCsv($users);
    }

    // ── IMPORT PARSING ──

    public function parseFile(string $path, string $format): array
    {
        return $format === 'json'
            ? $this->parseJson($path)
            : $this->parseCsv($path);
    }

    public function detectHeaders(string $path, string $format): array
    {
        $rows = $this->parseFile($path, $format);

        if (empty($rows)) {
            return [];
        }

        return array_keys($rows[0]);
    }

    public function getDestinationFields(string $entity): array
    {
        return match ($entity) {
            'services' => [
                'title', 'description', 'service_type', 'eligibility_notes',
                'target_audience', 'price', 'category', 'project_number',
                'patent_number', 'tags', 'is_active',
            ],
            'users' => [
                'username', 'name', 'email', 'role', 'is_active',
                'phone_encrypted', 'external_id_encrypted',
            ],
            default => [],
        };
    }

    // ── DUPLICATE DETECTION ──

    public function detectDuplicates(array $row, string $entity, array $fieldMapping): Collection
    {
        $conflicts = collect();

        if ($entity === 'services') {
            $conflicts = $this->detectServiceDuplicates($row, $fieldMapping);
        } elseif ($entity === 'users') {
            $conflicts = $this->detectUserDuplicates($row, $fieldMapping);
        }

        return $conflicts;
    }

    private function detectServiceDuplicates(array $row, array $fieldMapping): Collection
    {
        $conflicts = collect();

        // Exact ID match
        $mappedId = $this->getMappedValue($row, $fieldMapping, 'id');
        if ($mappedId !== null) {
            $existing = Service::find($mappedId);
            if ($existing) {
                $conflicts->push([
                    'existing_id' => $existing->id,
                    'existing_data' => $existing->toArray(),
                    'similarity_score' => 1.0,
                    'match_type' => 'exact_id',
                ]);
                return $conflicts;
            }
        }

        // Exact project_number match
        $projectNumber = $this->getMappedValue($row, $fieldMapping, 'project_number');
        if ($projectNumber !== null && $projectNumber !== '') {
            $existing = Service::where('project_number', $projectNumber)->first();
            if ($existing) {
                $conflicts->push([
                    'existing_id' => $existing->id,
                    'existing_data' => $existing->toArray(),
                    'similarity_score' => 1.0,
                    'match_type' => 'project_number',
                ]);
                return $conflicts;
            }
        }

        // Exact patent_number match
        $patentNumber = $this->getMappedValue($row, $fieldMapping, 'patent_number');
        if ($patentNumber !== null && $patentNumber !== '') {
            $existing = Service::where('patent_number', $patentNumber)->first();
            if ($existing) {
                $conflicts->push([
                    'existing_id' => $existing->id,
                    'existing_data' => $existing->toArray(),
                    'similarity_score' => 1.0,
                    'match_type' => 'patent_number',
                ]);
                return $conflicts;
            }
        }

        $mappedTitle = $this->getMappedValue($row, $fieldMapping, 'title');
        if ($mappedTitle === null) {
            return $conflicts;
        }

        $normalizedTitle = $this->normalizeTitle($mappedTitle);

        // Trigram similarity on normalized title
        $similar = DB::select(
            "SELECT id, title, similarity(lower(title), ?) as score
             FROM services
             WHERE similarity(lower(title), ?) >= ?
             ORDER BY score DESC
             LIMIT 5",
            [$normalizedTitle, $normalizedTitle, self::SIMILARITY_THRESHOLD]
        );

        foreach ($similar as $match) {
            $existing = Service::find($match->id);
            if ($existing) {
                $conflicts->push([
                    'existing_id' => $existing->id,
                    'existing_data' => $existing->toArray(),
                    'similarity_score' => round((float) $match->score, 4),
                    'match_type' => 'title_similarity',
                ]);
            }
        }

        return $conflicts;
    }

    private function detectUserDuplicates(array $row, array $fieldMapping): Collection
    {
        $conflicts = collect();

        // Exact username match
        $username = $this->getMappedValue($row, $fieldMapping, 'username');
        if ($username !== null) {
            $existing = User::where('username', $username)->first();
            if ($existing) {
                $conflicts->push([
                    'existing_id' => $existing->id,
                    'existing_data' => $existing->makeVisible([])->toArray(),
                    'similarity_score' => 1.0,
                    'match_type' => 'exact_id',
                ]);
                return $conflicts;
            }
        }

        // Exact email match
        $email = $this->getMappedValue($row, $fieldMapping, 'email');
        if ($email !== null) {
            $existing = User::where('email', $email)->first();
            if ($existing) {
                $conflicts->push([
                    'existing_id' => $existing->id,
                    'existing_data' => $existing->makeVisible([])->toArray(),
                    'similarity_score' => 1.0,
                    'match_type' => 'exact_id',
                ]);
            }
        }

        return $conflicts;
    }

    // ── ROW PROCESSING ──

    public function processRow(
        array $row,
        string $entity,
        array $fieldMapping,
        string $conflictStrategy,
        ImportBatch $batch,
    ): array {
        $mapped = $this->applyMapping($row, $fieldMapping);
        $duplicates = $this->detectDuplicates($row, $entity, $fieldMapping);

        if ($duplicates->isNotEmpty()) {
            $best = $duplicates->first();

            if ($conflictStrategy === 'skip') {
                return ['status' => 'skipped', 'reason' => 'duplicate'];
            }

            if ($conflictStrategy === 'prefer_newest') {
                return $this->resolvePreferNewest($mapped, $best, $entity, $batch);
            }

            // admin_override — create conflict for manual resolution
            ImportConflict::create([
                'import_batch_id' => $batch->id,
                'entity' => $entity,
                'existing_id' => $best['existing_id'],
                'incoming_data' => $mapped,
                'existing_data' => $best['existing_data'],
                'similarity_score' => $best['similarity_score'],
                'match_type' => $best['match_type'],
            ]);

            return ['status' => 'conflict', 'conflict_id' => $best['existing_id']];
        }

        // No duplicates — create new record
        return $this->createRecord($mapped, $entity);
    }

    private function resolvePreferNewest(array $mapped, array $duplicate, string $entity, ImportBatch $batch): array
    {
        $existingId = $duplicate['existing_id'];
        $existingData = $duplicate['existing_data'];

        // Check updated_at for incremental sync
        $incomingUpdatedAt = $mapped['updated_at'] ?? null;
        $existingUpdatedAt = $existingData['updated_at'] ?? null;

        if ($incomingUpdatedAt && $existingUpdatedAt && $incomingUpdatedAt <= $existingUpdatedAt) {
            return ['status' => 'skipped', 'reason' => 'existing_is_newer'];
        }

        // Overwrite with incoming data
        return $this->updateRecord($existingId, $mapped, $entity);
    }

    private function createRecord(array $data, string $entity): array
    {
        $validationErrors = $this->validateImportData($data, $entity);
        if (!empty($validationErrors)) {
            return ['status' => 'error', 'error' => 'Validation failed: ' . implode('; ', $validationErrors)];
        }

        try {
            if ($entity === 'services') {
                $this->createServiceFromImport($data);
            } elseif ($entity === 'users') {
                $this->createUserFromImport($data);
            }

            return ['status' => 'created'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    private function updateRecord(int $id, array $data, string $entity): array
    {
        $validationErrors = $this->validateImportData($data, $entity);
        if (!empty($validationErrors)) {
            return ['status' => 'error', 'error' => 'Validation failed: ' . implode('; ', $validationErrors)];
        }

        try {
            if ($entity === 'services') {
                $service = Service::findOrFail($id);
                $updateData = array_filter($data, fn($v) => $v !== null && $v !== '');
                unset($updateData['id'], $updateData['created_at'], $updateData['updated_at'], $updateData['tags']);
                $service->update($updateData);

                if (!empty($data['tags'])) {
                    $this->syncTagsFromImport($service, $data['tags']);
                }
            } elseif ($entity === 'users') {
                $user = User::findOrFail($id);
                $updateData = array_filter($data, fn($v) => $v !== null && $v !== '');
                unset($updateData['id'], $updateData['password'], $updateData['created_at'], $updateData['updated_at']);
                $user->update($updateData);
            }

            return ['status' => 'updated'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    public function resolveConflict(ImportConflict $conflict, string $resolution): void
    {
        if ($resolution === 'overwrite') {
            $this->updateRecord(
                $conflict->existing_id,
                $conflict->incoming_data,
                $conflict->entity,
            );
        }
        // 'skip' = do nothing

        $conflict->update([
            'resolution' => $resolution,
            'resolved' => true,
        ]);
    }

    // ── HELPERS ──

    private function createServiceFromImport(array $data): Service
    {
        $audience = $data['target_audience'] ?? '';
        if (is_string($audience)) {
            $audience = array_filter(explode('|', $audience));
        }

        $service = Service::create([
            'title' => $data['title'],
            'slug' => Str::slug($data['title']),
            'description' => $data['description'] ?? '',
            'service_type' => $data['service_type'] ?? 'consultation',
            'eligibility_notes' => $data['eligibility_notes'] ?? null,
            'target_audience' => $audience,
            'price' => (float) ($data['price'] ?? 0),
            'category' => $data['category'] ?? null,
            'project_number' => $data['project_number'] ?? null,
            'patent_number' => $data['patent_number'] ?? null,
            'is_active' => $this->toBool($data['is_active'] ?? true),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        if (!empty($data['tags'])) {
            $this->syncTagsFromImport($service, $data['tags']);
        }

        return $service;
    }

    private function createUserFromImport(array $data): User
    {
        return User::create([
            'username' => $data['username'],
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Str::random(16) . '!A1a', // temp password, must be changed
            'role' => $data['role'] ?? 'learner',
            'is_active' => $this->toBool($data['is_active'] ?? true),
            'phone_encrypted' => $data['phone_encrypted'] ?? null,
            'external_id_encrypted' => $data['external_id_encrypted'] ?? null,
            'password_updated_at' => null, // Force password change on first login
        ]);
    }

    private function syncTagsFromImport(Service $service, string $tagsString): void
    {
        $tagNames = array_filter(array_map('trim', explode('|', $tagsString)));
        $tagIds = collect($tagNames)->map(function (string $name) {
            return \App\Models\Tag::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            )->id;
        });

        $service->tags()->sync($tagIds);
    }

    private function applyMapping(array $row, array $fieldMapping): array
    {
        $mapped = [];

        foreach ($fieldMapping as $sourceCol => $destCol) {
            if ($destCol && isset($row[$sourceCol])) {
                $mapped[$destCol] = $row[$sourceCol];
            }
        }

        return $mapped;
    }

    private function getMappedValue(array $row, array $fieldMapping, string $destField): ?string
    {
        $sourceCol = array_search($destField, $fieldMapping);

        if ($sourceCol === false) {
            return $row[$destField] ?? null;
        }

        return $row[$sourceCol] ?? null;
    }

    private function normalizeTitle(string $title): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $title)));
    }

    private function toBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['true', '1', 'yes'], true);
    }

    private function toJson(Collection $data): string
    {
        return json_encode($data->values()->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    private function toCsv(Collection $data): string
    {
        if ($data->isEmpty()) {
            return '';
        }

        $output = fopen('php://temp', 'r+');
        fputcsv($output, array_keys($data->first()));

        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    private function parseCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new \RuntimeException('Cannot open file: ' . $path);
        }

        $headers = fgetcsv($handle);

        if ($headers === false) {
            fclose($handle);
            return [];
        }

        // Normalize headers
        $headers = array_map(fn($h) => trim(strtolower($h)), $headers);

        while (($line = fgetcsv($handle)) !== false) {
            if (count($line) === count($headers)) {
                $rows[] = array_combine($headers, $line);
            }
        }

        fclose($handle);

        return $rows;
    }

    private function parseJson(string $path): array
    {
        $content = file_get_contents($path);
        $data = json_decode($content, true);

        if (!is_array($data)) {
            throw new \RuntimeException('Invalid JSON file.');
        }

        // Handle both flat array and wrapped {"data": [...]}
        if (isset($data['data']) && is_array($data['data'])) {
            return $data['data'];
        }

        // If associative (single record), wrap it
        if (!empty($data) && !isset($data[0])) {
            return [$data];
        }

        return $data;
    }
}
