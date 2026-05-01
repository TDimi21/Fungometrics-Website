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
            $playersId = PlayerTeam::with('team')
                ->where('team_id', $request->id)
                ->where('actual', true)->pluck('user_id')
                ->all();
            if (0 === count($playersId)) {
                throw new NotFound();
            }

            $playersData = (new RoasterUtils())->getDataPlayers($playersId);
            $response = [
                'code' => '029',
                'message' => 'data players by team '.$request->id,
                'status' => 'success',
                'data' => PlayerTeamResource::collection($playersData),
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
