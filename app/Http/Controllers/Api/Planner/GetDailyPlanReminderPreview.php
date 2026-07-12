<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Models\DailyPlan;
use App\Services\Planner\DailyPlanReminderService;
use Auth;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetDailyPlanReminderPreview extends Controller
{
    public function __invoke(string $dailyPlanId, Request $request, DailyPlanReminderService $reminderService): JsonResponse
    {
        try {
            if (! $this->coachCanAccessPlan($dailyPlanId)) {
                return response()->json([
                    'code' => '0C3-F',
                    'message' => 'not allowed to view reminders for this plan',
                    'status' => 'error',
                    'data' => [],
                ], HttpCodes::HTTP_FORBIDDEN);
            }

            return response()->json([
                'code' => '0C3',
                'message' => 'daily plan reminder preview',
                'status' => 'success',
                'data' => $reminderService->buildReminderPreview($dailyPlanId, [
                    'message' => $request->query('message'),
                ]),
            ], HttpCodes::HTTP_OK);
        } catch (Exception $e) {
            Log::error('GetDailyPlanReminderPreview: '.$e->getMessage());

            return response()->json([
                'code' => '0C3-E',
                'message' => 'failed to fetch reminder preview',
                'status' => 'error',
                'data' => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function coachCanAccessPlan(string $dailyPlanId): bool
    {
        $teamIds = CoachTeam::query()
            ->where('coach_id', Auth::id())
            ->pluck('team_id')
            ->all();

        return DailyPlan::query()
            ->where('id', $dailyPlanId)
            ->whereIn('team_id', $teamIds)
            ->exists();
    }
}
