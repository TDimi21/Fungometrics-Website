<?php

declare(strict_types=1);

namespace App\Services\DataHub\Dictionary;

use App\Models\MappingEntry;
use App\Models\MappingTemplate;
use App\Models\MappingTemplateVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class MappingApprovalService
{
    public function approve(User $user, string $teamId, string $platformId, string $fingerprint, array $headers, array $entries, bool $remember): ?MappingTemplateVersion
    {
        if( ! $remember) {
            return null;
        }
        return DB::transaction(function () use ($user, $teamId, $platformId, $fingerprint, $headers, $entries) {
            $template = MappingTemplate::query()->firstOrCreate(['team_id' => $teamId,'platform_definition_id' => $platformId,'template_fingerprint' => $fingerprint], ['created_by' => $user->id,'is_active' => true]);
            $next = ((int)MappingTemplateVersion::query()->where('mapping_template_id', $template->id)->max('version')) + 1;
            $version = MappingTemplateVersion::query()->create(['mapping_template_id' => $template->id,'version' => $next,'header_fingerprint' => $fingerprint,'headers' => $headers,'status' => 'approved','approved_by' => $user->id,'approved_at' => now(),'supersedes_version_id' => $template->current_version_id]);
            foreach($entries as $entry) {
                MappingEntry::query()->create([
                    'mapping_template_version_id' => $version->id,
                    'source_column_name' => $entry['source_column_name'],
                    'normalized_source_column' => $entry['normalized_source_column'],
                    'baseball_concept_id' => $entry['baseball_concept_id'] ?? null,
                    'source_unit_id' => $entry['source_unit_id'] ?? null,
                    'canonical_unit_id' => $entry['canonical_unit_id'] ?? null,
                    'transformation_key' => $entry['transformation_key'] ?? null,
                    'resolution_source' => $entry['resolution_source'],
                    'confidence' => $entry['confidence'],
                    'required_type' => $entry['required_type'] ?? null,
                    'action' => $entry['action'],
                    'metadata' => $entry['metadata'] ?? null,
                ]);
            }
            $template->update(['current_version_id' => $version->id]);
            return $version;
        });
    }
}
