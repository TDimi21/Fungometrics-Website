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

class SaveNextWeekPlanDraftDay extends Controller
{
    public function __invoke(
        string $teamId,
        Request $request,
        NextWeekPlanGeneratorService $generator,
        BenchmarkPracticePlanDailyPlannerAdapter $adapter,
    ): JsonResponse {
        if (! $this->canAccessTeam($request, $teamId)) {
            return response()->json([
                'code' => 'NWD-SF',
                'message' => 'not allowed to save next week plan draft for this team',
                'status' => 'error',
                'data' => [],
            ], HttpCodes::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'day_index' => ['required', 'integer', 'min:1', 'max:7'],
            'scheduled_for' => ['nullable', 'date'],
            'status' => ['nullable', 'string'],
            'assign_player_ids' => ['nullable', 'array'],
            'assign_player_ids.*' => ['string'],
            'assigned_player_ids' => ['nullable', 'array'],
            'assigned_player_ids.*' => ['string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'next_week_start_date' => ['nullable', 'date'],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'plan_days' => ['nullable', 'integer', 'min:1', 'max:7'],
            'max_minutes_per_day' => ['nullable', 'integer', 'min:30', 'max:180'],
        ]);

        $draft = $generator->generateForTeam($teamId, [
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'next_week_start_date' => $validated['next_week_start_date'] ?? null,
            'days' => $validated['days'] ?? 7,
            'plan_days' => $validated['plan_days'] ?? 5,
            'max_minutes_per_day' => $validated['max_minutes_per_day'] ?? 90,
        ]);
        $day = collect(Arr::wrap($draft['suggested_plan_days'] ?? []))
            ->firstWhere('day_index', (int) $validated['day_index']);

        if (! is_array($day)) {
            return response()->json([
                'code' => 'NWD-SNF',
                'message' => 'suggested day was not found in the generated draft',
                'status' => 'error',
                'data' => [
                    'day_index' => (int) $validated['day_index'],
                    'draft' => $draft,
                ],
            ], HttpCodes::HTTP_UNPROCESSABLE_ENTITY);
        }

        $save = $adapter->saveGeneratedDayToDailyPlanner($teamId, $day, [
            'scheduled_for' => $validated['scheduled_for'] ?? $day['scheduled_for'] ?? null,
            'assigned_player_ids' => $validated['assigned_player_ids'] ?? $validated['assign_player_ids'] ?? [],
            'status' => 'draft',
        ]);

        $warnings = Arr::wrap($save['warnings'] ?? []);
        if (($validated['status'] ?? 'draft') !== 'draft') {
            $warnings[] = 'Generated weekly days are saved as drafts only. Publish later from the Daily Planner.';
        }

        return response()->json([
            'code' => 'NWD-S',
            'message' => 'next week plan day saved as Daily Planner draft',
            'status' => 'success',
            'data' => [
                ...$save,
                'warnings' => array_values(array_unique(array_filter($warnings))),
                'draft' => $draft,
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
