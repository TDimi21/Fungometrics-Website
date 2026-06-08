<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Exceptions\NotFound;
use App\Http\Controllers\Controller;
use App\Http\Resources\TeamsPlayers;
use App\Models\CoachTeam;
use App\Models\Team;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;
use Auth;

class GetTeamsPlayersV2 extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $coachId = (string) Auth::user()->id;
            $cacheKey = "coach_teams_v2_{$coachId}";

            $responseData = Cache::remember($cacheKey, 60, function () use ($coachId) {
                $coachTeams = CoachTeam::where('coach_id', $coachId)
                    ->get(['id', 'team_id']);

                if (0 === $coachTeams->count()) {
                    return [];
                }

                $teamIds = $coachTeams->pluck('team_id')->unique()->values()->all();
                $coachTeamByTeam = $coachTeams->keyBy('team_id');

                $teams = Team::query()
                    ->whereIn('id', $teamIds)
                    ->withCount('team_players')
                    ->get(['id', 'name', 'logo', 'join_code', 'is_dummy', 'owner_team_id']);

                return $teams->map(function (Team $team) use ($coachTeamByTeam) {
                    // Auto-generate join_code if missing (teams created before migration)
                    if (empty($team->join_code)) {
                        $team->join_code = Team::generateJoinCode();
                        $team->save();
                    }

                    return [
                        // Keep `id` aligned with login payload / frontend expectation:
                        // it must be the Team UUID (not coach_teams pivot UUID).
                        'id'            => $team->id,
                        'coach_team_id' => $coachTeamByTeam->get($team->id)?->id,
                        'id_team'       => $team->id,
                        'name'          => $team->name,
                        'logo'          => $team->logo ?? '',
                        'num_players'   => (int) ($team->team_players_count ?? 0),
                        'join_code'     => $team->join_code,
                        'is_dummy'      => (bool) $team->is_dummy,
                        'owner_team_id' => $team->owner_team_id,
                    ];
                })->values();
            });

            if (count($responseData) === 0) {
                return response()->json([
                    'code' => '036',
                    'message' => 'data teams and players',
                    'status' => 'success',
                    'data' => [],
                ], HttpCodes::HTTP_OK);
            }

            $response = [
                'code' => '036',
                'message' => 'data teams and players',
                'status' => 'success',
                'data' =>  $responseData,
            ];
            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $response = [
                'code' => '036-E',
                'message' => 'Not  Data Found',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_NOT_FOUND);
        }
    }
}
