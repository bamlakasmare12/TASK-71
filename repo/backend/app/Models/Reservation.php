<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    protected $fillable = [
        'user_id',
        'service_id',
        'time_slot_id',
        'status',
        'confirmed_at',
        'checked_in_at',
        'checked_out_at',
        'cancelled_at',
        'cancellation_reason',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'confirmed_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class);
    }

    public function penalties(): HasMany
    {
        return $this->hasMany(Penalty::class);
    }

    public function isPending(): bool
    {
        return $this->status === ReservationStatus::Pending;
    }

    public function isConfirmed(): bool
    {
        return $this->status === ReservationStatus::Confirmed;
    }

    public function canCancel(): bool
    {
        return in_array($this->status, [
            ReservationStatus::Pending,
            ReservationStatus::Confirmed,
        ]);
    }

    public function isFreeCancellation(): bool
    {
        if (!$this->timeSlot) {
            return true;
        }

        return now()->diffInHours($this->timeSlot->start_time, false) >= 24;
    }

    public function canCheckIn(): bool
    {
        return $this->status === ReservationStatus::Confirmed
            && $this->timeSlot
            && $this->timeSlot->isCheckInWindowOpen();
    }
}
