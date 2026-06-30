<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\CoachTeam;
use App\Models\Concerns\UserTypes;
use App\Models\PlayerTeam;
use App\Models\User;

class PlayerMetricsAccess
{
    /**
     * Whether $authUser may read or write fitness/metrics for $targetUserId.
     *
     * Allowed when:
     *   - acting on your own record (player editing themselves), or
     *   - you are a coach on a team the target player belongs to.
     *
     * This stops one player from touching or reading another player's metrics.
     */
    public static function canAccess(?User $authUser, ?string $targetUserId): bool
    {
        if (! $authUser || ! $targetUserId) {
            return false;
        }

        // A player (or anyone) may always act on their own record.
        if ((string) $authUser->id === (string) $targetUserId) {
            return true;
        }

        // Otherwise only a coach who shares a team with the player.
        if ((string) $authUser->type !== UserTypes::COACH->value) {
            return false;
        }

        $playerTeamIds = PlayerTeam::where('user_id', (string) $targetUserId)
            ->pluck('team_id');

        if ($playerTeamIds->isEmpty()) {
            return false;
        }

        return CoachTeam::where('coach_id', $authUser->id)
            ->whereIn('team_id', $playerTeamIds)
            ->exists();
    }
}
