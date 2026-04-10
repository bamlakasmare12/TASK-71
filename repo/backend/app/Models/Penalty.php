<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Penalty extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'reservation_id',
        'type',
        'fee_amount',
        'points_deducted',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'fee_amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
