<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Services\Planner\SeasonArchiveDeliveryAnalyticsService;
use App\Services\Planner\SeasonArchiveDeliveryHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class SeasonArchiveDeliveryAnalyticsController extends Controller
{
    public function team(string $teamId, Request $request, SeasonArchiveDeliveryAnalyticsService $service): JsonResponse
    {
        if (! $this->canAccessTeam($request, $teamId)) {
            return $this->forbidden();
        }

        return response()->json([
            'code' => 'SADA-T',
            'message' => 'season archive delivery analytics',
            'status' => 'success',
            'data' => $service->buildTeamAnalytics($teamId, $this->filters($request)),
        ], HttpCodes::HTTP_OK);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'audience' => ['nullable', 'string', Rule::in(['coach', 'staff', 'director', 'players', 'parents'])],
            'channel' => ['nullable', 'string', Rule::in(['copy', 'email', 'message', 'announcement', 'notification'])],
            'status' => ['nullable', 'string', Rule::in(SeasonArchiveDeliveryHistoryService::STATUSES)],
            'template' => ['nullable', 'string'],
            'template_key' => ['nullable', 'string'],
            'archive_type' => ['nullable', 'string'],
        ]);

        $validated['days'] = max(1, min(365, (int) ($validated['days'] ?? 365)));

        return $validated;
    }

    private function canAccessTeam(Request $request, string $teamId): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        if (in_array((string) ($user->type ?? ''), ['admin', 'super_admin'], true)) {
            return true;
        }

        return CoachTeam::query()
            ->where('team_id', $teamId)
            ->where('coach_id', (string) $user->id)
            ->exists();
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'code' => 'SADA-F',
            'message' => 'not allowed to view season archive delivery analytics',
            'status' => 'error',
            'data' => [],
        ], HttpCodes::HTTP_FORBIDDEN);
    }
}
