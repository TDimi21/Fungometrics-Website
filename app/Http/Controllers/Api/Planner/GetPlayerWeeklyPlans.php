<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Services\Planner\PlayerWeeklyPlanService;
use Auth;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetPlayerWeeklyPlans extends Controller
{
    public function __invoke(Request $request, PlayerWeeklyPlanService $service): JsonResponse
    {
        try {
            $validated = $request->validate([
                'start_date' => ['nullable', 'date'],
                'end_date' => ['nullable', 'date'],
                'days' => ['nullable', 'integer', 'min:1', 'max:31'],
                'include_completed' => ['nullable', 'boolean'],
            ]);

            $playerId = (string) Auth::id();
            if ($playerId === '') {
                return response()->json([
                    'code' => '101-UNAUTH',
                    'message' => 'player not authenticated',
                    'status' => 'error',
                    'data' => [],
                ], HttpCodes::HTTP_UNAUTHORIZED);
            }

            return response()->json([
                'code' => '101',
                'message' => 'player weekly plans',
                'status' => 'success',
                'data' => $service->buildForPlayer($playerId, $validated),
            ], HttpCodes::HTTP_OK);
        } catch (Exception $e) {
            Log::error('GetPlayerWeeklyPlans: '.$e->getMessage());

            return response()->json([
                'code' => '101-E',
                'message' => 'failed to fetch player weekly plans',
                'status' => 'error',
                'data' => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
