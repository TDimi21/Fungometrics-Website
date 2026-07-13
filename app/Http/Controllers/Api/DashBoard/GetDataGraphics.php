<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\DashBoard;

use App\Http\Controllers\Controller;
use App\Services\Statistics\TeamStatisticsService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
            $teamId   = (string) $request->team;
            $cacheKey = "dashboard_graphics_{$teamId}";

            $data = Cache::remember($cacheKey, 300, function () use ($teamId) {
                $dataGraphs = new TeamStatisticsService($teamId);
                return [
                    'b/s'                          => $dataGraphs->getBallsStrikeData(),
                    'directional_percents'         => $dataGraphs->getDirectionalData(),
                    'type_hits_batting_percents'   => $dataGraphs->getHitTypeBattingData(),
                    'pitch_velocity_average'       => $dataGraphs->averagePitchVelocityData(),
                    'pitch_throws'                 => $dataGraphs->pitchesThrowData(),
                    'type_hits_pitching_percents'  => $dataGraphs->getHitTypePitchingData(),
                    'launch_angle_average_velocity'=> $dataGraphs->launchAngleAverageVelocityData(),
                    'swing_miss_take_percents'     => $dataGraphs->pitchThrowResult(),
                    'contact_spray'                => $dataGraphs->getContactSprayData(),
                    'cage_spray'                   => $dataGraphs->getCageSprayData(),
                    'bullpen_pitches'              => $dataGraphs->getBullpenPitchData(),
                    'long_toss_curve'              => $dataGraphs->getLongTossCurve(),
                    'weighted_ball_curve'          => $dataGraphs->getWeightedBallCurve(),
                ];
            });

            return response()->json([
                'code'    => '047',
                'message' => '',
                'status'  => 'success',
                'data'    => $data,
            ], HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            Log::error($exception->getMessage());
            return response()->json([
                'code'    => '047-E',
                'message' => ' ',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_NOT_FOUND);
        }
    }
}
