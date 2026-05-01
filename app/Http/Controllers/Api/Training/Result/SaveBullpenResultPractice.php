<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Training\Result;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Training\Result\BullpenResultRequest;
use App\Models\BullpenPracticeResult;
use App\Services\CreateServiceData;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class SaveBullpenResultPractice extends Controller
{
    /**
     * @param  BullpenResultRequest  $request
     * @return JsonResponse
     */
    public function __invoke(BullpenResultRequest $request): JsonResponse
    {
        try {
            $count = BullpenPracticeResult::where('practice_id', $request->practice_id)->count();
            $data = $request->validated();
            $data['sort'] = $count++;
            $result = (new CreateServiceData(new BullpenPracticeResult()))->handle($data);
            $response = [
                'code' => '009',
                'message' => 'save bullpen result',
                'status' => 'success',
                'data' => $result
            ];
            return response()->json($response, HttpCodes::HTTP_CREATED);
        } catch (Exception $exception) {
            $response = [
                'code' => '009-E',
                'message' => 'error to save bullpen result',
                'status' => 'error',
                'data' => []
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
