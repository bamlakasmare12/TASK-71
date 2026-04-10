<?php

namespace App\Services;

use App\Models\DataDictionary;
use App\Models\FormRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class DataDictionaryService
{
    public function getByType(string $type): Collection
    {
        return Cache::remember("dict.{$type}", 300, function () use ($type) {
            return DataDictionary::ofType($type)->get();
        });
    }

    public function getLabelsForType(string $type): array
    {
        return $this->getByType($type)->pluck('label', 'key')->toArray();
    }

    public function isValidKey(string $type, string $key): bool
    {
        return $this->getByType($type)->contains('key', $key);
    }

    public function getValidationRules(string $entity): array
    {
        $rules = FormRule::where('entity', $entity)
            ->where('is_active', true)
            ->get();

        $laravelRules = [];

        foreach ($rules as $rule) {
            $laravelRules[$rule->field] = $this->buildLaravelRule($rule->rules);
        }

        return $laravelRules;
    }

    private function buildLaravelRule(array $config): array
    {
        $rules = [];

        if (!empty($config['required'])) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        if (isset($config['type'])) {
            $rules[] = $config['type']; // string, integer, numeric, etc.
        }

        if (isset($config['min'])) {
            $rules[] = "min:{$config['min']}";
        }

        if (isset($config['max'])) {
            $rules[] = "max:{$config['max']}";
        }

        if (isset($config['regex'])) {
            $rules[] = "regex:{$config['regex']}";
        }

        if (isset($config['in'])) {
            $rules[] = 'in:' . implode(',', $config['in']);
        }

        return $rules;
    }

    public function clearCache(string $type): void
    {
        Cache::forget("dict.{$type}");
    }
}
