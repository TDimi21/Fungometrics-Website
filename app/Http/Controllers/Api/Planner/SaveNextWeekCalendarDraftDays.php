<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Services\Intelligence\BenchmarkPracticePlanDailyPlannerAdapter;
use App\Services\Planner\NextWeekPlanGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class SaveNextWeekCalendarDraftDays extends Controller
{
    public function __invoke(
        string $teamId,
        Request $request,
        NextWeekPlanGeneratorService $generator,
        BenchmarkPracticePlanDailyPlannerAdapter $adapter,
    ): JsonResponse {
        if (! $this->canAccessTeam($request, $teamId)) {
            return response()->json([
                'code' => 'NWCD-SF',
                'message' => 'not allowed to save next week calendar draft for this team',
                'status' => 'error',
                'data' => [],
            ], HttpCodes::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'days' => ['required', 'array', 'min:1'],
            'days.*.day_index' => ['required', 'integer', 'min:1', 'max:7'],
            'days.*.scheduled_for' => ['nullable', 'date'],
            'days.*.status' => ['nullable', 'string'],
            'days.*.assign_player_ids' => ['nullable', 'array'],
            'days.*.assign_player_ids.*' => ['string'],
            'days.*.assigned_player_ids' => ['nullable', 'array'],
            'days.*.assigned_player_ids.*' => ['string'],
            'days.*.overwrite_existing' => ['nullable', 'boolean'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'next_week_start_date' => ['nullable', 'date'],
            'days_to_review' => ['nullable', 'integer', 'min:1', 'max:365'],
            'plan_days' => ['nullable', 'integer', 'min:1', 'max:7'],
            'max_minutes_per_day' => ['nullable', 'integer', 'min:30', 'max:180'],
        ]);

        $calendarDraft = $generator->buildCalendarDraft($teamId, [
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'next_week_start_date' => $validated['next_week_start_date'] ?? null,
            'days' => $validated['days_to_review'] ?? 7,
            'plan_days' => $validated['plan_days'] ?? 5,
            'max_minutes_per_day' => $validated['max_minutes_per_day'] ?? 90,
        ]);
        $calendarDays = collect(Arr::wrap($calendarDraft['calendar_days'] ?? []))->keyBy('day_index');
        $selectedDays = [];
        $missing = [];

        foreach (Arr::wrap($validated['days'] ?? []) as $selection) {
            if (! is_array($selection)) {
                continue;
            }

            $dayIndex = (int) ($selection['day_index'] ?? 0);
            $day = $calendarDays->get($dayIndex);
            if (! is_array($day)) {
                $missing[] = [
                    'day_index' => $dayIndex,
                    'reason' => 'No generated calendar day exists for this index.',
                ];
                continue;
            }

            $selectedDays[] = [
                ...$day,
                'scheduled_for' => $selection['scheduled_for'] ?? $day['scheduled_for'] ?? null,
                'assigned_player_ids' => $selection['assigned_player_ids'] ?? $selection['assign_player_ids'] ?? [],
                'overwrite_existing' => (bool) ($selection['overwrite_existing'] ?? false),
            ];
        }

        $save = $adapter->saveGeneratedDaysToDailyPlanner($teamId, $selectedDays);
        $skippedDays = [
            ...Arr::wrap($save['skipped_days'] ?? []),
            ...$missing,
        ];

        return response()->json([
            'code' => 'NWCD-S',
            'message' => 'selected next week calendar days saved as Daily Planner drafts',
            'status' => 'success',
            'data' => [
                ...$save,
                'skipped_count' => count($skippedDays),
                'skipped_days' => $skippedDays,
                'calendar_draft' => $calendarDraft,
            ],
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
}
