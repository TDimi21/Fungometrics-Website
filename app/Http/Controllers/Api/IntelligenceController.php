<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Models\PlayerTeam;
use App\Models\Team;
use App\Models\User;
use App\Services\Intelligence\DecisionEngine;
use App\Services\Intelligence\PlayerIntelligenceService;
use App\Services\Intelligence\TeamIntelligenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class IntelligenceController extends Controller
{
    public function __construct(
        private readonly TeamIntelligenceService $teamIntelligence,
        private readonly PlayerIntelligenceService $playerIntelligence,
        private readonly DecisionEngine $decisionEngine,
    ) {
    }

    public function team(Request $request, string $teamId): JsonResponse
    {
        if (! Team::query()->whereKey($teamId)->exists()) {
            return $this->notFound('Team not found');
        }

        if (! $this->coachCanAccessTeam($request, $teamId)) {
            return $this->forbidden('You do not have access to this team');
        }

        $days = $this->days($request);
        $snapshot = $this->teamIntelligence->build($teamId, $days);
        $snapshot['decision_brief'] = $this->decisionEngine->buildTeamDecisionBrief($teamId, $days);

        return response()->json($snapshot);
    }

    public function player(Request $request, string $teamId, string $playerId): JsonResponse
    {
        if (! Team::query()->whereKey($teamId)->exists()) {
            return $this->notFound('Team not found');
        }

        if (! User::query()->whereKey($playerId)->exists()) {
            return $this->notFound('Player not found');
        }

        if (! $this->coachCanAccessTeam($request, $teamId)) {
            return $this->forbidden('You do not have access to this team');
        }

        if (! PlayerTeam::query()->where('team_id', $teamId)->where('user_id', $playerId)->exists()) {
            return $this->forbidden('Player is not linked to this team');
        }

        return response()->json(
            $this->playerIntelligence->build($teamId, $playerId, $this->days($request))
        );
    }

    private function days(Request $request): int
    {
        $days = (int) $request->query('days', 365);

        return max(7, min(365, $days));
    }

    private function coachCanAccessTeam(Request $request, string $teamId): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        return CoachTeam::query()
            ->where('team_id', $teamId)
            ->where('coach_id', (string) $user->id)
            ->exists();
    }

    private function notFound(string $message): JsonResponse
    {
        return response()->json([
            'code' => 'INTEL-NF',
            'message' => $message,
            'status' => 'error',
            'data' => [],
        ], HttpCodes::HTTP_NOT_FOUND);
    }

    private function forbidden(string $message): JsonResponse
    {
        return response()->json([
            'code' => 'INTEL-AUTH',
            'message' => $message,
            'status' => 'error',
            'data' => [],
        ], HttpCodes::HTTP_FORBIDDEN);
    }
}
