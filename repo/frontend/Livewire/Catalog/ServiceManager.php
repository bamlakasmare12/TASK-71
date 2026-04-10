<?php

namespace App\Livewire\Catalog;

use App\Actions\Catalog\ManageService;
use App\Models\Service;
use App\Services\DataDictionaryService;
use App\Services\InternalApiClient;
use Livewire\Component;

class ServiceManager extends Component
{
    public ?Service $service = null;
    public bool $isEditing = false;

    public string $title = '';
    public string $description = '';
    public string $serviceType = '';
    public string $eligibilityNotes = '';
    public array $targetAudience = [];
    public string $price = '0.00';
    public string $category = '';
    public bool $isActive = true;
    public string $tagsInput = '';
    public string $projectNumber = '';
    public string $patentNumber = '';

    public string $errorMessage = '';
    public string $successMessage = '';

    public function mount(?int $serviceId = null): void
    {
        if ($serviceId) {
            $this->service = Service::with('tags')->findOrFail($serviceId);
            $this->isEditing = true;
            $this->fill([
                'title' => $this->service->title,
                'description' => $this->service->description,
                'serviceType' => $this->service->service_type,
                'eligibilityNotes' => $this->service->eligibility_notes ?? '',
                'targetAudience' => $this->service->target_audience ?? [],
                'price' => (string) $this->service->price,
                'category' => $this->service->category ?? '',
                'isActive' => $this->service->is_active,
                'tagsInput' => $this->service->tags->pluck('name')->implode(', '),
                'projectNumber' => $this->service->project_number ?? '',
                'patentNumber' => $this->service->patent_number ?? '',
            ]);
        }
    }

    public function save(ManageService $action, InternalApiClient $api): void
    {
        $mode = $this->isEditing ? 'update' : 'create';
        $rules = $action->validationRules($mode);
        $livewireRules = [];
        $fieldMap = [
            'title' => 'title', 'description' => 'description',
            'serviceType' => 'service_type', 'price' => 'price',
        ];
        foreach ($fieldMap as $prop => $field) {
            if (isset($rules[$field])) {
                $livewireRules[$prop] = $rules[$field];
            }
        }
        $this->validate($livewireRules);

        $this->errorMessage = '';
        $this->successMessage = '';

        $data = [
            'title' => $this->title,
            'description' => $this->description,
            'service_type' => $this->serviceType,
            'eligibility_notes' => $this->eligibilityNotes ?: null,
            'target_audience' => $this->targetAudience,
            'price' => (float) $this->price,
            'category' => $this->category ?: null,
            'project_number' => $this->projectNumber ?: null,
            'patent_number' => $this->patentNumber ?: null,
            'is_active' => $this->isActive,
            'tags' => array_filter(array_map('trim', explode(',', $this->tagsInput))),
        ];

        if ($this->isEditing) {
            $response = $api->put("catalog/{$this->service->id}", $data);
        } else {
            $response = $api->post('catalog', $data);
        }

        if ($response['ok']) {
            if ($this->isEditing) {
                $this->successMessage = 'Service updated successfully.';
            } else {
                $this->service = Service::find($response['data']['id'] ?? null);
                $this->successMessage = 'Service created successfully.';
                $this->isEditing = true;
            }
        } else {
            $this->errorMessage = 'Failed to save service: ' . ($response['error'] ?? $response['message'] ?? 'Unknown error');
        }
    }

    public function render(DataDictionaryService $dictService)
    {
        return view('livewire.catalog.service-manager', [
            'serviceTypes' => $dictService->getLabelsForType('service_type'),
            'audiences' => $dictService->getLabelsForType('eligibility'),
        ])->layout('components.layouts.app', ['title' => $this->isEditing ? 'Edit Service' : 'Create Service']);
    }
}
