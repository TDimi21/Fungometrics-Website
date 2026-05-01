<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Training;

use App\Http\Controllers\Controller;
use App\Models\Practice;
use App\Services\DeleteServiceData;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class DeletePractice extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $data = (new DeleteServiceData(new Practice()))->handle($request->uuid);
            $response = [
                'code' => '035',
                'message' => 'delete practice '.$request->uuid,
                'status' => 'success',
                'data' => $data,
            ];
            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $response = [
                'code' => '035-E',
                'message' => 'error to delete practice '.$request->uuid,
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
