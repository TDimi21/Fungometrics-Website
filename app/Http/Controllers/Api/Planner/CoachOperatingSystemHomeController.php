<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Services\Planner\CoachOperatingHomeActionService;
use App\Services\Planner\CoachOperatingSystemHomeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class CoachOperatingSystemHomeController extends Controller
{
    public function team(string $teamId, Request $request, CoachOperatingSystemHomeService $service): JsonResponse
    {
        if (! $this->canAccessTeam($request, $teamId)) {
            return response()->json([
                'code' => 'COH-F',
                'message' => 'not allowed to view operating system home for this team',
                'status' => 'error',
                'data' => [],
            ], HttpCodes::HTTP_FORBIDDEN);
        }

        return response()->json([
            'code' => 'COH-T',
            'message' => 'coach operating system home',
            'status' => 'success',
            'data' => $service->buildHome($teamId, $this->filters($request)),
        ], HttpCodes::HTTP_OK);
    }

    public function actions(string $teamId, Request $request, CoachOperatingHomeActionService $service): JsonResponse
    {
        if (! $this->canAccessTeam($request, $teamId)) {
            return response()->json([
                'code' => 'COH-AF',
                'message' => 'not allowed to view operating home actions for this team',
                'status' => 'error',
                'data' => [],
            ], HttpCodes::HTTP_FORBIDDEN);
        }

        return response()->json([
            'code' => 'COH-A',
            'message' => 'coach operating system home actions',
            'status' => 'success',
            'data' => $service->buildAvailableActions($teamId, [], $this->actionFilters($request)),
        ], HttpCodes::HTTP_OK);
    }

    public function executeAction(string $teamId, Request $request, CoachOperatingHomeActionService $service): JsonResponse
    {
        if (! $this->canAccessTeam($request, $teamId)) {
            return response()->json([
                'team_id' => $teamId,
                'action_type' => (string) $request->input('action_type', ''),
                'status' => 'failed',
                'message' => 'You do not have access to this team.',
                'result' => [],
                'updated_home' => [],
                'warnings' => ['Operating Home actions are coach/admin only.'],
            ], HttpCodes::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'action_type' => ['required', 'string', 'max:100'],
            'payload' => ['nullable', 'array'],
            'confirm' => ['nullable', 'boolean'],
            'days' => ['nullable', 'integer', 'min:7', 'max:365'],
            'weeks' => ['nullable', 'integer', 'min:1', 'max:52'],
        ]);

        $payload = [
            ...($validated['payload'] ?? []),
            'confirm' => (bool) ($validated['confirm'] ?? false),
            'days' => max(7, min(365, (int) ($validated['days'] ?? 365))),
            'weeks' => max(1, min(52, (int) ($validated['weeks'] ?? 8))),
        ];

        $result = $service->executeAction(
            $teamId,
            (string) $validated['action_type'],
            $payload,
            (string) $request->user()?->id,
        );

        return response()->json($result, ($result['status'] ?? null) === 'failed'
            ? HttpCodes::HTTP_UNPROCESSABLE_ENTITY
            : HttpCodes::HTTP_OK);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return $request->validate([
            'date' => ['nullable', 'date'],
            'days' => ['nullable', 'integer', 'min:7', 'max:365'],
            'weeks' => ['nullable', 'integer', 'min:1', 'max:52'],
            'include_health' => ['nullable'],
            'include_alerts' => ['nullable'],
            'include_planner' => ['nullable'],
            'include_benchmarks' => ['nullable'],
            'include_reports' => ['nullable'],
        ]);
    }

    private function actionFilters(Request $request): array
    {
        return $request->validate([
            'days' => ['nullable', 'integer', 'min:7', 'max:365'],
            'weeks' => ['nullable', 'integer', 'min:1', 'max:52'],
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
}
