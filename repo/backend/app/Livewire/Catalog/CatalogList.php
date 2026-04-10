<?php

namespace App\Livewire\Catalog;

use App\Services\CatalogQueryService;
use App\Services\DataDictionaryService;
use App\Services\InternalApiClient;
use Livewire\Component;
use Livewire\WithPagination;

class CatalogList extends Component
{
    use WithPagination;

    public string $search = '';
    public string $category = '';
    public string $audience = '';
    public string $serviceType = '';
    public string $tag = '';
    public string $sortBy = 'earliest'; // earliest, price_low, price_high, title
    public string $priceFilter = ''; // free, paid

    protected $queryString = [
        'search' => ['except' => ''],
        'category' => ['except' => ''],
        'audience' => ['except' => ''],
        'serviceType' => ['except' => ''],
        'tag' => ['except' => ''],
        'sortBy' => ['except' => 'earliest'],
        'priceFilter' => ['except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCategory(): void
    {
        $this->resetPage();
    }

    public function updatingAudience(): void
    {
        $this->resetPage();
    }

    public function updatingTag(): void
    {
        $this->resetPage();
    }

    public function updatingPriceFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'category', 'audience', 'serviceType', 'tag', 'sortBy', 'priceFilter']);
        $this->resetPage();
    }

    public function toggleFavorite(int $serviceId, InternalApiClient $api): void
    {
        $api->post("catalog/{$serviceId}/favorite");
    }

    public function render(CatalogQueryService $catalogQuery, DataDictionaryService $dictService)
    {
        $services = $catalogQuery->listServices([
            'search' => $this->search,
            'category' => $this->category,
            'audience' => $this->audience,
            'service_type' => $this->serviceType,
            'tag' => $this->tag,
            'price_filter' => $this->priceFilter,
            'sort' => $this->sortBy,
        ]);

        $favoriteIds = auth()->check()
            ? $catalogQuery->getUserFavoriteIds(auth()->id())
            : [];

        return view('livewire.catalog.catalog-list', [
            'services' => $services,
            'favoriteIds' => $favoriteIds,
            'serviceTypes' => $dictService->getLabelsForType('service_type'),
            'audiences' => $dictService->getLabelsForType('eligibility'),
            'categories' => $catalogQuery->getAvailableCategories(),
            'tags' => $catalogQuery->getAvailableTags(),
        ])->layout('components.layouts.app', ['title' => 'Service Catalog']);
    }
}
