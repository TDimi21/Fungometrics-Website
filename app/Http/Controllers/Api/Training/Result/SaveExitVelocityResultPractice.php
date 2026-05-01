<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Training\Result;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Training\Result\ExitVelocityRequest;
use App\Models\ExitVelocityPractice;
use App\Services\CreateServiceData;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class SaveExitVelocityResultPractice extends Controller
{
    /**
     * @param ExitVelocityRequest $request
     * @return JsonResponse
     */
    public function __invoke(ExitVelocityRequest $request): JsonResponse
    {
        try {
            $results = ExitVelocityPractice::where('practice_id', '=', $request->practice_id);
            $count = $results->count();
            $data = $request->validated();
            $data['sort'] = $count++;
            $result['result'] = (new CreateServiceData(new ExitVelocityPractice()))->handle($data);
            $results->get()->push($result['result']);
            $groups = $results->get()->groupBy('user_id');
            $result['setxplayer'] = $groups->map(function ($group) {
                $setMAx= $group->max('set');
                $countBallsxSet = $group->where('set', '=', $setMAx)->count();
                $groupCount =$group->count();
                return ['set'=>$setMAx,
                    'bxs'=>$countBallsxSet,
                    'balls'=>$groupCount
                ];
            });
            $response = [
                'code' => '012',
                'message' => 'save exit velocity practice result',
                'status' => 'success',
                'data' => $result,
            ];
            return response()->json($response, HttpCodes::HTTP_CREATED);
        } catch (Exception $exception) {
            $response = [
                'code' => '012-E',
                'message' => 'error to save exit velocity practice result',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
