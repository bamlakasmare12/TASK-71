<?php

namespace App\Services;

use App\Models\DataDictionary;
use App\Models\FormRule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DictionaryQueryService
{
    public function listDictionaries(?string $type = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = DataDictionary::query()->orderBy('type')->orderBy('sort_order');

        if ($type) {
            $query->where('type', $type);
        }

        return $query->paginate($perPage, ['*'], 'dictPage');
    }

    public function listFormRules(?string $entity = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = FormRule::query()->orderBy('entity')->orderBy('field');

        if ($entity) {
            $query->where('entity', $entity);
        }

        return $query->paginate($perPage, ['*'], 'rulePage');
    }

    public function allDictionaries(?string $type = null): \Illuminate\Support\Collection
    {
        $query = DataDictionary::query()->orderBy('type')->orderBy('sort_order');

        if ($type) {
            $query->where('type', $type);
        }

        return $query->get();
    }

    public function allFormRules(?string $entity = null): \Illuminate\Support\Collection
    {
        $query = FormRule::query()->orderBy('entity')->orderBy('field');

        if ($entity) {
            $query->where('entity', $entity);
        }

        return $query->get();
    }
}
