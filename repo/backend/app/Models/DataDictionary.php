<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataDictionary extends Model
{
    protected $fillable = [
        'type',
        'key',
        'label',
        'metadata',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type)->where('is_active', true)->orderBy('sort_order');
    }
}
