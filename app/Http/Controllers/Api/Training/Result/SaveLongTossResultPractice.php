<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Training\Result;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Training\Result\LongTossRequest;
use App\Models\LongTossPractice;
use App\Services\CreateServiceData;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class SaveLongTossResultPractice extends Controller
{
    /**
     * @param LongTossRequest $request
     * @return JsonResponse
     */
    public function __invoke(LongTossRequest $request): JsonResponse
    {
        try {
            $results = LongTossPractice::where('practice_id', '=', $request->practice_id);
            $count = $results->count();
            $data = $request->validated();
            $data['sort'] = $count++;
            $result['result'] = (new CreateServiceData(new LongTossPractice()))->handle($data);
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
                'code' => '011',
                'message' => 'save long toss training result',
                'status' => 'success',
                'data' => $result,
            ];

            return response()->json($response, HttpCodes::HTTP_CREATED);
        } catch (Exception $exception) {
            $response = [
                'code' => '011-E',
                'message' => 'error to save long toss training result  ',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
