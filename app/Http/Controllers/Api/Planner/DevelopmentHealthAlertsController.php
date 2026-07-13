<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Services\Planner\DevelopmentHealthAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class DevelopmentHealthAlertsController extends Controller
{
    public function team(string $teamId, Request $request, DevelopmentHealthAlertService $service): JsonResponse
    {
        if (! $this->canAccessTeam($request, $teamId)) {
            return $this->forbidden();
        }

        return response()->json([
            'code' => 'DHA-T',
            'message' => 'development health alerts',
            'status' => 'success',
            'data' => $service->buildTeamAlerts($teamId, $this->filters($request)),
        ], HttpCodes::HTTP_OK);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'days' => ['nullable', 'integer', 'min:7', 'max:365'],
            'weeks' => ['nullable', 'integer', 'min:1', 'max:52'],
            'severity_threshold' => ['nullable', 'in:critical,high,medium,low'],
            'include_resolved' => ['nullable'],
            'population_metric_key' => ['nullable', 'string'],
        ]);
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
            'code' => 'DHA-F',
            'message' => 'not allowed to view development health alerts for this team',
            'status' => 'error',
            'data' => [],
        ], HttpCodes::HTTP_FORBIDDEN);
    }
}
