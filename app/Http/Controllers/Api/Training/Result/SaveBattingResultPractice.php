<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Training\Result;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Training\Result\BattingPracticeRequest;
use App\Models\BattingPracticeResult;
use App\Services\CreateServiceData;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class SaveBattingResultPractice extends Controller
{
    /**
     * @param  BattingPracticeRequest  $request
     * @return JsonResponse
     */
    public function __invoke(BattingPracticeRequest $request): JsonResponse
    {
        try {
            $count = BattingPracticeResult::where('practice_id', $request->practice_id)->count();
            $data = $request->validated();
            $data['sort'] = $count++;
            $data = (new CreateServiceData(model: new BattingPracticeResult()))->handle(data: $data);
            $response = [
                'code' => '008',
                'message' => 'save batting training result',
                'status' => 'success',
                'data' => $data,
            ];
            return response()->json($response, HttpCodes::HTTP_CREATED);
        } catch (Exception $exception) {
            $response = [
                'code' => '008-E',
                'message' => 'error to save batting training result',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());

            return response()->json($response, HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
