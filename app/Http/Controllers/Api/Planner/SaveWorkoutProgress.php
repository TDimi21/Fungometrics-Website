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
                'assignment_version'=>['sometimes','integer','min:0'],
                'version' => ['sometimes','integer','min:0'],
                'post_training' => ['sometimes','array'],
                'post_training.pain' => ['sometimes','boolean'],
                'post_training.notes' => ['nullable','string','max:2000'],
                'post_training.overall_effort' => ['nullable','integer','between:0,10'],
                'post_training.overall_fatigue' => ['nullable','integer','between:0,10'],
                'post_training.arm_fatigue' => ['nullable','integer','between:0,10'],
                'post_training.arm_soreness' => ['nullable','integer','between:0,10'],
                'readiness'    => ['nullable', 'array'],
                'readiness.sleep_hours' => ['nullable','numeric','between:0,24'],
                'readiness.sleep_quality' => ['nullable','integer','between:1,5'],
                'readiness.energy' => ['nullable','integer','between:1,5'],
                'readiness.overall_soreness' => ['nullable','integer','between:1,5'],
                'readiness.stress' => ['nullable','integer','between:1,5'],
                'readiness.motivation' => ['nullable','integer','between:1,5'],
                'readiness.arm_soreness' => ['nullable','integer','between:0,10'],
                'readiness.shoulder_soreness' => ['nullable','integer','between:0,10'],
                'readiness.elbow_soreness' => ['nullable','integer','between:0,10'],
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
                'planner_contract_version' => '2.0',
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
