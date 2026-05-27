<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Models\BullpenPracticeResult;
use App\Models\ExitVelocityPractice;
use App\Models\PlayerFitness;
use App\Models\PlayerTeam;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetTeamPlayerCards extends Controller
{
    /**
     * GET /coach/teams/{team}/player-cards
     *
     * Returns a card for each player on the team containing:
     *  - profile (name, picture, city, state, level)
     *  - physical (height, weight, dob, hit/throw side, jersey #)
     *  - latest fitness metrics (lifts, dash times, body weight)
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $teamId = (string) $request->id;

            $cards = Cache::remember("player_cards_v2_{$teamId}", 600, function () use ($teamId) {
                $playerIds = PlayerTeam::where('team_id', $teamId)
                    ->pluck('user_id')
                    ->all();

                if (empty($playerIds)) {
                    return [];
                }

                $users = User::whereIn('id', $playerIds)
                    ->with(['profile', 'player'])
                    ->get();

                $latestFitness = PlayerFitness::whereIn('user_id', $playerIds)
                    ->orderByDesc('fitness_date')
                    ->get()
                    ->unique('user_id')
                    ->keyBy('user_id');

                // Max bullpen velocity per pitcher (pitcher_id maps to user id)
                $maxBullpenVelo = BullpenPracticeResult::whereIn('pitcher_id', $playerIds)
                    ->selectRaw('pitcher_id, MAX(miles_per_hour) as max_velo')
                    ->groupBy('pitcher_id')
                    ->pluck('max_velo', 'pitcher_id');

                // Max exit velocity per player
                $maxExitVelo = ExitVelocityPractice::whereIn('user_id', $playerIds)
                    ->selectRaw('user_id, MAX(velocity) as max_ev')
                    ->groupBy('user_id')
                    ->pluck('max_ev', 'user_id');

                return $users->map(function (User $user) use ($latestFitness, $maxBullpenVelo, $maxExitVelo) {
                $profile = $user->profile;
                $player  = $user->player;
                $fitness = $latestFitness->get($user->id);

                return [
                    'id'    => $user->id,
                    'email' => $user->email,
                    'phone' => $user->phone ?? null,

                    // ── Profile ───────────────────────────────────────────────
                    'profile' => $profile ? [
                        'first_name' => $profile->first_name,
                        'last_name'  => $profile->last_name,
                        'full_name'  => trim("{$profile->first_name} {$profile->last_name}"),
                        'picture'    => $profile->picture,
                        'city'       => $profile->city,
                        'state'      => $profile->state,
                        'zip'        => $profile->zip,
                        'level'      => $profile->level,
                    ] : null,

                    // ── Physical ──────────────────────────────────────────────
                    'physical' => $player ? [
                        'height_ft'      => $player->height_in_ft,
                        'height_in'      => $player->height_in_inch,
                        'born_date'      => $player->born_date,
                        'hit_side'       => $player->hit_side,
                        'throw_side'     => $player->throw_side,
                        'jersey_number'  => $player->number_in_shirt,
                    ] : null,

                    // ── Latest Fitness ────────────────────────────────────────
                    'fitness' => $fitness ? [
                        'date'        => $fitness->fitness_date,
                        'body_weight' => $fitness->body_weight,
                        'bench_press' => $fitness->bench_press,
                        'front_squat' => $fitness->front_squat,
                        'back_squat'  => $fitness->back_squat,
                        'power_clean' => $fitness->power_clean,
                        'dead_lift'   => $fitness->dead_lift,
                        'yd_40_dash'  => $fitness->yd_40_dash,
                        'yd_60_dash'  => $fitness->yd_60_dash,
                    ] : null,

                    // ── Session Velocity Stats ─────────────────────────────────
                    'stats' => [
                        'max_fastball'  => $maxBullpenVelo->get($user->id) ? (float) $maxBullpenVelo->get($user->id) : null,
                        'max_exit_velo' => $maxExitVelo->get($user->id)    ? (float) $maxExitVelo->get($user->id)    : null,
                    ],
                ];
                })->values()->all();
            });

            return response()->json([
                'code'    => '061',
                'message' => '',
                'status'  => 'success',
                'data'    => $cards,
            ], HttpCodes::HTTP_OK);

        } catch (Exception $exception) {
            Log::error($exception->getMessage());
            return response()->json([
                'code'    => '061-E',
                'message' => 'Error retrieving player cards',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
