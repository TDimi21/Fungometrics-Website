<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Models\PlayerFitness;
use App\Models\PlayerTeam;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

            // All player IDs on this team
            $playerIds = PlayerTeam::where('team_id', $teamId)
                ->pluck('user_id')
                ->all();

            if (empty($playerIds)) {
                return response()->json([
                    'code'    => '061',
                    'message' => 'No players found for this team',
                    'status'  => 'success',
                    'data'    => [],
                ], HttpCodes::HTTP_OK);
            }

            // Load users with their profile and player physical data
            $users = User::whereIn('id', $playerIds)
                ->with(['profile', 'player'])
                ->get();

            // Load the most recent fitness entry per player
            $latestFitness = PlayerFitness::whereIn('user_id', $playerIds)
                ->orderByDesc('fitness_date')
                ->get()
                ->unique('user_id')         // keep only the latest per player
                ->keyBy('user_id');

            $cards = $users->map(function (User $user) use ($latestFitness) {
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
                ];
            })->values()->all();

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
