<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'service_id' => $this->service_id,
            'time_slot_id' => $this->time_slot_id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            'checked_out_at' => $this->checked_out_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'can_cancel' => $this->canCancel(),
            'can_check_in' => $this->canCheckIn(),
            'is_free_cancellation' => $this->isFreeCancellation(),
            'service' => new ServiceResource($this->whenLoaded('service')),
            'time_slot' => $this->whenLoaded('timeSlot', fn () => [
                'id' => $this->timeSlot->id,
                'start_time' => $this->timeSlot->start_time->toIso8601String(),
                'end_time' => $this->timeSlot->end_time->toIso8601String(),
            ]),
            'penalties' => $this->whenLoaded('penalties', fn () => $this->penalties->map(fn ($p) => [
                'type' => $p->type,
                'fee_amount' => $p->fee_amount,
                'points_deducted' => $p->points_deducted,
                'reason' => $p->reason,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
