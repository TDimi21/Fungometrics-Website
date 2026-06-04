<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Models\AthleticPerformanceScore;
use App\Models\PlayerFitness;
use App\Services\AthleticPerformanceIndexService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class CalculateAthleticPerformance extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $assessmentId = (string) $request->route('assessment');

            /** @var PlayerFitness|null $assessment */
            $assessment = PlayerFitness::query()->find($assessmentId);

            if (!$assessment) {
                DB::rollBack();

                return response()->json([
                    'code' => '074-NF',
                    'message' => 'assessment not found',
                    'status' => 'error',
                    'data' => [],
                ], HttpCodes::HTTP_NOT_FOUND);
            }

            $service = new AthleticPerformanceIndexService();
            $saved = $service->calculateAndSave($assessment);

            /** @var AthleticPerformanceScore|null $previous */
            $previous = AthleticPerformanceScore::query()
                ->where('player_id', (string) $assessment->user_id)
                ->where('id', '!=', $saved->id)
                ->orderByDesc('calculated_at')
                ->orderByDesc('created_at')
                ->first();

            $trend = $service->getTrend(
                $saved->overall_api_score !== null ? (float) $saved->overall_api_score : null,
                $previous?->overall_api_score !== null ? (float) $previous->overall_api_score : null,
            );

            DB::commit();

            return response()->json([
                'code' => '074',
                'message' => 'athletic performance score calculated',
                'status' => 'success',
                'data' => [
                    'score' => $saved,
                    'trend' => $trend,
                ],
            ], HttpCodes::HTTP_CREATED);
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error('CalculateAthleticPerformance: ' . $exception->getMessage());

            return response()->json([
                'code' => '074-E',
                'message' => 'failed to calculate athletic performance score',
                'status' => 'error',
                'data' => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
