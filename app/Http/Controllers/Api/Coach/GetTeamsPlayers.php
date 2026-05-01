<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Exceptions\NotFound;
use App\Http\Controllers\Controller;
use App\Http\Resources\TeamsPlayers;
use App\Models\CoachTeam;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;
use Auth;

class GetTeamsPlayers extends Controller
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
                throw new NotFound();
            }
            $responseData = TeamsPlayers::collection($teams);


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
