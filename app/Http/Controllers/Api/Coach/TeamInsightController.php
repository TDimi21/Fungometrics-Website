<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Models\Team;
use Auth;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class TeamInsightController extends Controller
{
    private function authorizedTeam(string $id): ?Team
    {
        $teamIds = CoachTeam::where('coach_id', Auth::id())->pluck('team_id')->all();

        return Team::where('id', $id)->whereIn('id', $teamIds)->first();
    }

    /** GET /coach/teams/{id}/practice-insight */
    public function show(string $id): JsonResponse
    {
        try {
            $team = $this->authorizedTeam($id);

            return response()->json([
                'status' => 'success',
                'data'   => ['practice_insight' => $team?->practice_insight],
            ], HttpCodes::HTTP_OK);
        } catch (Exception $e) {
            Log::error('TeamInsight@show: ' . $e->getMessage());

            return response()->json(['status' => 'success', 'data' => ['practice_insight' => null]], HttpCodes::HTTP_OK);
        }
    }

    /** POST /coach/teams/{id}/practice-insight */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'practice_insight' => ['nullable', 'string', 'max:1000'],
            ]);

            $team = $this->authorizedTeam($id);
            if (! $team) {
                return response()->json(['status' => 'error', 'data' => []], HttpCodes::HTTP_NOT_FOUND);
            }

            $team->practice_insight = $validated['practice_insight'] ?? null;
            $team->save();

            return response()->json([
                'status' => 'success',
                'data'   => ['practice_insight' => $team->practice_insight],
            ], HttpCodes::HTTP_OK);
        } catch (Exception $e) {
            Log::error('TeamInsight@update: ' . $e->getMessage());

            return response()->json(['status' => 'error', 'data' => []], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
