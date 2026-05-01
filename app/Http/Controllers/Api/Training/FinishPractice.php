<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Training;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Training\FinishPracticeRequest;
use App\Models\Practice;
use App\Services\UpdateServiceData;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class FinishPractice extends Controller
{
    /**
     * @param FinishPracticeRequest $request
     * @return JsonResponse
     */
    public function __invoke(FinishPracticeRequest $request): JsonResponse
    {
        try {
            $data_request = $request->validated();
            $data_request['finish'] = Carbon::now();
            $result = (new UpdateServiceData(new Practice()))->handle(
                $request->uuid,
                $data_request
            );
            $response = [
                'code' => '015',
                'message' => 'update practice - set finish',
                'status' => 'success',
                'data' => $result,
            ];

            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $message = 'error to update practice';
            $code = HttpCodes::HTTP_INTERNAL_SERVER_ERROR;
            if ('NOT UPDATE, Not Data Found' === $exception->getMessage()) {
                $message = 'error to update practice - Not Practices Found';
                $code = HttpCodes::HTTP_NOT_FOUND;
            }

            $response = [
                'code' => '015-E',
                'message' => $message,
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, $code);
        }
    }
}
