<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Services\Planner\CoachWeeklyReportExportService;
use App\Services\Planner\WeeklyReportTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetWeeklyReportTemplates extends Controller
{
    public function index(WeeklyReportTemplateService $service): JsonResponse
    {
        return response()->json([
            'code' => 'WRT-L',
            'message' => 'weekly report templates',
            'status' => 'success',
            'data' => [
                'templates' => $service->listTemplates(),
            ],
        ], HttpCodes::HTTP_OK);
    }

    public function preview(string $teamId, Request $request, CoachWeeklyReportExportService $exportService): JsonResponse
    {
        if (! $this->canAccessTeam($request, $teamId)) {
            return response()->json([
                'code' => 'WRT-F',
                'message' => 'not allowed to preview weekly report template for this team',
                'status' => 'error',
                'data' => [],
            ], HttpCodes::HTTP_FORBIDDEN);
        }

        return response()->json([
            'code' => 'WRT-P',
            'message' => 'weekly report template preview',
            'status' => 'success',
            'data' => $exportService->buildExport($teamId, [
                'start_date' => $request->query('start_date'),
                'end_date' => $request->query('end_date'),
                'days' => $this->days($request),
                'format' => $request->query('format', 'text'),
                'audience' => $request->query('audience', 'coach'),
                'template' => $request->query('template'),
                'include_private_notes' => $request->query('include_private_notes', false),
                'current_user_id' => (string) $request->user()->id,
            ]),
        ], HttpCodes::HTTP_OK);
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

    private function days(Request $request): int
    {
        $days = (int) $request->query('days', 7);

        return max(1, min(365, $days));
    }
}
