<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Exceptions\NotFound;
use App\Http\Controllers\Api\RoasterUtils;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PlayerTeamResource;
use App\Models\CoachTeam;
use App\Models\PlayerTeam;
use Auth;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetPlayersList extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $teamsIds = CoachTeam::where('coach_id', Auth::id())->pluck('team_id')->all();
            $playersTeamsIds = PlayerTeam::whereIn('team_id', $teamsIds)->pluck('user_id')->all();
            if (0=== count($playersTeamsIds)) {
                throw new NotFound();
            }
            $response = [
                'code' => '023',
                'message' => 'list roster players',
                'status' => 'success',
                'data' => PlayerTeamResource::collection((new RoasterUtils())->getDataPlayers($playersTeamsIds))
            ];

            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $response = [
                'code' => '023-E',
                'message' => 'Not Players Found ',
                'status' => 'error',
                'data' => []
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_NOT_FOUND);
        }
    }
}
