<?php

namespace App\Http\Controllers\Api;

use App\Actions\Catalog\ManageService;
use App\Actions\Catalog\ToggleFavorite;
use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Services\CatalogQueryService;
use App\Services\DataDictionaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CatalogController extends Controller
{
    public function __construct(private CatalogQueryService $catalogQuery) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $services = $this->catalogQuery->listServices([
            'search' => $request->input('search'),
            'category' => $request->input('category'),
            'audience' => $request->input('audience'),
            'service_type' => $request->input('service_type'),
            'tag' => $request->input('tag'),
            'price_filter' => $request->input('price_filter'),
            'sort' => $request->input('sort', 'earliest'),
        ]);

        return ServiceResource::collection($services);
    }

    public function show(Service $service): ServiceResource
    {
        return new ServiceResource($this->catalogQuery->getServiceDetail($service));
    }

    public function store(Request $request, ManageService $action): ServiceResource
    {
        $request->validate($action->validationRules('create'));

        $service = $action->create($request->all(), $request->user()->id);

        return new ServiceResource($service->load('tags'));
    }

    public function update(Request $request, Service $service, ManageService $action): ServiceResource
    {
        $request->validate($action->validationRules('update'));

        $service = $action->update($service, $request->all(), $request->user()->id);

        return new ServiceResource($service->load('tags'));
    }

    public function favorites(Request $request): AnonymousResourceCollection
    {
        $favoriteServiceIds = $this->catalogQuery->getUserFavoriteIds($request->user()->id);

        $services = Service::whereIn('id', $favoriteServiceIds)
            ->with(['tags'])
            ->paginate(12);

        return ServiceResource::collection($services);
    }

    public function toggleFavorite(Request $request, Service $service, ToggleFavorite $action): JsonResponse
    {
        $isFavorited = $action->execute($request->user(), $service->id);

        return response()->json(['favorited' => $isFavorited]);
    }

    public function dictionaries(DataDictionaryService $dictService): JsonResponse
    {
        return response()->json([
            'service_types' => $dictService->getLabelsForType('service_type'),
            'audiences' => $dictService->getLabelsForType('eligibility'),
        ]);
    }
}
