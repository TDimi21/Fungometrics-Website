<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Training\Result;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Training\Result\BullpenResultEditRequest;
use App\Models\BullpenPracticeResult;
use App\Services\UpdateServiceData;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class EditBullpenResultPractice extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(BullpenResultEditRequest $request, $uuid): JsonResponse
    {
        try {
            DB::beginTransaction();
            $model = (new UpdateServiceData(model: new BullpenPracticeResult()))
                ->handle(id: $uuid, data: $request->validated());


            $response = [
                'code' => 'code',
                'message' => '',
                'status' => 'success',
                'data' => $model,
            ];
            DB::commit();
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
            return response()->json($response, HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
