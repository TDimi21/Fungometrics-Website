<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
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
