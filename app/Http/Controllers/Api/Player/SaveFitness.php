<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Player\FitnessRequest;
use App\Models\PlayerFitness;
use App\Services\CreateServiceData;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class SaveFitness extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(FitnessRequest $request): JsonResponse
    {
        try {

            $saveData = (new CreateServiceData(new PlayerFitness()))->handle($request->validated());
            $response = [
                'code' => '039',
                'message' => 'save fitness to player '.$request->user_id,
                'status' => 'success',
                'data' =>  $saveData,
            ];
            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $response = [
                'code' => '039-E',
                'message' => 'not save fitness to player',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
