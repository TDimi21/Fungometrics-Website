<?php

declare(strict_types=1);

namespace App\Services\DataHub\Dictionary;

use App\Models\BaseballConcept;
use App\Models\BaseballConceptAlias;
use App\Models\BaseballDomain;
use App\Models\UnitDefinition;

final class BaseballDictionaryService
{
    public function catalog(): array
    {
        $aliases = BaseballConceptAlias::query()
            ->where('status', 'active')
            ->orderBy('alias')
            ->get(['baseball_concept_id', 'alias'])
            ->groupBy('baseball_concept_id');
        $concepts = BaseballConcept::query()
            ->where('status', 'active')
            ->orderBy('display_name')
            ->get()
            ->map(function (BaseballConcept $concept) use ($aliases): BaseballConcept {
                $concept->setAttribute('aliases', $aliases
                    ->get($concept->id, collect())
                    ->pluck('alias')
                    ->values()
                    ->all());

                return $concept;
            });

        return [
            'domains' => BaseballDomain::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'concepts' => $concepts,
            'units' => UnitDefinition::query()->orderBy('display_name')->get(),
        ];
    }
    public function validate(BaseballConcept $concept, mixed $value): array
    {
        if( ! is_numeric($value) || ('numeric' !== $concept->data_type && 'integer' !== $concept->data_type)) {
            return ['valid' => true,'warning' => null];
        }$v = (float)$value;
        $bad = (null !== $concept->valid_min && $v < $concept->valid_min) || (null !== $concept->valid_max && $v > $concept->valid_max);
        return ['valid' => ! $bad,'warning' => $bad ? 'Value is outside the concept’s broad sanity range.' : null];
    }
}
