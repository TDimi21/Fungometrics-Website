<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Exceptions\NotCreated;
use App\Exceptions\NotFound;
use App\Exceptions\UpdateException;
use App\Models\CoachTeam;
use App\Models\Concerns\UserTypes;
use App\Models\Player;
use App\Models\PlayerTeam;
use App\Models\Profile;
use App\Models\User;
use App\Services\CreateServiceData;
use App\Services\ListServiceData;
use Illuminate\Database\Eloquent\Model;

class CoachUtils
{
    /** Coaches included per team before an upgrade is required. */
    public const COACH_SEAT_LIMIT = 5;

    /** True if the user has any (active) coach link to the team. */
    public static function isCoachOnTeam(string $userId, string $teamId): bool
    {
        return CoachTeam::where('coach_id', $userId)
            ->where('team_id', $teamId)
            ->exists();
    }

    /** True if the user is a head (main) coach of the team. */
    public static function isHeadCoach(string $userId, string $teamId): bool
    {
        return CoachTeam::where('coach_id', $userId)
            ->where('team_id', $teamId)
            ->where('is_main', true)
            ->exists();
    }

    /**
     * Subscription plan that governs the team's capacity: the head coach's plan.
     * Falls back to 'free' when no head coach is resolvable.
     */
    public static function teamHeadCoachPlan(string $teamId): string
    {
        $main = CoachTeam::where('team_id', $teamId)
            ->where('is_main', true)
            ->first();

        if ($main) {
            $headPlan = User::find($main->coach_id)?->subscription_plan;
            if ($headPlan) {
                return $headPlan;
            }
        }

        return 'free';
    }

    /**
     * True if adding another coach would exceed the team's seat allowance.
     * Coach Pro lifts the cap entirely.
     */
    public static function coachSeatLimitReached(string $teamId): bool
    {
        if (self::teamHeadCoachPlan($teamId) === 'coach_pro') {
            return false;
        }

        return CoachTeam::where('team_id', $teamId)->count() >= self::COACH_SEAT_LIMIT;
    }

    /**
     * @param  array  $data
     * @param  string  $type
     * @return array
     *
     * @throws NotCreated
     */
    public static function saveNewUser(array $data, string $type = 'player'): array
    {
        $response = collect();
        $response_user = (new CreateServiceData(new User()))->handle([
            'phone' => $data['phone'],
            'type' => $type,
            'status'=>true
        ]);
        $nameArr = $data['name'] ?? [];
        $response_profile = (new CreateServiceData(new Profile()))->handle([
            'user_id' => $response_user->id,
            'first_name' => $nameArr['first'] ?? $data['first_name'] ?? '',
            'last_name'  => $nameArr['last']  ?? $data['last_name']  ?? '',
        ]);

        if ($type === UserTypes::COACH->value) {
            (new CreateServiceData(new CoachTeam()))->handle([
                'coach_id' => $response_user->id,
                'team_id' => $data['team'],
                'actual' => true,
            ]);
        }
        if ($type === UserTypes::PLAYER->value) {
            (new CreateServiceData(new PlayerTeam()))->handle([
                'user_id' => $response_user->id,
                'team_id' => $data['team'],
                'actual' => true,
            ]);
            if (isset($data['player']) && is_array($data['player'])) {
                $playerData = array_filter([
                    'user_id' => $response_user->id,
                    'grad_year' => $data['player']['grad_year'] ?? null,
                ], fn ($value) => $value !== null && $value !== '');

                if (count($playerData) > 1) {
                    (new CreateServiceData(new Player()))->handle($playerData);
                }
            }
        }
        $response->put('user', $response_user);
        $response->put('profile', $response_profile);

        return $response->all();
    }

    /**
     * @param  Model  $player
     * @param  string  $team_id
     * @return Model
     *
     * @throws NotCreated
     * @throws NotFound
     * @throws UpdateException
     */
    public static function addPlayerToRoaster(Model $player, string $team_id): array
    {
        // Check for any row — including soft-deleted — to avoid duplicate unique violation
        $existing = PlayerTeam::withTrashed()
            ->where('user_id', $player->id)
            ->where('team_id', $team_id)
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                // Restore the soft-deleted link instead of creating a duplicate
                $existing->restore();
                $existing->actual = true;
                $existing->save();
                return ['data' => $existing->toArray(), 'exist' => false];
            }
            return ['data' => $existing->toArray(), 'exist' => true];
        }

        return [
            'data' => (new CreateServiceData(new PlayerTeam()))
                ->handle([
                    'team_id' => $team_id,
                    'user_id' => $player->id,
                    'actual'  => true,
                ])->toArray(),
            'exist' => false,
        ];
    }
}
