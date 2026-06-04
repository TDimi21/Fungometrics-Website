<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Models\AthleticPerformanceScore;
use App\Models\PlayerFitness;
use App\Services\AthleticPerformanceIndexService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetAthleticPerformance extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $playerId = (string) $request->route('player');

            $authUser = $request->user();
            if ($authUser && $authUser->tokenCan('player') && (string) $authUser->id !== $playerId) {
                return response()->json([
                    'code' => '073-AUTH',
                    'message' => 'You can only access your own athletic performance',
                    'status' => 'error',
                    'data' => [],
                ], HttpCodes::HTTP_FORBIDDEN);
            }

            $latestAssessment = PlayerFitness::query()
                ->where('user_id', $playerId)
                ->orderByDesc('fitness_date')
                ->orderByDesc('created_at')
                ->first();

            if (!$latestAssessment) {
                return response()->json([
                    'code' => '073-NF',
                    'message' => 'No assessments found for player',
                    'status' => 'error',
                    'data' => [],
                ], HttpCodes::HTTP_NOT_FOUND);
            }

            $service = new AthleticPerformanceIndexService();

            /** @var AthleticPerformanceScore|null $latestScore */
            $latestScore = AthleticPerformanceScore::query()
                ->where('player_id', $playerId)
                ->orderByDesc('calculated_at')
                ->orderByDesc('created_at')
                ->first();

            if (!$latestScore || (string) $latestScore->assessment_id !== (string) $latestAssessment->id) {
                $latestScore = $service->calculateAndSave($latestAssessment);
            }

            /** @var AthleticPerformanceScore|null $previousScore */
            $previousScore = AthleticPerformanceScore::query()
                ->where('player_id', $playerId)
                ->where('id', '!=', $latestScore->id)
                ->orderByDesc('calculated_at')
                ->orderByDesc('created_at')
                ->first();

            $peerValues = AthleticPerformanceScore::query()
                ->where('role', (string) $latestScore->role)
                ->whereNotNull('overall_api_score')
                ->pluck('overall_api_score')
                ->map(fn ($v) => (float) $v)
                ->all();

            $peerPercentile = $service->getPercentile(
                $latestScore->overall_api_score !== null ? (float) $latestScore->overall_api_score : null,
                $peerValues,
                true
            );

            $previousOverall = $previousScore?->overall_api_score !== null
                ? (float) $previousScore->overall_api_score
                : null;
            $latestOverall = $latestScore->overall_api_score !== null
                ? (float) $latestScore->overall_api_score
                : null;

            $delta = ($latestOverall !== null && $previousOverall !== null)
                ? round($latestOverall - $previousOverall, 2)
                : null;

            return response()->json([
                'code' => '073',
                'message' => 'athletic performance summary',
                'status' => 'success',
                'data' => [
                    'latest_assessment' => $latestAssessment,
                    'latest_api_score' => [
                        'overall_api_score' => $latestScore->overall_api_score,
                        'grade_label' => $latestScore->grade_label,
                        'projection_label' => $latestScore->projection_label,
                        'role' => $latestScore->role,
                        'calculated_at' => $latestScore->calculated_at,
                    ],
                    'category_scores' => [
                        'strength_score' => $latestScore->strength_score,
                        'power_score' => $latestScore->power_score,
                        'speed_score' => $latestScore->speed_score,
                        'baseball_score' => $latestScore->baseball_score,
                        'recovery_mobility_score' => $latestScore->recovery_mobility_score,
                    ],
                    'strength_breakdown' => [
                        'lower_body_strength_score' => $latestScore->lower_body_strength_score,
                        'upper_body_strength_score' => $latestScore->upper_body_strength_score,
                        'relative_strength_score' => $latestScore->relative_strength_score,
                    ],
                    'comparison' => [
                        'team_percentile' => $latestScore->team_percentile,
                        'team_rank' => $latestScore->team_rank,
                        'team_count' => $latestScore->team_count,
                        'peer_percentile' => $peerPercentile,
                        'previous_assessment_change' => $delta,
                        'trend' => $service->getTrend($latestOverall, $previousOverall),
                    ],
                    'strengths' => $latestScore->strengths,
                    'weaknesses' => $latestScore->weaknesses,
                    'development_plan' => $latestScore->development_plan,
                ],
            ], HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            Log::error('GetAthleticPerformance: ' . $exception->getMessage());

            return response()->json([
                'code' => '073-E',
                'message' => 'failed to fetch athletic performance summary',
                'status' => 'error',
                'data' => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
