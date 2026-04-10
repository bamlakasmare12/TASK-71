<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    protected $fillable = [
        'user_id',
        'entity',
        'filename',
        'stored_path',
        'format',
        'status',
        'total_rows',
        'processed_rows',
        'success_count',
        'error_count',
        'duplicate_count',
        'field_mapping',
        'conflict_strategy',
        'error_log',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'field_mapping' => 'array',
            'error_log' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conflicts(): HasMany
    {
        return $this->hasMany(ImportConflict::class);
    }

    public function unresolvedConflicts(): HasMany
    {
        return $this->hasMany(ImportConflict::class)->where('resolved', false);
    }

    public function progressPercent(): int
    {
        if ($this->total_rows === 0) {
            return 0;
        }

        return (int) round(($this->processed_rows / $this->total_rows) * 100);
    }

    public function isComplete(): bool
    {
        return in_array($this->status, ['completed', 'failed']);
    }
}
