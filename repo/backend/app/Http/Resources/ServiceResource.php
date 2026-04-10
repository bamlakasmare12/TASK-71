<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'service_type' => $this->service_type,
            'category' => $this->category,
            'eligibility_notes' => $this->eligibility_notes,
            'target_audience' => $this->target_audience,
            'price' => $this->price,
            'is_free' => $this->is_free,
            'project_number' => $this->project_number,
            'patent_number' => $this->patent_number,
            'is_active' => $this->is_active,
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
            ])),
            'next_available_slot' => $this->whenLoaded('timeSlots', function () {
                $slot = $this->timeSlots->first();
                return $slot ? [
                    'id' => $slot->id,
                    'start_time' => $slot->start_time->toIso8601String(),
                    'end_time' => $slot->end_time->toIso8601String(),
                    'capacity' => $slot->capacity,
                    'booked_count' => $slot->booked_count,
                ] : null;
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
