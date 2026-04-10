<?php

namespace App\Livewire\Reservations;

use App\Services\InternalApiClient;
use App\Services\PenaltyConfigService;
use App\Services\ReservationQueryService;
use Livewire\Component;
use Livewire\WithPagination;

class ReservationDashboard extends Component
{
    use WithPagination;

    public string $filter = 'upcoming'; // upcoming, past, all
    public string $errorMessage = '';
    public string $successMessage = '';

    // Reschedule state
    public ?int $reschedulingReservationId = null;
    public ?int $selectedNewSlotId = null;
    public array $availableSlots = [];

    public function confirm(int $reservationId, InternalApiClient $api): void
    {
        $this->clearMessages();
        $response = $api->post("reservations/{$reservationId}/confirm");
        if ($response['ok']) {
            $this->successMessage = 'Reservation confirmed!';
        } else {
            $this->errorMessage = $response['error'] ?? $response['message'] ?? 'Failed to confirm reservation.';
        }
    }

    public function cancel(int $reservationId, InternalApiClient $api): void
    {
        $this->clearMessages();
        $response = $api->post("reservations/{$reservationId}/cancel");
        if ($response['ok']) {
            $this->successMessage = 'Reservation cancelled.';
        } else {
            $this->errorMessage = $response['error'] ?? $response['message'] ?? 'Failed to cancel reservation.';
        }
    }

    public function checkIn(int $reservationId, InternalApiClient $api): void
    {
        $this->clearMessages();
        $response = $api->post("reservations/{$reservationId}/check-in");
        if ($response['ok']) {
            $this->successMessage = 'Checked in successfully!';
        } else {
            $this->errorMessage = $response['error'] ?? $response['message'] ?? 'Check-in is not available at this time.';
        }
    }

    public function checkOut(int $reservationId, InternalApiClient $api): void
    {
        $this->clearMessages();
        $response = $api->post("reservations/{$reservationId}/check-out");
        if ($response['ok']) {
            $this->successMessage = 'Checked out. Thank you!';
        } else {
            $this->errorMessage = $response['error'] ?? $response['message'] ?? 'Failed to check out.';
        }
    }

    public function startReschedule(int $reservationId, ReservationQueryService $queryService): void
    {
        $this->clearMessages();
        $reservation = \App\Models\Reservation::where('user_id', auth()->id())
            ->with('service')
            ->findOrFail($reservationId);

        if (!$reservation->canCancel()) {
            $this->errorMessage = 'This reservation cannot be rescheduled.';
            return;
        }

        $this->reschedulingReservationId = $reservationId;
        $this->selectedNewSlotId = null;
        $this->availableSlots = $queryService->getAvailableSlotsForService(
            $reservation->service_id,
            $reservation->time_slot_id
        );

        if (empty($this->availableSlots)) {
            $this->errorMessage = 'No other time slots are available for this service.';
            $this->reschedulingReservationId = null;
        }
    }

    public function cancelReschedule(): void
    {
        $this->reschedulingReservationId = null;
        $this->selectedNewSlotId = null;
        $this->availableSlots = [];
    }

    public function confirmReschedule(InternalApiClient $api): void
    {
        $this->clearMessages();
        if (!$this->reschedulingReservationId || !$this->selectedNewSlotId) {
            $this->errorMessage = 'Please select a new time slot.';
            return;
        }

        $response = $api->post("reservations/{$this->reschedulingReservationId}/reschedule", [
            'new_time_slot_id' => $this->selectedNewSlotId,
        ]);

        if ($response['ok']) {
            $this->successMessage = 'Reservation rescheduled successfully!';
            $this->cancelReschedule();
        } else {
            $this->errorMessage = $response['error'] ?? $response['message'] ?? 'Failed to reschedule.';
        }
    }

    private function clearMessages(): void
    {
        $this->errorMessage = '';
        $this->successMessage = '';
    }

    public function render(ReservationQueryService $queryService, PenaltyConfigService $penaltyConfig)
    {
        return view('livewire.reservations.reservation-dashboard', [
            'reservations' => $queryService->listForUser(auth()->id(), $this->filter),
            'penaltyDescription' => $penaltyConfig->getPenaltyDescription(),
        ])->layout('components.layouts.app', ['title' => 'My Reservations']);
    }
}
