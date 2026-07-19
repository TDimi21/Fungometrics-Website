<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\CoachTeam;
use App\Models\PlayerTeam;
use App\Models\Practice;
use App\Models\Team;
use App\Models\TeamsLiveAB;
use App\Models\User;
use App\Services\Access\AdministrativeAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeRouteScope
{
    public function __construct(private AdministrativeAccess $administration)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ( ! $user) {
            abort(401);
        }
        if ($this->administration->canManageSubscriptions($user)) {
            return $next($request);
        }

        $team = $this->firstRouteValue($request, ['team', 'teamId']);
        $teamId = $team ? $this->modelId($team) : null;
        if ($teamId && Team::query()->whereKey($teamId)->exists() && ! $this->canAccessTeam($user, $teamId)) {
            abort(404);
        }

        $player = $this->firstRouteValue($request, ['player', 'playerId']);
        $playerId = $player ? $this->modelId($player) : null;
        if ($playerId && User::query()->whereKey($playerId)->exists() && ! $this->canAccessPlayer($user, $playerId)) {
            abort(404);
        }

        $practiceValue = $request->route('practice');
        if ($practiceValue) {
            $practice = $practiceValue instanceof Practice
                ? $practiceValue
                : Practice::query()->find($this->modelId($practiceValue));
            if ($practice && ! $this->canAccessPractice($user, $practice)) {
                abort(404);
            }
        }

        return $next($request);
    }

    private function canAccessTeam(User $user, string $teamId): bool
    {
        if ( ! Team::query()->whereKey($teamId)->exists()) {
            return false;
        }

        return CoachTeam::query()->where('coach_id', $user->id)->where('team_id', $teamId)->exists()
            || PlayerTeam::query()->where('user_id', $user->id)->where('team_id', $teamId)->exists();
    }

    private function canAccessPlayer(User $user, string $playerId): bool
    {
        if ((string) $user->id === $playerId) {
            return true;
        }
        if ('coach' !== (string) $user->type) {
            return false;
        }

        return CoachTeam::query()
            ->where('coach_id', $user->id)
            ->whereIn('team_id', PlayerTeam::query()->where('user_id', $playerId)->select('team_id'))
            ->exists();
    }

    private function canAccessPractice(User $user, Practice $practice): bool
    {
        if ((string) $practice->user_id === (string) $user->id) {
            return true;
        }

        if (null !== $practice->team_id) {
            return $this->canAccessTeam($user, (string) $practice->team_id);
        }

        $liveAbTeamIds = TeamsLiveAB::query()
            ->where('practice_id', $practice->id)
            ->select('team_id');

        return CoachTeam::query()
            ->where('coach_id', $user->id)
            ->whereIn('team_id', clone $liveAbTeamIds)
            ->exists()
            || PlayerTeam::query()
                ->where('user_id', $user->id)
                ->whereIn('team_id', clone $liveAbTeamIds)
                ->exists();
    }

    /** @param array<int, string> $names */
    private function firstRouteValue(Request $request, array $names): mixed
    {
        foreach ($names as $name) {
            $value = $request->route($name);
            if (null !== $value && '' !== $value) {
                return $value;
            }
        }

        return null;
    }

    private function modelId(mixed $value): string
    {
        return (string) (is_object($value) && isset($value->id) ? $value->id : $value);
    }
}
