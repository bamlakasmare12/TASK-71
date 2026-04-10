<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Services\ImportExportService;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessImportChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(
        private int $batchId,
        private array $rows,
        private int $startIndex,
    ) {}

    public function handle(ImportExportService $service): void
    {
        $batch = ImportBatch::find($this->batchId);

        if (!$batch || $batch->status === 'failed') {
            return;
        }

        $errors = $batch->error_log ?? [];

        foreach ($this->rows as $index => $row) {
            $rowNumber = $this->startIndex + $index + 1; // 1-based for display

            try {
                $result = $service->processRow(
                    $row,
                    $batch->entity,
                    $batch->field_mapping ?? [],
                    $batch->conflict_strategy,
                    $batch,
                );

                match ($result['status']) {
                    'created', 'updated' => $batch->increment('success_count'),
                    'skipped' => null,
                    'conflict' => $batch->increment('duplicate_count'),
                    'error' => $this->recordError($batch, $errors, $rowNumber, $result['error'] ?? 'Unknown error'),
                    default => null,
                };
            } catch (\Exception $e) {
                $this->recordError($batch, $errors, $rowNumber, $e->getMessage());
                Log::error("Import row {$rowNumber} failed", [
                    'batch_id' => $this->batchId,
                    'error' => $e->getMessage(),
                ]);
            }

            $batch->increment('processed_rows');
        }

        // Update error log
        if (!empty($errors)) {
            $batch->update(['error_log' => $errors]);
        }

        // Check if batch is complete
        $batch->refresh();
        if ($batch->processed_rows >= $batch->total_rows) {
            $finalStatus = $batch->error_count > 0 && $batch->success_count === 0
                ? 'failed'
                : 'completed';

            $batch->update([
                'status' => $batch->unresolvedConflicts()->count() > 0 ? 'pending_review' : $finalStatus,
                'completed_at' => now(),
            ]);
        }
    }

    private function recordError(ImportBatch $batch, array &$errors, int $rowNumber, string $message): void
    {
        $errors[] = [
            'row' => $rowNumber,
            'error' => Str::limit($message, 500),
            'timestamp' => now()->toIso8601String(),
        ];
        $batch->increment('error_count');
    }
}
