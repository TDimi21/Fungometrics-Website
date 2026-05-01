<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Models\PlayerTeam;
use App\Services\DeleteServiceData;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class RemovePlayers extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $playerTeam = PlayerTeam::where([
                'team_id' => $request->team,
                'user_id' => $request->player
            ])->firstOrFail();
            $delete = (new DeleteServiceData(new PlayerTeam()))->handle($playerTeam->id);
            $response = [
                'code' => '034',
                'message' => 'remove player for team',
                'status' => 'success',
                'data' =>  $delete,
            ];

            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $response = [
                'code' => '034-E',
                'message' => 'error to remove player for team',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
