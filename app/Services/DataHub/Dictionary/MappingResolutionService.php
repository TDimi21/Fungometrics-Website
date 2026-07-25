<?php

declare(strict_types=1);

namespace App\Services\DataHub\Dictionary;

use App\Models\MappingEntry;
use App\Models\MappingTemplate;

final class MappingResolutionService
{
    public function __construct(private ConceptAliasResolver $aliases)
    {
    }
    public function resolve(string $teamId, string $platformId, string $fingerprint, array $columns): array
    {
        $template = MappingTemplate::query()->where('team_id', $teamId)->where('platform_definition_id', $platformId)->where('template_fingerprint', $fingerprint)->first();
        $remembered = $template ? MappingEntry::query()->where('mapping_template_version_id', $template->current_version_id)->get()->keyBy('normalized_source_column') : collect();
        return array_map(function ($column) use ($remembered, $platformId) {
            $normalized = TemplateFingerprintService::normalize($column);
            if($entry = $remembered->get($normalized)) {
                return ['source_column_name' => $column,'normalized_source_column' => $normalized,'concept_id' => $entry->baseball_concept_id,'source_unit_id' => $entry->source_unit_id,'canonical_unit_id' => $entry->canonical_unit_id,'transformation_key' => $entry->transformation_key,'relationship_type' => $entry->metadata['relationship_type'] ?? null,'resolution_source' => 'exact_template','confidence' => 100,'trusted' => true];
            }if($alias = $this->aliases->resolve($column, $platformId)) {
                return ['source_column_name' => $column,'normalized_source_column' => $normalized,'concept_id' => $alias->baseball_concept_id,'source_unit_key' => $alias->source_unit_key,'transformation_key' => $alias->transformation_key,'relationship_type' => $alias->relationship_type,'resolution_source' => 'official_platform_alias','confidence' => $alias->confidence,'trusted' => true];
            }return ['source_column_name' => $column,'normalized_source_column' => $normalized,'concept_id' => null,'resolution_source' => 'unresolved','confidence' => 0,'trusted' => false];
        }, $columns);
    }
}
