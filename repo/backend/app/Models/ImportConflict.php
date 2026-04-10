<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportConflict extends Model
{
    protected $fillable = [
        'import_batch_id',
        'entity',
        'existing_id',
        'incoming_data',
        'existing_data',
        'similarity_score',
        'match_type',
        'resolution',
        'resolved',
    ];

    protected function casts(): array
    {
        return [
            'incoming_data' => 'array',
            'existing_data' => 'array',
            'similarity_score' => 'decimal:4',
            'resolved' => 'boolean',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }
}
