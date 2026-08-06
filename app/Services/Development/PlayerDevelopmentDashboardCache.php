<?php

declare(strict_types=1);

namespace App\Services\Development;

use App\Models\PlayerTeam;
use Illuminate\Support\Facades\Cache;

final class PlayerDevelopmentDashboardCache
{
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
            foreach ([30, 60, 90, 120, 365, 'all'] as $days) {
                Cache::forget("dev_dashboard_v3_{$teamId}_{$playerId}_{$days}");
            }
        }
    }
}
