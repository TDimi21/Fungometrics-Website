<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Models\AthleticPerformanceScore;
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

            $cards = Cache::remember("player_cards_v3_{$teamId}", 600, function () use ($teamId) {
                $playerIds = PlayerTeam::where('team_id', $teamId)
                    ->whereNotNull('user_id')
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
                    ->orderByDesc('created_at')
                    ->get()
                    ->unique('user_id')
                    ->keyBy('user_id');

                $latestAthleticScores = AthleticPerformanceScore::whereIn('player_id', $playerIds)
                    ->orderByDesc('calculated_at')
                    ->orderByDesc('created_at')
                    ->get()
                    ->unique('player_id')
                    ->keyBy('player_id');

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

                return $users->map(function (User $user) use ($latestFitness, $latestAthleticScores, $maxBullpenVelo, $maxExitVelo) {
                $profile = $user->profile;
                $player  = $user->player;
                $fitness = $latestFitness->get($user->id);
                $athletic = $latestAthleticScores->get($user->id);

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
                        'sleep_hours' => $fitness->sleep_hours,
                        'sleep_quality_1_to_5' => $fitness->sleep_quality_1_to_5,
                        'recovery_score' => $fitness->recovery_score,
                        'mobility_score' => $fitness->mobility_score,
                    ] : null,

                    // Canonical strength metric for dashboards/leaderboards
                    'fmtrxx_strength_score' => $this->computeFmtrxxStrengthScore($fitness),
                    'athletic_performance' => $athletic ? [
                        'overall_api_score' => $athletic->overall_api_score,
                        'grade_label' => $athletic->grade_label,
                        'projection_label' => $athletic->projection_label,
                        'strength_score' => $athletic->strength_score,
                        'power_score' => $athletic->power_score,
                        'speed_score' => $athletic->speed_score,
                        'baseball_score' => $athletic->baseball_score,
                        'recovery_mobility_score' => $athletic->recovery_mobility_score,
                        'team_percentile' => $athletic->team_percentile,
                        'team_rank' => $athletic->team_rank,
                        'team_count' => $athletic->team_count,
                        'calculated_at' => $athletic->calculated_at,
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

    private function computeFmtrxxStrengthScore(?PlayerFitness $fitness): ?float
    {
        if (!$fitness) {
            return null;
        }

        $values = array_values(array_filter([
            is_numeric($fitness->bench_press) ? (float) $fitness->bench_press : null,
            is_numeric($fitness->front_squat) ? (float) $fitness->front_squat : null,
            is_numeric($fitness->power_clean) ? (float) $fitness->power_clean : null,
            is_numeric($fitness->dead_lift) ? (float) $fitness->dead_lift : null,
        ], static fn ($v) => $v !== null));

        if (empty($values)) {
            return null;
        }

        return round(array_sum($values) / count($values), 1);
    }
}
