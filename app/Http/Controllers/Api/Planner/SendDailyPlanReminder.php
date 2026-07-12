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
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class SendDailyPlanReminder extends Controller
{
    public function __invoke(string $dailyPlanId, Request $request, DailyPlanReminderService $reminderService): JsonResponse
    {
        try {
            if (! $this->coachCanAccessPlan($dailyPlanId)) {
                return response()->json([
                    'code' => '0C4-F',
                    'message' => 'not allowed to send reminders for this plan',
                    'status' => 'error',
                    'data' => [],
                ], HttpCodes::HTTP_FORBIDDEN);
            }

            $options = [
                'message' => $request->input('message'),
            ];
            $playerIds = $this->playerIds($request->input('player_ids'));
            $payload = $playerIds === []
                ? $reminderService->sendReminderToUnacknowledged($dailyPlanId, (string) Auth::id(), $options)
                : $reminderService->sendReminderToPlayers($dailyPlanId, $playerIds, (string) Auth::id(), $options);

            return response()->json([
                'code' => '0C4',
                'message' => 'daily plan reminder result',
                'status' => 'success',
                'data' => $payload,
            ], HttpCodes::HTTP_OK);
        } catch (Exception $e) {
            Log::error('SendDailyPlanReminder: '.$e->getMessage());

            return response()->json([
                'code' => '0C4-E',
                'message' => 'failed to prepare reminder',
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

    private function playerIds(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        return array_values(array_unique(array_filter(array_map(
            fn ($item): string => trim((string) $item),
            Arr::wrap($value)
        ))));
    }
}
