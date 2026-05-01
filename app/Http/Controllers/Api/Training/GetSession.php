<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Training;

use App\Exceptions\NotFound;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PracticeSessionResource;
use App\Models\CagePracticeMeta;
use App\Models\Concerns\PracticeTypes;
use App\Models\Practice;
use App\Services\ListServiceData;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetSession extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $practiceData = (new ListServiceData(new Practice()))->findByUuid($request->uuid);
            $dataResponse = [
                'practice' => $practiceData,
                'players' => $practiceData->lineup,
            ];
            if ($practiceData->type === PracticeTypes::CAGE->value) {
                $dataResponse['meta'] = (new ListServiceData(new CagePracticeMeta()))->byParamFirst(
                    'practice_id',
                    $practiceData->id
                );
            }
            $response = [
                'code' => '007',
                'message' => 'session training',
                'status' => 'success',
                'data' => new PracticeSessionResource($dataResponse),
            ];

            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $message = 'error to process data';
            $code = HttpCodes::HTTP_INTERNAL_SERVER_ERROR;
            if ($exception instanceof NotFound) {
                $message = 'Not Data Found';
                $code = HttpCodes::HTTP_NOT_FOUND;
            }
            $response = [
                'code' => '007-E',
                'message' => $message,
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());

            return response()->json($response, $code);
        }
    }
}
