<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Exceptions\NotFound;
use App\Http\Controllers\Api\RoasterUtils;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PlayerTeamResource;
use App\Models\PlayerTeam;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetTeamById extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $teamId = (string) $request->id;
            $result = Cache::remember("roster_team_{$teamId}", 60, function () use ($teamId) {
                $playersId = PlayerTeam::query()
                    ->where('team_id', $teamId)
                    ->whereNotNull('user_id')
                    ->pluck('user_id')
                    ->unique()
                    ->values()
                    ->all();
                if (0 === count($playersId)) {
                    return null; // signal empty
                }
                $playersData = (new RoasterUtils())->getDataPlayers($playersId);
                return PlayerTeamResource::collection($playersData)->resolve();
            });

            if ($result === null) {
                return response()->json([
                    'code'    => '029',
                    'message' => 'No players found for team '.$teamId,
                    'status'  => 'success',
                    'data'    => [],
                ], HttpCodes::HTTP_OK);
            }

            $response = [
                'code'    => '029',
                'message' => 'data players by team '.$teamId,
                'status'  => 'success',
                'data'    => $result,
            ];

            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $response = [
                'code' => '029-E',
                'message' => 'Not  Data Found to team id '.$request->id,
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_NOT_FOUND);
        }
    }
}
