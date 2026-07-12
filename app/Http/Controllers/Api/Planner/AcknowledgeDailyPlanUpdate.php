<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\DailyPlan;
use App\Models\DailyPlanAssignment;
use App\Services\Planner\DailyPlanPlayerUpdateService;
use Auth;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class AcknowledgeDailyPlanUpdate extends Controller
{
    public function __invoke(string $dailyPlanId, Request $request, DailyPlanPlayerUpdateService $updateService): JsonResponse
    {
        try {
            $playerId = (string) Auth::id();
            $assigned = DailyPlanAssignment::query()
                ->where('plan_id', $dailyPlanId)
                ->where('user_id', $playerId)
                ->exists();

            $planExists = DailyPlan::query()
                ->where('id', $dailyPlanId)
                ->where('status', 'published')
                ->exists();

            if (! $assigned || ! $planExists) {
                return response()->json([
                    'code' => '096-NF',
                    'message' => 'workout update not found',
                    'status' => 'error',
                    'data' => [],
                ], HttpCodes::HTTP_NOT_FOUND);
            }

            $status = $updateService->acknowledgeUpdate(
                $dailyPlanId,
                $playerId,
                $request->filled('revision_id') ? (string) $request->input('revision_id') : null,
                [
                    'source' => 'player_daily_plan_update_banner',
                    'client_payload' => $request->except(['revision_id']),
                ]
            );

            return response()->json([
                'code' => '096',
                'message' => 'workout update acknowledged',
                'status' => 'success',
                'data' => $status,
            ], HttpCodes::HTTP_OK);
        } catch (Exception $e) {
            Log::error('AcknowledgeDailyPlanUpdate: '.$e->getMessage());

            return response()->json([
                'code' => '096-E',
                'message' => 'failed to acknowledge workout update',
                'status' => 'error',
                'data' => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
