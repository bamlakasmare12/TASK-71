<?php

namespace App\Livewire\Admin;

use App\Models\ImportBatch;
use App\Services\InternalApiClient;
use Livewire\Component;
use Livewire\WithFileUploads;

class ImportManager extends Component
{
    use WithFileUploads;

    // Upload state
    public $file = null;
    public string $entity = 'services';
    public string $conflictStrategy = 'prefer_newest';

    // Mapping state
    public ?int $activeBatchId = null;
    public array $sourceHeaders = [];
    public array $destinationFields = [];
    public array $fieldMapping = [];

    // Progress state
    public string $step = 'upload'; // upload, mapping, processing, review, done
    public string $errorMessage = '';
    public string $successMessage = '';

    public function updatedFile(): void
    {
        $this->validate([
            'file' => 'required|file|max:10240|mimes:csv,txt,json',
        ]);
    }

    public function upload(InternalApiClient $api): void
    {
        $this->validate([
            'file' => 'required|file|max:10240|mimes:csv,txt,json',
            'entity' => 'required|in:services,users',
        ]);

        $this->errorMessage = '';
        $path = $this->file->store('imports', 'local');

        $response = $api->post('admin/import/upload', [
            'stored_path' => $path,
            'original_filename' => $this->file->getClientOriginalName(),
            'entity' => $this->entity,
            'conflict_strategy' => $this->conflictStrategy,
        ]);

        if (!$response['ok']) {
            $this->errorMessage = $response['error'] ?? $response['message'] ?? 'Failed to parse file.';
            return;
        }

        $data = $response['data'];
        $this->activeBatchId = $data['batch_id'];
        $this->sourceHeaders = $data['source_headers'];
        $this->destinationFields = $data['destination_fields'];
        $this->fieldMapping = $data['field_mapping'];
        $this->step = 'mapping';
    }

    public function startProcessing(InternalApiClient $api): void
    {
        $response = $api->post("admin/import/{$this->activeBatchId}/process", [
            'field_mapping' => $this->fieldMapping,
            'conflict_strategy' => $this->conflictStrategy,
        ]);

        if ($response['ok']) {
            $this->step = 'processing';
            $data = $response['data'];
            $this->successMessage = "Import started! Processing {$data['total_rows']} rows in {$data['chunks']} chunk(s).";
        } else {
            $this->errorMessage = $response['error'] ?? $response['message'] ?? 'Failed to start processing.';
        }
    }

    public function refreshProgress(): void
    {
        // Livewire will re-render with fresh batch data
    }

    public function resolveConflict(int $conflictId, string $resolution, InternalApiClient $api): void
    {
        $response = $api->post("admin/import/conflicts/{$conflictId}/resolve", [
            'resolution' => $resolution,
        ]);
        if (!$response['ok']) {
            $this->errorMessage = $response['error'] ?? 'Failed to resolve conflict.';
        }
    }

    public function finishReview(InternalApiClient $api): void
    {
        $response = $api->post("admin/import/{$this->activeBatchId}/finish");
        if ($response['ok']) {
            $this->step = 'done';
        } else {
            $this->errorMessage = $response['error'] ?? $response['message'] ?? 'Failed to finish review.';
        }
    }

    public function startNew(): void
    {
        $this->reset();
        $this->step = 'upload';
    }

    public function isStepComplete(string $checkStep): bool
    {
        $order = ['upload', 'mapping', 'processing', 'review', 'done'];
        $currentIndex = array_search($this->step, $order);
        $checkIndex = array_search($checkStep, $order);

        return $checkIndex < $currentIndex;
    }

    public function render()
    {
        $batch = $this->activeBatchId ? ImportBatch::with('conflicts')->find($this->activeBatchId) : null;

        // Auto-advance from processing to review/done
        if ($batch && $this->step === 'processing') {
            if ($batch->isComplete() || $batch->status === 'pending_review') {
                $this->step = $batch->unresolvedConflicts()->count() > 0 ? 'review' : 'done';
            }
        }

        return view('livewire.admin.import-manager', [
            'batch' => $batch,
            'conflicts' => $batch?->unresolvedConflicts()->get() ?? collect(),
        ])->layout('components.layouts.app', ['title' => 'Import Data']);
    }
}
