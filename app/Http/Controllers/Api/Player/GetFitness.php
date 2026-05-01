<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Player;

use App\Exceptions\NotFound;
use App\Http\Controllers\Controller;
use App\Models\PlayerFitness;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetFitness extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $data = PlayerFitness::where('user_id', $request->id)->orderByDesc('fitness_date')->limit(10)->get();
            if (0 === $data->count()) {
                throw new NotFound();
            }
            $response = [
                'code' => '040',
                'message' => 'fitness data for player '.$request->id,
                'status' => 'success',
                'data' => $data,
            ];

            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $response = [
                'code' => '040-E',
                'message' => 'Not Data Found for player '.$request->id,
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_NOT_FOUND);
        }
    }
}
