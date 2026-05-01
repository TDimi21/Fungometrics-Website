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
            $topTableBatting = new TopTenBattingService($request->team);
            $topTableBullpen = new TopTenBullpenService($request->team);
            $topTableTraining = new TopTenTrainingService($request->team);
            $topTableFitness = new TopTenFitnessService($request->team);
            $results = [];
            if(1 === $request->option) {
                $results = $topTableBatting->getExitVelocityResults($request->range);
            }

            if(2 === $request->option) {
                $results = $topTableBatting->getExitVelocityAverageResults($request->range);
            }
            if(3 === $request->option) {
                $results = $topTableBatting->getTotalSwingsResults($request->range);
            }

            if(4 === $request->option) {
                $results = $topTableBullpen->getExitVelocityResults($request->range);
            }
            if(5 === $request->option) {
                $results = $topTableBullpen->getExitVelocityAverageResults($request->range);
            }

            if(6 === $request->option) {
                $results = $topTableBullpen->getTotalThrowsResults($request->range);
            }

            if(7 === $request->option) {
                $results = $topTableTraining->getWeightVelocityResults($request->range);
            }
            if(8 === $request->option) {
                $results = $topTableTraining->getLongTossDistanceResults($request->range);
            }

            if(9 === $request->option) {
                $results = $topTableTraining->getThrowTrainingsResults($request->range);
            }

            if(10 === $request->option) {
                $results = $topTableFitness->getFitnessWeightResults($request->range);
            }

            if(11 === $request->option) {
                $results = $topTableFitness->getPowerBodyWeightResults($request->range);
            }




            $response = [
                'code' => '052',
                'message' => '',
                'status' => 'success',
                'data' => $results,
            ];

            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {

            $response = [
                'code' => '052-E',
                'message' => ' ',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_NOT_FOUND);
        }
    }
}
