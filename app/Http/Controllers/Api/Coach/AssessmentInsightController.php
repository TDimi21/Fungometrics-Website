<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Models\PlayerAssessment;
use Auth;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class AssessmentInsightController extends Controller
{
    /** POST /coach/assessments/{id}/insights — save coach-edited AI insights. */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'coach_insights' => ['nullable', 'array'],
            ]);

            $teamIds = CoachTeam::where('coach_id', Auth::id())->pluck('team_id')->all();

            $assessment = PlayerAssessment::where('id', $id)
                ->whereIn('team_id', $teamIds)
                ->first();

            if (! $assessment) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'assessment not found for this team',
                    'data'    => [],
                ], HttpCodes::HTTP_NOT_FOUND);
            }

            $assessment->coach_insights = $validated['coach_insights'] ?? null;
            $assessment->save();

            return response()->json(['status' => 'success', 'data' => $assessment], HttpCodes::HTTP_OK);
        } catch (Exception $e) {
            Log::error('AssessmentInsight@update: ' . $e->getMessage());

            return response()->json(['status' => 'error', 'data' => []], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
