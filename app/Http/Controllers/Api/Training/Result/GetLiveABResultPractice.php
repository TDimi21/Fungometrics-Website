<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Training\Result;

use App\Http\Controllers\Controller;
use App\Models\LiveABPracticeResult;
use App\Services\ListServiceData;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetLiveABResultPractice extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {

            $data = (new ListServiceData(new LiveABPracticeResult()))->findByUuid($request->uuid)->load(['practice', 'batting.profile', 'pitching.profile']);

            $response = [
                'code' => 'code',
                'message' => '',
                'status' => 'success',
                'data' => $data,
            ];

            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            DB::rollBack();
            $response = [
                'code' => '',
                'message' => ' ',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_NOT_FOUND);
        }
    }
}
