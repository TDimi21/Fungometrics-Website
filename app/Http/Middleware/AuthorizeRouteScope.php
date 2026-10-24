<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\BattingPracticeResult;
use App\Models\BullpenPracticeResult;
use App\Models\CagePracticeResult;
use App\Models\CoachTeam;
use App\Models\ExitVelocityPractice;
use App\Models\LiveABPracticeResult;
use App\Models\LongTossPractice;
use App\Models\PlayerTeam;
use App\Models\Practice;
use App\Models\Team;
use App\Models\TeamsLiveAB;
use App\Models\User;
use App\Models\WeightBallPractice;
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

        $this->authorizeResult($request, $user);
        if (in_array((string) $request->route()?->uri(), ['api/result/scripted-bp/plan', 'api/result/scripted-bp/swing'], true)) {
            $id = $request->input('practice_id');
            if ($id !== null) {
                abort_unless(is_string($id), 422);
                $practice = Practice::find($id);
                abort_unless($practice && $this->canAccessPractice($user, $practice), 404);
                $this->authorizePersonalWrite($request, $user, $practice);
                if ($request->is('api/result/scripted-bp/swing') && $request->filled('batter_id')) {
                    $batter = $request->input('batter_id');
                    abort_unless(is_string($batter) && $practice->lineup()->where('user_id', $batter)->exists(), 422);
                    abort_if((string) $user->type === 'player' && $batter !== (string) $user->id, 403);
                }
                if ($request->is('api/result/scripted-bp/plan')) {
                    abort_if($practice->is_completed || $practice->script_plan || $practice->scriptedBpSwings()->exists(), 409, 'An active plan with recorded results cannot be replaced.');
                    abort_if((string) $user->type === 'player' && $practice->user_id !== $user->id, 403);
                }
            }
        }

        $team = $this->firstRouteValue($request, ['team', 'teamId']);
        $teamId = $team ? $this->modelId($team) : null;
        if ($teamId && Team::query()->whereKey($teamId)->exists() && ! $this->canAccessTeam($user, $teamId)) {
            abort(404);
        }

        $player = $this->firstRouteValue($request, ['player', 'playerId']);
        if ( ! $player && str_ends_with((string) $request->route()?->uri(), 'edit/players/{id}')) {
            $player = $request->route('id');
        }
        $playerId = $player ? $this->modelId($player) : null;
        if ($playerId && User::query()->whereKey($playerId)->exists() && ! $this->canAccessPlayer($user, $playerId)) {
            abort(404);
        }

        $practiceValue = $request->route('practice');
        if ((string) $request->route()?->uri() === 'api/training/{uuid}') {
            $practiceValue = $request->route('uuid');
        }
        if ($practiceValue) {
            $practice = $practiceValue instanceof Practice
                ? $practiceValue
                : Practice::query()->find($this->modelId($practiceValue));
            if ($practice) $this->authorizePersonalWrite($request, $user, $practice);
            if ($practice && ! $this->canAccessPractice($user, $practice)) {
                abort(404);
            }
        }

        return $next($request);
    }

    /** Resolve result IDs independently of client-supplied practice IDs. */
    private function authorizeResult(Request $request, User $user): void
    {
        $models = [
            'batting' => BattingPracticeResult::class,
            'bullpen' => BullpenPracticeResult::class,
            'cage' => CagePracticeResult::class,
            'liveab' => LiveABPracticeResult::class,
            'longtoss' => LongTossPractice::class,
            'exitvelocity' => ExitVelocityPractice::class,
            'weightball' => WeightBallPractice::class,
        ];
        $uri = (string) $request->route()?->uri();
        foreach ($models as $type => $model) {
            $collection = 'api/result/'.$type;
            if ($uri !== $collection && $uri !== $collection.'/{uuid}') {
                continue;
            }

            $practiceId = $request->input('practice_id');
            if (null !== $practiceId && ! is_string($practiceId)) {
                abort(422, 'The practice_id must be a string.');
            }

            if ($uri === $collection.'/{uuid}') {
                $result = $model::query()->find($request->route('uuid'));
                // Preserve the controllers' missing-result response contracts.
                if ( ! $result) {
                    return;
                }
                $practice = Practice::query()->find($result->practice_id);
                if ( ! $practice || ! $this->canAccessPractice($user, $practice)) {
                    abort(404);
                }
                // A result cannot be moved to another session through an edit.
                if ($request->exists('practice_id') && $practiceId !== (string) $practice->id) {
                    abort(422, 'The result practice_id cannot be changed.');
                }
            } else {
                // Required-field validation remains the FormRequest's responsibility.
                if (null === $practiceId || '' === $practiceId) {
                    return;
                }
                $practice = Practice::query()->find($practiceId);
                if ( ! $practice || ! $this->canAccessPractice($user, $practice)) {
                    abort(404);
                }
            }

            $this->authorizePersonalWrite($request, $user, $practice);
            if ((string) $user->type === 'player' && !$request->isMethod('GET')) {
                $field = match ($type) { 'batting' => 'batter_id', 'bullpen' => 'pitcher_id', default => 'user_id' };
                if (isset($result)) abort_unless((string) $result->{$field} === (string) $user->id, 403);
                foreach (['user_id', 'pitcher_id', 'batter_id'] as $identity) {
                    if ($request->exists($identity)) abort_unless($request->input($identity) === (string) $user->id, 403);
                }
                $request->merge([$field => (string) $user->id]);
            }
            return;
        }
    }

    private function authorizePersonalWrite(Request $request, User $user, Practice $practice): void
    {
        if ((string) $user->type !== 'player' || $request->isMethod('GET')) return;
        abort_unless($practice->team_id === null && (string) $practice->user_id === (string) $user->id, 403, 'Players can score only their own personal sessions.');
        abort_if($request->filled('team') || $request->filled('team_id'), 403, 'Personal sessions cannot be connected to a team.');
    }

    private function canAccessTeam(User $user, string $teamId): bool
    {
        if ( ! Team::query()->whereKey($teamId)->exists()) {
            return false;
        }

        return CoachTeam::query()->where('coach_id', $user->id)->where('team_id', $teamId)->exists()
            || PlayerTeam::query()->where('user_id', $user->id)->where('team_id', $teamId)->where('actual', true)->exists();
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
            ->whereIn('team_id', PlayerTeam::query()
                ->where('user_id', $playerId)
                ->where('actual', true)
                ->select('team_id'))
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
                ->where('actual', true)
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
