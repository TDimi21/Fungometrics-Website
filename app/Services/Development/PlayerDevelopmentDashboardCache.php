<?php

declare(strict_types=1);

namespace App\Services\Development;

use App\Models\PlayerTeam;
use Illuminate\Support\Facades\Cache;

final class PlayerDevelopmentDashboardCache
{
    private const WINDOWS = [7, 30, 60, 90, 120, 180, 365, 'all'];

    public function forgetPlayer(string $playerId): void
    {
        $teamIds = PlayerTeam::query()
            ->where('user_id', $playerId)
            ->whereNotNull('team_id')
            ->pluck('team_id')
            ->map(fn ($teamId): string => (string) $teamId)
            ->push('all')
            ->unique();

        foreach ($teamIds as $teamId) {
            foreach (self::WINDOWS as $days) {
                Cache::forget("dev_dashboard_v3_{$teamId}_{$playerId}_{$days}");
                Cache::forget("player_intelligence_v2_{$teamId}_{$playerId}_{$days}");
            }
        }
    }

    public function forgetTeam(string $teamId): void
    {
        PlayerTeam::query()
            ->where('team_id', $teamId)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->each(fn ($playerId) => $this->forgetPlayer((string) $playerId));
    }
}
