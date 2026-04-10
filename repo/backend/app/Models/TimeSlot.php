<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimeSlot extends Model
{
    protected $fillable = [
        'service_id',
        'start_time',
        'end_time',
        'capacity',
        'booked_count',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function hasAvailability(): bool
    {
        return $this->booked_count < $this->capacity;
    }

    public function isFuture(): bool
    {
        return $this->start_time->isFuture();
    }

    public function isCheckInWindowOpen(): bool
    {
        $now = now();
        $opensAt = $this->start_time->copy()->subMinutes(15);
        $closesAt = $this->start_time->copy()->addMinutes(10);

        return $now->between($opensAt, $closesAt);
    }

    public function isLateArrival(): bool
    {
        return now()->isAfter($this->start_time);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_active', true)
            ->where('start_time', '>', now())
            ->whereColumn('booked_count', '<', 'capacity');
    }
}
