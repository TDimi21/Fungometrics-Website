<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Models\AssessmentDraft;
use App\Models\CoachTeam;
use Auth;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class AssessmentDraftController extends Controller
{
    private function coachTeamIds(): array
    {
        return CoachTeam::where('coach_id', Auth::id())->pluck('team_id')->all();
    }

    private function ok($data = null): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $data], HttpCodes::HTTP_OK);
    }

    /** GET /coach/assessment-drafts/{player} */
    public function show(string $player): JsonResponse
    {
        try {
            $draft = AssessmentDraft::where('user_id', $player)
                ->whereIn('team_id', $this->coachTeamIds())
                ->first();

            return $this->ok($draft);
        } catch (Exception $e) {
            Log::error('AssessmentDraft@show: ' . $e->getMessage());

            return $this->ok(null);
        }
    }

    /** POST /coach/assessment-drafts */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => ['required', 'string'],
                'team_id' => ['nullable', 'string'],
                'data'    => ['nullable', 'array'],
            ]);

            $teamIds = $this->coachTeamIds();
            $teamId  = in_array($validated['team_id'] ?? null, $teamIds, true)
                ? $validated['team_id']
                : ($teamIds[0] ?? null);

            $draft = AssessmentDraft::updateOrCreate(
                ['user_id' => $validated['user_id']],
                ['team_id' => $teamId, 'updated_by' => Auth::id(), 'data' => $validated['data'] ?? []]
            );

            return $this->ok($draft);
        } catch (Exception $e) {
            Log::error('AssessmentDraft@store: ' . $e->getMessage());

            return response()->json(['status' => 'error', 'data' => []], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /** DELETE /coach/assessment-drafts/{player} */
    public function destroy(string $player): JsonResponse
    {
        try {
            AssessmentDraft::where('user_id', $player)
                ->whereIn('team_id', $this->coachTeamIds())
                ->delete();

            return $this->ok();
        } catch (Exception $e) {
            Log::error('AssessmentDraft@destroy: ' . $e->getMessage());

            return $this->ok();
        }
    }
}
