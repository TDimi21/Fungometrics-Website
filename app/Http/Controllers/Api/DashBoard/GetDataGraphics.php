<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\DashBoard;

use App\Http\Controllers\Controller;
use App\Services\Statistics\TeamStatisticsService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetDataGraphics extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $dataGraphs = new TeamStatisticsService($request->team);
            $response = [
                'code' => '047',
                'message' => '',
                'status' => 'success',
                'data' => [
                    'b/s'=>$dataGraphs->getBallsStrikeData(),
                    'directional_percents'=>$dataGraphs->getDirectionalData(),
                    'type_hits_batting_percents'=>$dataGraphs->getHitTypeBattingData(),
                    'pitch_velocity_average'=>$dataGraphs->averagePitchVelocityData(),
                    'pitch_throws'=>$dataGraphs->pitchesThrowData(),
                    'type_hits_pitching_percents'=>$dataGraphs->getHitTypePitchingData(),
                    'launch_angle_average_velocity'=>$dataGraphs->launchAngleAverageVelocityData(),
                    'swing_miss_take_percents'=>$dataGraphs->pitchThrowResult()
                ],
            ];
            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $response = [
                'code' => '047-E',
                'message' => ' ',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_NOT_FOUND);
        }
    }
}
