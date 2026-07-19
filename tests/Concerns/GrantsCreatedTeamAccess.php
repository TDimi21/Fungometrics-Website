<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\CoachTeam;
use App\Models\PlayerTeam;
use App\Models\Team;
use BackedEnum;

/**
 * Makes legacy happy-path fixtures explicit about team scope.
 *
 * Security tests intentionally do not use this trait so unrelated users remain
 * unrelated and continue to receive privacy-safe 404 responses.
 */
trait GrantsCreatedTeamAccess
{
    protected function setUpGrantsCreatedTeamAccess(): void
    {
        Team::created(function (Team $team): void {
            $user = auth()->user();
            if ( ! $user) {
                return;
            }

            $type = $user->type instanceof BackedEnum ? $user->type->value : (string) $user->type;
            if ('coach' === $type) {
                CoachTeam::query()->firstOrCreate(
                    ['coach_id' => $user->id, 'team_id' => $team->id],
                    ['is_main' => true]
                );

                return;
            }

            if ('player' === $type) {
                PlayerTeam::query()->firstOrCreate(
                    ['user_id' => $user->id, 'team_id' => $team->id],
                    ['actual' => true]
                );
            }
        });
    }
}
