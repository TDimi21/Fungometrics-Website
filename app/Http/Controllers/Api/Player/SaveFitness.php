<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Player\FitnessRequest;
use App\Models\PlayerFitness;
use App\Models\PlayerTeam;
use App\Services\AthleticPerformanceIndexService;
use App\Services\CreateServiceData;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class SaveFitness extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(FitnessRequest $request): JsonResponse
    {
        try {

            $saveData = (new CreateServiceData(new PlayerFitness()))->handle($request->validated());

            try {
                (new AthleticPerformanceIndexService())->calculateAndSave($saveData);
            } catch (Exception $scoreException) {
                Log::warning('SaveFitness score calculation warning: ' . $scoreException->getMessage());
            }

            $teamIds = PlayerTeam::where('user_id', (string) $request->user_id)
                ->whereNotNull('team_id')
                ->pluck('team_id')
                ->unique()
                ->values();

            foreach ($teamIds as $teamId) {
                Cache::forget("player_cards_v3_{$teamId}");
                Cache::forget("player_dev_board_{$teamId}");
                Cache::forget("performance_overview_{$teamId}");
                Cache::forget("dashboard_graphics_{$teamId}");

                foreach ([30, 60, 90, 120] as $days) {
                    Cache::forget("dev_dashboard_{$teamId}_{$request->user_id}_{$days}");
                    Cache::forget("dev_dashboard_v2_{$teamId}_{$request->user_id}_{$days}");
                }
            }

            $response = [
                'code' => '039',
                'message' => 'save fitness to player '.$request->user_id,
                'status' => 'success',
                'data' =>  $saveData,
            ];
            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $response = [
                'code' => '039-E',
                'message' => 'not save fitness to player',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
