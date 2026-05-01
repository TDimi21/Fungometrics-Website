<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\DashBoard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Dashboard\ChartRequest;
use App\Services\Statistics\ChartsDataService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetDataCharts extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(ChartRequest $request): JsonResponse
    {
        try {
            $data = new ChartsDataService();
            $params = $request->validated();
            //avg exit velocity -- live
            if(1 === $request->type) {
                $data = $data->getAverageLiveExitVelocity($params, $request->range);
            }
            //max exit velocity -- exit velocity
            if(2 === $request->type) {
                $data = $data->getMaxExitVelocity($params, $request->range);
            }
            //max cage distance -- cage
            if(3 === $request->type) {
                $data = $data->getMaxCageDistance($params, $request->range);
            }
            //total strike percent -- pitching
            if(4 === $request->type) {
                $data = $data->getStrikePercent($params, $request->range);
            }
            //max fb velocity -- pitching
            if(5 === $request->type) {
                $data = $data->getMaxFbVelocity($params, $request->range);
            }
            //average throw velocity for each weigth -- weigth ball
            if(6 === $request->type) {
                $data = $data->getAvgThrowVelocity($params, $request->range);
            }
            //max distance throws with 0 hops -- long toss
            if(7 === $request->type) {
                $data = $data->getMaxDistanceThrows($params, $request->range);
            }
            //average traininig exit velocity per session -- exit velocity
            if(8 === $request->type) {
                $data = $data->getAvgExitVelocity($params, $request->range);
            }

            $response = [
                'code' => '048',
                'message' => '',
                'status' => 'success',
                'data' => $data,
            ];
            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $response = [
                'code' => '048-E',
                'message' => ' ',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_NOT_FOUND);
        }
    }
}
