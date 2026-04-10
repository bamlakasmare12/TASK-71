<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\TimeSlot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ReservationQueryService
{
    public function listForUser(int $userId, string $filter = 'all', int $perPage = 10): LengthAwarePaginator
    {
        $query = Reservation::where('user_id', $userId)
            ->with(['service', 'timeSlot', 'penalties'])
            ->orderByDesc('created_at');

        if ($filter === 'upcoming') {
            $query->whereIn('status', [
                ReservationStatus::Pending->value,
                ReservationStatus::Confirmed->value,
            ])->whereHas('timeSlot', fn($q) => $q->where('start_time', '>=', now()));
        } elseif ($filter === 'past') {
            $query->whereIn('status', [
                ReservationStatus::Completed->value,
                ReservationStatus::Cancelled->value,
                ReservationStatus::NoShow->value,
                ReservationStatus::PartialAttendance->value,
            ]);
        }

        return $query->paginate($perPage);
    }

    public function getAvailableSlotsForService(int $serviceId, ?int $excludeSlotId = null, int $limit = 20): array
    {
        $query = TimeSlot::where('service_id', $serviceId)
            ->available()
            ->orderBy('start_time')
            ->limit($limit);

        if ($excludeSlotId) {
            $query->where('id', '!=', $excludeSlotId);
        }

        return $query->get()
            ->map(fn($slot) => [
                'id' => $slot->id,
                'label' => $slot->start_time->format('D, M d, Y g:i A') . ' - ' . $slot->end_time->format('g:i A'),
                'available' => $slot->capacity - $slot->booked_count,
            ])
            ->toArray();
    }
}
