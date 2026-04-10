<?php

namespace App\Livewire\Reservations;

use App\Models\Service;
use App\Models\TimeSlot;
use Livewire\Component;

class TimeSlotManager extends Component
{
    public Service $service;

    public string $startDate = '';
    public string $startTime = '';
    public string $endTime = '';
    public int $capacity = 1;

    public string $errorMessage = '';
    public string $successMessage = '';

    public function mount(Service $service): void
    {
        $this->service = $service;
    }

    public function createSlot(): void
    {
        $this->validate([
            'startDate' => 'required|date|after:today',
            'startTime' => 'required|date_format:H:i',
            'endTime' => 'required|date_format:H:i|after:startTime',
            'capacity' => 'required|integer|min:1|max:100',
        ]);

        $this->clearMessages();

        $startDateTime = $this->startDate . ' ' . $this->startTime . ':00';
        $endDateTime = $this->startDate . ' ' . $this->endTime . ':00';

        // Check for overlapping slots
        $overlap = TimeSlot::where('service_id', $this->service->id)
            ->where('is_active', true)
            ->where(function ($q) use ($startDateTime, $endDateTime) {
                $q->whereBetween('start_time', [$startDateTime, $endDateTime])
                  ->orWhereBetween('end_time', [$startDateTime, $endDateTime])
                  ->orWhere(function ($q2) use ($startDateTime, $endDateTime) {
                      $q2->where('start_time', '<=', $startDateTime)
                          ->where('end_time', '>=', $endDateTime);
                  });
            })
            ->exists();

        if ($overlap) {
            $this->errorMessage = 'This time slot overlaps with an existing slot.';
            return;
        }

        TimeSlot::create([
            'service_id' => $this->service->id,
            'start_time' => $startDateTime,
            'end_time' => $endDateTime,
            'capacity' => $this->capacity,
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        $this->successMessage = 'Time slot created.';
        $this->reset(['startDate', 'startTime', 'endTime']);
        $this->capacity = 1;
    }

    public function deactivateSlot(int $slotId): void
    {
        $slot = TimeSlot::where('service_id', $this->service->id)->findOrFail($slotId);

        if ($slot->booked_count > 0) {
            $this->errorMessage = 'Cannot deactivate a slot with active bookings.';
            return;
        }

        $slot->update(['is_active' => false]);
        $this->successMessage = 'Slot deactivated.';
    }

    private function clearMessages(): void
    {
        $this->errorMessage = '';
        $this->successMessage = '';
    }

    public function render()
    {
        return view('livewire.reservations.time-slot-manager', [
            'slots' => $this->service->timeSlots()
                ->where('is_active', true)
                ->where('start_time', '>=', now())
                ->orderBy('start_time')
                ->get(),
        ])->layout('components.layouts.app', ['title' => 'Manage Time Slots - ' . $this->service->title]);
    }
}
