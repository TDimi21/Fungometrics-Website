<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\DailyPlanAssignment;
use App\Models\DailyPlanProgress;
use App\Services\Intelligence\DailyPlanBenchmarkCompletionBridge;
use Auth;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

/**
 * Player: upsert my progress (readiness / item actuals / reflection) for a plan
 * that is assigned to me.
 */
class SaveWorkoutProgress extends Controller
{
    public function __invoke(Request $request, string $id, DailyPlanBenchmarkCompletionBridge $benchmarkCompletionBridge): JsonResponse
    {
        try {
            $userId = Auth::id();

            $assigned = DailyPlanAssignment::where('plan_id', $id)
                ->where('user_id', $userId)
                ->exists();

            if (! $assigned) {
                return response()->json([
                    'code'    => '095-F',
                    'message' => 'this workout is not assigned to you',
                    'status'  => 'error',
                    'data'    => [],
                ], HttpCodes::HTTP_FORBIDDEN);
            }

            $validated = $request->validate([
                'readiness'    => ['nullable', 'array'],
                'items'        => ['nullable', 'array'],
                'items.*'      => ['array'],
                'reflection'   => ['nullable', 'array'],
                'started_at'   => ['nullable', 'date'],
                'completed_at' => ['nullable', 'date'],
            ]);

            $plan = \App\Models\DailyPlan::where('status', 'published')->findOrFail($id);
            $progress = app(\App\Services\Workouts\WorkoutPerformanceService::class)->save($plan, (string) $userId, $validated);

            $bridgeResult = null;
            try {
                $bridgeResult = $benchmarkCompletionBridge->handleDailyPlanProgressUpdate(
                    $id,
                    (string) $userId,
                    $validated,
                    (string) $userId,
                );
            } catch (\Throwable $bridgeException) {
                Log::warning('SaveWorkoutProgress benchmark bridge failed: '.$bridgeException->getMessage(), [
                    'daily_plan_id' => $id,
                    'player_id' => $userId,
                ]);

                $bridgeResult = [
                    'status' => 'failed',
                    'daily_plan_id' => $id,
                    'player_id' => (string) $userId,
                    'warnings' => ['Benchmark task bridge failed, but daily plan progress was saved.'],
                ];
            }

            return response()->json([
                'code'    => '095',
                'message' => 'progress saved',
                'status'  => 'success',
                'data'    => $progress,
                'benchmark_completion_bridge' => $bridgeResult,
            ], HttpCodes::HTTP_OK);
        } catch (\Illuminate\Validation\ValidationException $e) { throw $e;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) { throw $e;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) { throw $e;
        } catch (Exception $e) {
            Log::error('SaveWorkoutProgress: ' . $e->getMessage());

            return response()->json([
                'code'    => '095-E',
                'message' => 'failed to save progress',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
