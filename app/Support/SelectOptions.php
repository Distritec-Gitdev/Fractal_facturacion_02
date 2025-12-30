<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class SelectOptions
{
    public static function fromQuery(Builder $q, string $labelCol, string $valueCol): array
    {
        return $q->whereNotNull($labelCol)
            ->where($labelCol, '<>', '')
            ->pluck($labelCol, $valueCol)
            ->map(fn ($label) => (string) $label)
            ->toArray();
    }

    public static function fromArray(iterable $items): array
    {
        return collect($items)
            ->filter(fn ($label) => $label !== null && $label !== '')
            ->map(fn ($label) => (string) $label)
            ->toArray();
    }
}
