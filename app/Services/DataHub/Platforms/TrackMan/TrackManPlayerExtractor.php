<?php

declare(strict_types=1);

namespace App\Services\DataHub\Platforms\TrackMan;

use App\Services\DataHub\Services\PlayerMatchingService;

final class TrackManPlayerExtractor
{
    public function __construct(private readonly PlayerMatchingService $matching)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function extract(array $rows): array
    {
        $players = [];
        foreach ($rows as $row) {
            foreach (['batter', 'pitcher'] as $role) {
                $name = trim((string) ($row[$role] ?? ''));
                if ('' === $name) {
                    continue;
                }
                $externalId = trim((string) ($row[$role.'_id'] ?? '')) ?: null;
                $normalized = $this->matching->normalize($name);
                $sourceKey = $externalId
                    ? 'trackman:id:'.$externalId
                    : 'trackman:name:'.hash('sha256', $normalized);
                $players[$sourceKey] ??= [
                    'source_key' => $sourceKey,
                    'source_name' => $name,
                    'normalized_name' => $normalized,
                    'external_player_id' => $externalId,
                    'roles' => [],
                    'row_count' => 0,
                    'batter_row_count' => 0,
                    'pitcher_row_count' => 0,
                    'source_team_names' => [],
                ];
                ++$players[$sourceKey]['row_count'];
                ++$players[$sourceKey][$role.'_row_count'];
                $players[$sourceKey]['roles'][] = $role;
                $team = trim((string) ($row[$role.'_team'] ?? ''));
                if ('' !== $team) {
                    $players[$sourceKey]['source_team_names'][] = $team;
                }
            }
        }
        foreach ($players as &$player) {
            $player['roles'] = array_values(array_unique($player['roles']));
            $player['source_team_names'] = array_values(array_unique($player['source_team_names']));
        }

        return array_values($players);
    }
}
