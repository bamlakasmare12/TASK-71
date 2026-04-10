<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Jobs\ProcessImportChunk;
use App\Models\DataDictionary;
use App\Models\FormRule;
use App\Models\ImportBatch;
use App\Models\ImportConflict;
use App\Models\User;
use App\Services\AuditService;
use App\Services\DataDictionaryService;
use App\Services\DictionaryQueryService;
use App\Services\ImportExportService;
use App\Services\UserQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminController extends Controller
{
    public function __construct(
        private UserQueryService $userQuery,
        private DictionaryQueryService $dictQuery,
    ) {}

    public function users(Request $request): AnonymousResourceCollection
    {
        $users = $this->userQuery->listUsers([
            'search' => $request->input('search'),
            'role' => $request->input('role'),
        ]);

        return UserResource::collection($users);
    }

    public function changeRole(Request $request, User $user, AuditService $audit): UserResource|JsonResponse
    {
        $request->validate([
            'role' => 'required|in:learner,editor,admin',
        ]);

        $oldRole = $user->role->value;
        $newRole = $request->input('role');

        $user->update(['role' => UserRole::from($newRole)]);

        $audit->log(AuditAction::RoleChange, $request->user()->id, [
            'target_user_id' => $user->id,
            'old_role' => $oldRole,
            'new_role' => $newRole,
        ], 'critical');

        return new UserResource($user->fresh());
    }

    public function deactivateUser(Request $request, User $user, AuditService $audit): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['error' => 'Cannot deactivate your own account.'], 422);
        }

        $audit->log(AuditAction::AccountDeleted, $request->user()->id, [
            'deleted_user_id' => $user->id,
            'deleted_username' => $user->username,
        ], 'critical');

        $user->update(['is_active' => false]);

        return response()->json(['message' => 'Account deactivated.']);
    }

    public function dictionaries(Request $request): JsonResponse
    {
        $entries = $this->dictQuery->allDictionaries($request->input('type'));

        return response()->json(['data' => $entries]);
    }

    public function storeDictionary(Request $request, DataDictionaryService $dictService): JsonResponse
    {
        $request->validate([
            'type' => 'required|string|max:100',
            'key' => 'required|string|max:100',
            'label' => 'required|string|max:255',
            'metadata' => 'nullable|array',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $dict = DataDictionary::create($request->only(['type', 'key', 'label', 'metadata', 'sort_order', 'is_active']));
        $dictService->clearCache($request->input('type'));

        return response()->json(['data' => $dict], 201);
    }

    public function updateDictionary(Request $request, DataDictionary $dictionary, DataDictionaryService $dictService): JsonResponse
    {
        $request->validate([
            'label' => 'sometimes|required|string|max:255',
            'metadata' => 'nullable|array',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $dictionary->update($request->only(['label', 'metadata', 'sort_order', 'is_active']));
        $dictService->clearCache($dictionary->type);

        return response()->json(['data' => $dictionary->fresh()]);
    }

    public function deleteDictionary(DataDictionary $dictionary, DataDictionaryService $dictService): JsonResponse
    {
        $type = $dictionary->type;
        $dictionary->delete();
        $dictService->clearCache($type);

        return response()->json(['message' => 'Dictionary entry deleted.']);
    }

    public function formRules(Request $request): JsonResponse
    {
        $rules = $this->dictQuery->allFormRules($request->input('entity'));

        return response()->json(['data' => $rules]);
    }

    public function storeFormRule(Request $request): JsonResponse
    {
        $request->validate([
            'entity' => 'required|string|max:100',
            'field' => 'required|string|max:100',
            'rules' => 'required|array',
            'is_active' => 'boolean',
        ]);

        $rule = FormRule::create($request->only(['entity', 'field', 'rules', 'is_active']));

        return response()->json(['data' => $rule], 201);
    }

    public function updateFormRule(Request $request, FormRule $formRule): JsonResponse
    {
        $request->validate([
            'rules' => 'sometimes|required|array',
            'is_active' => 'boolean',
        ]);

        $formRule->update($request->only(['rules', 'is_active']));

        return response()->json(['data' => $formRule->fresh()]);
    }

    public function deleteFormRule(FormRule $formRule): JsonResponse
    {
        $formRule->delete();

        return response()->json(['message' => 'Form rule deleted.']);
    }

    // ── IMPORT ──

    public function importUpload(Request $request, ImportExportService $service): JsonResponse
    {
        $request->validate([
            'file' => 'required_without:stored_path|file|max:10240|mimes:csv,txt,json',
            'stored_path' => 'required_without:file|string',
            'original_filename' => 'nullable|string',
            'entity' => 'required|in:services,users',
            'conflict_strategy' => 'required|in:skip,prefer_newest,admin_override',
        ]);

        // Support both direct file upload and pre-stored path (from Livewire)
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $ext = $file->getClientOriginalExtension();
            $format = in_array($ext, ['json']) ? 'json' : 'csv';
            $path = $file->store('imports', 'local');
            $originalFilename = $file->getClientOriginalName();
        } else {
            $path = $request->input('stored_path');
            $format = str_ends_with(strtolower($path), '.json') ? 'json' : 'csv';
            $originalFilename = $request->input('original_filename', basename($path));
        }

        $fullPath = storage_path('app/' . $path);

        $headers = $service->detectHeaders($fullPath, $format);

        if (empty($headers)) {
            return response()->json(['error' => 'File appears to be empty or has no recognizable headers.'], 422);
        }

        $rows = $service->parseFile($fullPath, $format);

        // Auto-map matching field names
        $destinationFields = $service->getDestinationFields($request->input('entity'));
        $fieldMapping = [];
        foreach ($headers as $header) {
            $normalized = strtolower(str_replace([' ', '-'], '_', $header));
            $fieldMapping[$header] = in_array($normalized, $destinationFields) ? $normalized : '';
        }

        $batch = ImportBatch::create([
            'user_id' => $request->user()->id,
            'entity' => $request->input('entity'),
            'filename' => $originalFilename,
            'stored_path' => $path,
            'format' => $format,
            'status' => 'mapping',
            'total_rows' => count($rows),
            'conflict_strategy' => $request->input('conflict_strategy'),
        ]);

        return response()->json([
            'data' => [
                'batch_id' => $batch->id,
                'source_headers' => $headers,
                'destination_fields' => $destinationFields,
                'field_mapping' => $fieldMapping,
                'total_rows' => count($rows),
            ],
        ], 201);
    }

    public function importProcess(Request $request, int $batchId, ImportExportService $service): JsonResponse
    {
        $request->validate([
            'field_mapping' => 'required|array|min:1',
            'conflict_strategy' => 'nullable|in:skip,prefer_newest,admin_override',
        ]);

        $batch = ImportBatch::findOrFail($batchId);

        $activeMappings = array_filter($request->input('field_mapping'));
        if (empty($activeMappings)) {
            return response()->json(['error' => 'Please map at least one field.'], 422);
        }

        $batch->update([
            'field_mapping' => $request->input('field_mapping'),
            'conflict_strategy' => $request->input('conflict_strategy', $batch->conflict_strategy),
            'status' => 'processing',
        ]);

        $fullPath = storage_path('app/' . $batch->stored_path);

        if (!file_exists($fullPath)) {
            return response()->json(['error' => 'Import file not found.'], 404);
        }

        $rows = $service->parseFile($fullPath, $batch->format);
        $chunks = array_chunk($rows, 50);

        $startIndex = 0;
        foreach ($chunks as $chunk) {
            ProcessImportChunk::dispatch($batch->id, $chunk, $startIndex);
            $startIndex += count($chunk);
        }

        return response()->json([
            'data' => [
                'batch_id' => $batch->id,
                'total_rows' => $batch->total_rows,
                'chunks' => count($chunks),
                'status' => 'processing',
            ],
        ]);
    }

    public function importStatus(int $batchId): JsonResponse
    {
        $batch = ImportBatch::with('conflicts')->findOrFail($batchId);

        return response()->json([
            'data' => [
                'id' => $batch->id,
                'status' => $batch->status,
                'total_rows' => $batch->total_rows,
                'processed_rows' => $batch->processed_rows,
                'success_count' => $batch->success_count,
                'error_count' => $batch->error_count,
                'duplicate_count' => $batch->duplicate_count,
                'error_log' => $batch->error_log,
                'unresolved_conflicts' => $batch->unresolvedConflicts()->count(),
            ],
        ]);
    }

    public function importResolveConflict(Request $request, int $conflictId, ImportExportService $service): JsonResponse
    {
        $request->validate([
            'resolution' => 'required|in:overwrite,skip',
        ]);

        $conflict = ImportConflict::findOrFail($conflictId);
        $service->resolveConflict($conflict, $request->input('resolution'));

        return response()->json(['message' => 'Conflict resolved.']);
    }

    public function importFinish(int $batchId): JsonResponse
    {
        $batch = ImportBatch::findOrFail($batchId);

        if ($batch->unresolvedConflicts()->count() > 0) {
            return response()->json(['error' => 'Please resolve all conflicts before finishing.'], 422);
        }

        $batch->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return response()->json(['message' => 'Import completed.']);
    }

    // ── EXPORT ──

    public function exportData(Request $request, ImportExportService $service, AuditService $audit): JsonResponse
    {
        $request->validate([
            'entity' => 'required|in:services,users',
            'format' => 'required|in:csv,json',
            'include_sensitive' => 'boolean',
            'since' => 'nullable|date',
        ]);

        $options = [];
        if ($request->input('since')) {
            $options['since'] = $request->input('since');
        }
        if ($request->boolean('include_sensitive')) {
            $options['include_sensitive'] = true;
        }

        $entity = $request->input('entity');
        $format = $request->input('format');

        $content = match ($entity) {
            'services' => $service->exportServices($format, $options),
            'users' => $service->exportUsers($format, $options),
        };

        $audit->log(AuditAction::DataExport, $request->user()->id, [
            'entity' => $entity,
            'format' => $format,
            'include_sensitive' => $request->boolean('include_sensitive'),
            'incremental' => !empty($options['since']),
        ]);

        $filename = "{$entity}_export_" . now()->format('Y-m-d_His') . ".{$format}";

        return response()->json([
            'data' => [
                'filename' => $filename,
                'content' => $content,
                'format' => $format,
                'mime_type' => $format === 'json' ? 'application/json' : 'text/csv',
            ],
        ]);
    }
}
