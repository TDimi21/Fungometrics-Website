<?php

declare(strict_types=1);

namespace App\Services\DataHub\Dictionary;

use App\Models\BaseballConceptAlias;

final class ConceptAliasResolver
{
    public function resolve(string $column, string $platformId): ?BaseballConceptAlias
    {
        return BaseballConceptAlias::query()->where('platform_definition_id', $platformId)->where('normalized_alias', TemplateFingerprintService::normalize($column))->where('status', 'active')->first();
    }
}
