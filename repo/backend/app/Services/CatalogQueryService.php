<?php

namespace App\Services;

use App\Models\Service;
use App\Models\Tag;
use App\Models\UserFavorite;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CatalogQueryService
{
    public function listServices(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        $query = Service::active()->with(['tags', 'timeSlots' => function ($q) {
            $q->available()->orderBy('start_time')->limit(1);
        }]);

        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['audience'])) {
            $query->forAudience($filters['audience']);
        }

        if (!empty($filters['service_type'])) {
            $query->where('service_type', $filters['service_type']);
        }

        if (!empty($filters['tag'])) {
            $query->whereHas('tags', fn($q) => $q->where('slug', $filters['tag']));
        }

        if (($filters['price_filter'] ?? '') === 'free') {
            $query->where('is_free', true);
        } elseif (($filters['price_filter'] ?? '') === 'paid') {
            $query->where('is_free', false);
        }

        $sortBy = $filters['sort'] ?? 'earliest';
        $query = match ($sortBy) {
            'price_low' => $query->orderBy('price', 'asc'),
            'price_high' => $query->orderBy('price', 'desc'),
            'title' => $query->orderBy('title', 'asc'),
            default => $query->orderByRaw('(SELECT MIN(ts.start_time) FROM time_slots ts WHERE ts.service_id = services.id AND ts.is_active = true AND ts.start_time > NOW() AND ts.booked_count < ts.capacity) ASC NULLS LAST'),
        };

        return $query->paginate($perPage);
    }

    public function getServiceDetail(Service $service): Service
    {
        return $service->load(['tags', 'timeSlots' => function ($q) {
            $q->available()->orderBy('start_time');
        }]);
    }

    public function getAvailableCategories(): array
    {
        return Service::active()
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category', 'category')
            ->toArray();
    }

    public function getAvailableTags(): array
    {
        return Tag::whereHas('services', fn($q) => $q->active())
            ->orderBy('name')
            ->pluck('name', 'slug')
            ->toArray();
    }

    public function getUserFavoriteIds(int $userId): array
    {
        return UserFavorite::where('user_id', $userId)
            ->pluck('service_id')
            ->toArray();
    }

    public function isUserFavorite(int $userId, int $serviceId): bool
    {
        return UserFavorite::where('user_id', $userId)
            ->where('service_id', $serviceId)
            ->exists();
    }
}
