<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Services\Planner\WeeklyReportDeliveryAnalyticsService;
use App\Services\Planner\WeeklyReportDeliveryHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class WeeklyReportDeliveryAnalyticsController extends Controller
{
    public function team(string $teamId, Request $request, WeeklyReportDeliveryAnalyticsService $service): JsonResponse
    {
        if (! $this->canAccessTeam($request, $teamId)) {
            return $this->forbidden();
        }

        return response()->json([
            'code' => 'WRDA-T',
            'message' => 'weekly report delivery analytics',
            'status' => 'success',
            'data' => $service->buildTeamAnalytics($teamId, $this->filters($request)),
        ], HttpCodes::HTTP_OK);
    }

    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'audience' => ['nullable', 'string', Rule::in(['coach', 'staff', 'players', 'parents'])],
            'channel' => ['nullable', 'string', Rule::in(['copy', 'email', 'message', 'announcement', 'notification'])],
            'status' => ['nullable', 'string', Rule::in(WeeklyReportDeliveryHistoryService::STATUSES)],
            'template' => ['nullable', 'string'],
            'template_key' => ['nullable', 'string'],
        ]);

        $validated['days'] = max(1, min(365, (int) ($validated['days'] ?? 30)));

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
            'code' => 'WRDA-F',
            'message' => 'not allowed to view weekly report delivery analytics',
            'status' => 'error',
            'data' => [],
        ], HttpCodes::HTTP_FORBIDDEN);
    }
}
