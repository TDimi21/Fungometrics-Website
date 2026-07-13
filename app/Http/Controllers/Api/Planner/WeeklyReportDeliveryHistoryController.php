<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Models\WeeklyReportDelivery;
use App\Services\Planner\WeeklyReportDeliveryHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class WeeklyReportDeliveryHistoryController extends Controller
{
    public function index(string $teamId, Request $request, WeeklyReportDeliveryHistoryService $service): JsonResponse
    {
        if (! $this->canAccessTeam($request, $teamId)) {
            return $this->forbidden();
        }

        $filters = $this->filters($request);

        return response()->json([
            'code' => 'WRDH-L',
            'message' => 'weekly report delivery history',
            'status' => 'success',
            'data' => [
                'summary' => $service->buildDeliverySummary($teamId, $filters),
                'deliveries' => $service->listTeamDeliveries($teamId, $filters),
            ],
        ], HttpCodes::HTTP_OK);
    }

    public function show(string $deliveryId, Request $request, WeeklyReportDeliveryHistoryService $service): JsonResponse
    {
        $delivery = WeeklyReportDelivery::query()->find($deliveryId);
        if (! $delivery || ! $this->canAccessTeam($request, (string) $delivery->team_id)) {
            return $this->forbidden();
        }

        return response()->json([
            'code' => 'WRDH-S',
            'message' => 'weekly report delivery detail',
            'status' => 'success',
            'data' => [
                'delivery' => $service->getDelivery($deliveryId),
            ],
        ], HttpCodes::HTTP_OK);
    }

    public function recordCopy(string $deliveryId, Request $request, WeeklyReportDeliveryHistoryService $service): JsonResponse
    {
        $delivery = WeeklyReportDelivery::query()->find($deliveryId);
        if (! $delivery || ! $this->canAccessTeam($request, (string) $delivery->team_id)) {
            return $this->forbidden();
        }

        return response()->json([
            'code' => 'WRDH-C',
            'message' => 'weekly report delivery copy action recorded',
            'status' => 'success',
            'data' => [
                'delivery' => $service->recordCopyAction($deliveryId, (string) $request->user()->id),
            ],
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
            'code' => 'WRDH-F',
            'message' => 'not allowed to view weekly report delivery history',
            'status' => 'error',
            'data' => [],
        ], HttpCodes::HTTP_FORBIDDEN);
    }
}
