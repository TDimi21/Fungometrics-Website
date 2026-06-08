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
            $teams = CoachTeam::with(
                'team',
                'team.team_players.user.profile',
                'team.team_players.user.player',
                'team.team_players.user.positions'
            )->where('coach_id', Auth::user()->id)->get();
            if (0 === $teams->count()) {
                return response()->json([
                    'code' => '036',
                    'message' => 'data teams and players',
                    'status' => 'success',
                    'data' => [],
                ], HttpCodes::HTTP_OK);
            }

            $responseData = $teams->map(function ($coachTeam) {
                $team = $coachTeam->team;
                // Auto-generate join_code if missing (teams created before migration)
                if (empty($team->join_code)) {
                    $team->join_code = Team::generateJoinCode();
                    $team->save();
                }
                return [
                    // Keep `id` aligned with login payload / frontend expectation:
                    // it must be the Team UUID (not coach_teams pivot UUID).
                    'id'          => $team->id,
                    'coach_team_id' => $coachTeam->id,
                    'id_team'     => $team->id,
                    'name'        => $team->name,
                    'logo'        => $team->logo ?? '',
                    'num_players' => count($team->team_players),
                    'join_code'   => $team->join_code,
                    'is_dummy'    => (bool) $team->is_dummy,
                    'owner_team_id' => $team->owner_team_id,
                ];
            });

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
