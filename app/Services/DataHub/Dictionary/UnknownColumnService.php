<?php

declare(strict_types=1);

namespace App\Services\DataHub\Dictionary;

use App\Models\UnknownSourceColumn;

final class UnknownColumnService
{
    public function remember(string $teamId, string $platformId, string $fingerprint, string $column, array $samples = []): UnknownSourceColumn
    {
        $normalized = TemplateFingerprintService::normalize($column);
        $row = UnknownSourceColumn::query()->firstOrNew(['team_id' => $teamId,'platform_definition_id' => $platformId,'template_fingerprint' => $fingerprint,'normalized_source_column' => $normalized]);
        $row->fill(['source_column_name' => $column,'sample_values' => array_slice($samples, 0, 10),'occurrence_count' => ($row->exists ? $row->occurrence_count : 0) + 1,'first_seen_at' => $row->first_seen_at ?: now(),'last_seen_at' => now(),'status' => $row->status ?: 'unresolved']);
        $row->save();
        return $row;
    }
}
