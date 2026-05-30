<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\DashBoard;

use App\Http\Controllers\Controller;
use App\Services\Statistics\TopTenBattingService;
use App\Services\Statistics\TopTenBullpenService;
use App\Services\Statistics\TopTenFitnessService;
use App\Services\Statistics\TopTenTrainingService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetTopTenResults extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $team     = (string) $request->team;
            $option   = (int)    $request->option;
            $range    = (int)    $request->range;
            $cacheKey = "top10_{$team}_{$option}_{$range}";

            $results = Cache::remember($cacheKey, 300, function () use ($team, $option, $range) {
                $topTableBatting  = new TopTenBattingService($team);
                $topTableBullpen  = new TopTenBullpenService($team);
                $topTableTraining = new TopTenTrainingService($team);
                $topTableFitness  = new TopTenFitnessService($team);

                if (1 === $option)  return $topTableBatting->getExitVelocityResults($range);
                if (2 === $option)  return $topTableBatting->getExitVelocityAverageResults($range);
                if (3 === $option)  return $topTableBatting->getTotalSwingsResults($range);
                if (4 === $option)  return $topTableBullpen->getExitVelocityResults($range);
                if (5 === $option)  return $topTableBullpen->getExitVelocityAverageResults($range);
                if (6 === $option)  return $topTableBullpen->getTotalThrowsResults($range);
                if (7 === $option)  return $topTableTraining->getWeightVelocityResults($range);
                if (8 === $option)  return $topTableTraining->getLongTossDistanceResults($range);
                if (9 === $option)  return $topTableTraining->getThrowTrainingsResults($range);
                if (10 === $option) return $topTableFitness->getFitnessWeightResults($range);
                if (11 === $option) return $topTableFitness->getPowerBodyWeightResults($range);
                return [];
            });

            return response()->json([
                'code'    => '052',
                'message' => '',
                'status'  => 'success',
                'data'    => $results,
            ], HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            Log::error($exception->getMessage());
            return response()->json([
                'code'    => '052-E',
                'message' => ' ',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_NOT_FOUND);
        }
    }
}
