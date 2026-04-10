<?php

namespace App\Actions\Catalog;

use App\Models\Service;
use App\Models\Tag;
use App\Services\DataDictionaryService;
use Illuminate\Support\Str;

class ManageService
{
    public function __construct(private DataDictionaryService $dictService) {}

    public function validationRules(string $mode = 'create'): array
    {
        $prefix = $mode === 'update' ? 'sometimes|' : '';

        $baseRules = [
            'title' => $prefix . 'required|string|min:3|max:255',
            'description' => $prefix . 'required|string|min:10',
            'service_type' => $prefix . 'required|string',
            'price' => $prefix . 'required|numeric|min:0',
            'category' => 'nullable|string',
            'eligibility_notes' => 'nullable|string',
            'target_audience' => 'nullable|array',
            'is_active' => 'boolean',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
            'project_number' => 'nullable|string|max:100',
            'patent_number' => 'nullable|string|max:100',
        ];

        $dynamicRules = $this->dictService->getValidationRules('service');
        foreach ($dynamicRules as $field => $fieldRules) {
            if (isset($baseRules[$field])) {
                $existing = is_array($baseRules[$field]) ? $baseRules[$field] : explode('|', $baseRules[$field]);
                $baseRules[$field] = array_values(array_unique(array_merge($existing, $fieldRules)));
            } else {
                $baseRules[$field] = $fieldRules;
            }
        }

        return $baseRules;
    }

    public function create(array $data, int $userId): Service
    {
        $service = Service::create([
            'title' => $data['title'],
            'slug' => Str::slug($data['title']),
            'description' => $data['description'],
            'service_type' => $data['service_type'],
            'eligibility_notes' => $data['eligibility_notes'] ?? null,
            'target_audience' => $data['target_audience'] ?? [],
            'price' => $data['price'] ?? 0,
            'category' => $data['category'] ?? null,
            'project_number' => $data['project_number'] ?? null,
            'patent_number' => $data['patent_number'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        if (!empty($data['tags'])) {
            $this->syncTags($service, $data['tags']);
        }

        return $service;
    }

    public function update(Service $service, array $data, int $userId): Service
    {
        $service->update(array_merge($data, [
            'updated_by' => $userId,
        ]));

        if (array_key_exists('tags', $data)) {
            $this->syncTags($service, $data['tags']);
        }

        return $service->fresh(['tags']);
    }

    private function syncTags(Service $service, array $tagNames): void
    {
        $tagIds = collect($tagNames)->map(function (string $name) {
            return Tag::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            )->id;
        });

        $service->tags()->sync($tagIds);
    }
}
