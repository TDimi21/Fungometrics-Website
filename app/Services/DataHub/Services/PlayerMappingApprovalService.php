<?php

declare(strict_types=1);

namespace App\Services\DataHub\Services;

use App\Models\ExternalPlayerMapping;
use App\Models\PlayerTeam;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PlayerMappingApprovalService
{
    public function approve(
        User $user,
        string $teamId,
        string $platformId,
        array $mappings,
        array $confirmedDuplicateTargets,
    ): array {
        foreach ($mappings as &$mapping) {
            if (($mapping['not_importing'] ?? false) || empty($mapping['fmtrx_player_id'])) {
                $mapping['fmtrx_player_id'] = null;
                $mapping['not_importing'] = true;
            }
        }
        unset($mapping);

        $targetCounts = array_count_values(array_filter(array_column($mappings, 'fmtrx_player_id')));
        $unconfirmed = array_keys(array_filter(
            $targetCounts,
            fn (int $count, string $target): bool => $count > 1 && ! in_array($target, $confirmedDuplicateTargets, true),
            ARRAY_FILTER_USE_BOTH
        ));
        if ($unconfirmed) {
            throw ValidationException::withMessages(['duplicate_targets' => 'Confirm every duplicate roster-player mapping before approval.']);
        }

        $targetIds = array_values(array_unique(array_filter(array_column($mappings, 'fmtrx_player_id'))));
        $authorizedTargets = PlayerTeam::query()
            ->where('team_id', $teamId)
            ->whereNull('deleted_at')
            ->whereIn('user_id', $targetIds)
            ->pluck('user_id')
            ->map(fn ($id): string => (string) $id)
            ->all();
        if (array_diff($targetIds, $authorizedTargets)) {
            throw ValidationException::withMessages(['mappings' => 'A selected player is not on the chosen FMTRX roster.']);
        }

        DB::transaction(function () use ($user, $teamId, $platformId, $mappings): void {
            foreach ($mappings as $mapping) {
                if (empty($mapping['fmtrx_player_id'])) {
                    continue;
                }
                $normalized = app(PlayerMatchingService::class)->normalize($mapping['source_name']);
                $query = ExternalPlayerMapping::query()
                    ->where('team_id', $teamId)
                    ->where('platform_definition_id', $platformId);
                $record = ! empty($mapping['external_player_id'])
                    ? $query->where('source_player_id', $mapping['external_player_id'])->first()
                    : $query->whereNull('source_player_id')->where('normalized_source_player_name', $normalized)->first();
                $values = [
                    'source_player_id' => $mapping['external_player_id'] ?: null,
                    'source_player_name' => $mapping['source_name'],
                    'normalized_source_player_name' => $normalized,
                    'fmtrx_player_id' => $mapping['fmtrx_player_id'],
                    'source_roles' => $mapping['roles'] ?? [],
                    'last_seen_at' => now(),
                    'status' => 'active',
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                ];
                if ($record) {
                    $record->update($values + ['occurrence_count' => $record->occurrence_count + 1]);
                } else {
                    ExternalPlayerMapping::query()->create($values + [
                        'team_id' => $teamId,
                        'platform_definition_id' => $platformId,
                        'first_seen_at' => now(),
                        'occurrence_count' => 1,
                    ]);
                }
            }
        });

        return [
            'approved' => true,
            'connected_count' => count(array_filter($mappings, fn (array $mapping): bool => ! empty($mapping['fmtrx_player_id']))),
            'not_importing_source_keys' => array_values(array_map(
                fn (array $mapping): string => $mapping['source_key'],
                array_filter($mappings, fn (array $mapping): bool => empty($mapping['fmtrx_player_id']))
            )),
        ];
    }
}
