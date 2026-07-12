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

class MarkDailyPlanUpdateSeen extends Controller
{
    public function __invoke(string $id, Request $request, DailyPlanPlayerUpdateService $updateService): JsonResponse
    {
        try {
            $userId = (string) Auth::id();
            $assigned = DailyPlanAssignment::query()
                ->where('plan_id', $id)
                ->where('user_id', $userId)
                ->exists();

            $planExists = DailyPlan::query()
                ->where('id', $id)
                ->where('status', 'published')
                ->exists();

            if (! $assigned || ! $planExists) {
                return response()->json([
                    'code' => '095-NF',
                    'message' => 'workout update not found',
                    'status' => 'error',
                    'data' => [],
                ], HttpCodes::HTTP_NOT_FOUND);
            }

            $status = $updateService->markUpdateSeen(
                $id,
                $userId,
                $request->filled('revision_id') ? (string) $request->input('revision_id') : null
            );

            return response()->json([
                'code' => '095',
                'message' => 'workout update marked seen',
                'status' => 'success',
                'data' => $status,
            ], HttpCodes::HTTP_OK);
        } catch (Exception $e) {
            Log::error('MarkDailyPlanUpdateSeen: '.$e->getMessage());

            return response()->json([
                'code' => '095-E',
                'message' => 'failed to mark workout update seen',
                'status' => 'error',
                'data' => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
