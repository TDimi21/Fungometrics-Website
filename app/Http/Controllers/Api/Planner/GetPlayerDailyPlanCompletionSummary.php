<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\DailyPlanAssignment;
use App\Services\Planner\DailyPlanCompletionSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetPlayerDailyPlanCompletionSummary extends Controller
{
    public function __invoke(string $dailyPlanId, Request $request, DailyPlanCompletionSummaryService $service): JsonResponse
    {
        $userId = (string) ($request->user()?->id ?? '');
        $assigned = DailyPlanAssignment::query()
            ->where('plan_id', $dailyPlanId)
            ->where('user_id', $userId)
            ->exists();

        if (! $assigned) {
            return response()->json([
                'code' => 'DPCS-PF',
                'message' => 'this workout summary is not assigned to you',
                'status' => 'error',
                'data' => [],
            ], HttpCodes::HTTP_FORBIDDEN);
        }

        return response()->json([
            'code' => 'DPCS-P',
            'message' => 'daily plan completion summary',
            'status' => 'success',
            'data' => $service->buildPlayerSummary($dailyPlanId, $userId),
        ], HttpCodes::HTTP_OK);
    }
}
