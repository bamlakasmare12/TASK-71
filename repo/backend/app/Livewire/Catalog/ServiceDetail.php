<?php

namespace App\Livewire\Catalog;

use App\Actions\Catalog\RecordRecentView;
use App\Models\Service;
use App\Services\CatalogQueryService;
use App\Services\InternalApiClient;
use Livewire\Component;

class ServiceDetail extends Component
{
    public Service $service;
    public bool $isFavorited = false;
    public string $errorMessage = '';
    public string $successMessage = '';

    public function mount(Service $service, RecordRecentView $recordView, CatalogQueryService $catalogQuery): void
    {
        $this->service = $catalogQuery->getServiceDetail($service);

        if (auth()->check()) {
            $this->isFavorited = $catalogQuery->isUserFavorite(auth()->id(), $service->id);

            $recordView->execute(auth()->user(), $service->id);
        }
    }

    public function toggleFavorite(InternalApiClient $api): void
    {
        $response = $api->post("catalog/{$this->service->id}/favorite");
        if ($response['ok']) {
            $this->isFavorited = $response['data']['favorited'] ?? !$this->isFavorited;
        }
    }

    public function bookSlot(int $timeSlotId, InternalApiClient $api, CatalogQueryService $catalogQuery): void
    {
        $this->errorMessage = '';
        $this->successMessage = '';

        $response = $api->post('reservations', [
            'time_slot_id' => $timeSlotId,
        ]);

        if ($response['ok']) {
            $this->successMessage = 'Reservation created! Please confirm within 30 minutes.';
            $this->service = $catalogQuery->getServiceDetail($this->service);
        } else {
            $this->errorMessage = $response['error'] ?? $response['message'] ?? 'Failed to create reservation.';
        }
    }

    public function render()
    {
        return view('livewire.catalog.service-detail')
            ->layout('components.layouts.app', ['title' => $this->service->title]);
    }
}
