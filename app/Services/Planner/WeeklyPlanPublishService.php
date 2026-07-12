<?php

declare(strict_types=1);

namespace App\Services\Planner;

use App\Models\DailyPlan;
use App\Models\DailyPlanAssignment;
use App\Models\PlayerTeam;
use App\Models\Profile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class WeeklyPlanPublishService
{
    public function listWeeklyDrafts(string $teamId, array $options = []): array
    {
        $plans = DailyPlan::query()
            ->where('team_id', $teamId)
            ->whereIn('status', ['draft', 'published'])
            ->with(['assignments', 'progress'])
            ->orderBy('date')
            ->orderBy('created_at')
            ->get()
            ->filter(fn (DailyPlan $plan): bool => $this->isWeeklyGeneratedPlan($plan))
            ->values();

        return [
            'generated_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'draft_count' => $plans->where('status', 'draft')->count(),
            'published_count' => $plans->where('status', 'published')->count(),
            'plan_count' => $plans->count(),
            'plans' => $plans->map(fn (DailyPlan $plan): array => $this->planPayload($plan))->values()->all(),
            'warnings' => [],
            'evidence' => [
                'source' => 'daily_plans',
                'weekly_generated_filter' => 'source_or_tags',
                'player_visibility_contract' => 'status_published_plus_daily_plan_assignments',
            ],
        ];
    }

    public function publishDraftDay(string $dailyPlanId, array $options = []): array
    {
        $plan = DailyPlan::query()->with(['assignments', 'progress'])->find($dailyPlanId);
        if (! $plan) {
            return $this->emptyResult(null, 'failed', [
                [
                    'daily_plan_id' => $dailyPlanId,
                    'reason' => 'Daily Plan was not found.',
                ],
            ]);
        }

        $warnings = [];
        $statusBefore = (string) ($plan->status ?? '');

        if (! $this->isWeeklyGeneratedPlan($plan)) {
            return $this->emptyResult((string) $plan->team_id, 'skipped', [
                [
                    'daily_plan_id' => (string) $plan->id,
                    'title' => (string) ($plan->name ?? 'Daily Plan'),
                    'reason' => 'This is not a weekly generated draft.',
                ],
            ]);
        }

        if (in_array($statusBefore, ['dismissed', 'completed'], true)) {
            return $this->emptyResult((string) $plan->team_id, 'skipped', [
                [
                    'daily_plan_id' => (string) $plan->id,
                    'title' => (string) ($plan->name ?? 'Daily Plan'),
                    'reason' => 'Dismissed or completed plans cannot be published through this flow.',
                ],
            ]);
        }

        if ($statusBefore === 'published' && ! (bool) ($options['republish'] ?? false)) {
            return [
                'team_id' => (string) $plan->team_id,
                'status' => 'skipped',
                'published_count' => 0,
                'assigned_count' => 0,
                'skipped_count' => 1,
                'published_plans' => [$this->publishedPlanPayload($plan, $statusBefore, ['Plan is already published.'])],
                'skipped_plans' => [[
                    'daily_plan_id' => (string) $plan->id,
                    'title' => (string) ($plan->name ?? 'Daily Plan'),
                    'reason' => 'Plan is already published.',
                ]],
                'warnings' => ['Plan is already published.'],
                'evidence' => $this->evidence(false),
            ];
        }

        if ($statusBefore !== 'draft' && ! ($statusBefore === 'published' && (bool) ($options['republish'] ?? false))) {
            return $this->emptyResult((string) $plan->team_id, 'skipped', [
                [
                    'daily_plan_id' => (string) $plan->id,
                    'title' => (string) ($plan->name ?? 'Daily Plan'),
                    'reason' => 'Only draft weekly plans can be published through this flow.',
                ],
            ]);
        }

        $publishedDuplicate = $this->publishedPlanForSameDate($plan);
        if ($publishedDuplicate && ! (bool) ($options['republish'] ?? false)) {
            return $this->emptyResult((string) $plan->team_id, 'skipped', [
                [
                    'daily_plan_id' => (string) $plan->id,
                    'title' => (string) ($plan->name ?? 'Daily Plan'),
                    'reason' => 'A published plan already exists for this date.',
                    'existing_daily_plan_id' => (string) $publishedDuplicate->id,
                ],
            ], ['A published plan already exists for this date.']);
        }

        DB::transaction(function () use ($plan, $options): void {
            $plan->status = 'published';
            $plan->published_at = $plan->published_at ?? now();
            $plan->save();
        });

        $plan->refresh()->load(['assignments', 'progress']);

        return [
            'team_id' => (string) $plan->team_id,
            'status' => 'completed',
            'published_count' => 1,
            'assigned_count' => 0,
            'skipped_count' => 0,
            'published_plans' => [$this->publishedPlanPayload($plan, $statusBefore, $warnings)],
            'skipped_plans' => [],
            'warnings' => $warnings,
            'evidence' => $this->evidence(true, [
                'published_by_user_id' => $options['published_by_user_id'] ?? null,
            ]),
        ];
    }

    public function publishWeeklyDrafts(string $teamId, array $dailyPlanIds, array $options = []): array
    {
        if (empty($dailyPlanIds)) {
            $dailyPlanIds = collect($this->listWeeklyDrafts($teamId)['plans'] ?? [])
                ->filter(fn (array $plan): bool => ($plan['status'] ?? null) === 'draft')
                ->pluck('daily_plan_id')
                ->filter()
                ->values()
                ->all();
        }

        $published = [];
        $skipped = [];
        $warnings = [];
        $assignedCount = 0;

        foreach (array_values(array_unique(array_filter(array_map('strval', $dailyPlanIds)))) as $dailyPlanId) {
            $plan = DailyPlan::query()->find($dailyPlanId);
            if (! $plan || (string) $plan->team_id !== $teamId) {
                $skipped[] = [
                    'daily_plan_id' => $dailyPlanId,
                    'reason' => 'Daily Plan was not found for this team.',
                ];
                continue;
            }

            $result = (bool) ($options['assign_all'] ?? false) || ! empty($options['player_ids'] ?? [])
                ? $this->publishAndAssign($dailyPlanId, Arr::wrap($options['player_ids'] ?? []), $options)
                : $this->publishDraftDay($dailyPlanId, $options);

            $published = [
                ...$published,
                ...Arr::wrap($result['published_plans'] ?? []),
            ];
            $skipped = [
                ...$skipped,
                ...Arr::wrap($result['skipped_plans'] ?? []),
            ];
            $warnings = [
                ...$warnings,
                ...Arr::wrap($result['warnings'] ?? []),
            ];
            $assignedCount += (int) ($result['assigned_count'] ?? 0);
        }

        return [
            'team_id' => $teamId,
            'status' => $this->aggregateStatus(count($published), count($skipped)),
            'published_count' => count($published),
            'assigned_count' => $assignedCount,
            'skipped_count' => count($skipped),
            'published_plans' => $published,
            'skipped_plans' => $skipped,
            'warnings' => array_values(array_unique(array_filter($warnings))),
            'evidence' => $this->evidence(count($published) > 0, [
                'requested_daily_plan_count' => count($dailyPlanIds),
                'assign_all' => (bool) ($options['assign_all'] ?? false),
            ]),
        ];
    }

    public function assignPublishedPlan(string $dailyPlanId, array $playerIds = [], array $options = []): array
    {
        $plan = DailyPlan::query()->with(['assignments', 'progress'])->find($dailyPlanId);
        if (! $plan) {
            return $this->emptyResult(null, 'failed', [[
                'daily_plan_id' => $dailyPlanId,
                'reason' => 'Daily Plan was not found.',
            ]]);
        }

        if ((string) $plan->status !== 'published') {
            return $this->emptyResult((string) $plan->team_id, 'skipped', [[
                'daily_plan_id' => (string) $plan->id,
                'title' => (string) ($plan->name ?? 'Daily Plan'),
                'reason' => 'Only published plans can be assigned to players.',
            ]]);
        }

        $requested = (bool) ($options['assign_all'] ?? false)
            ? $this->teamPlayerIds((string) $plan->team_id)
            : array_values(array_unique(array_filter(array_map('strval', $playerIds))));
        $validIds = $this->validTeamPlayerIds((string) $plan->team_id, $requested);
        $existing = DailyPlanAssignment::query()
            ->where('plan_id', (string) $plan->id)
            ->pluck('user_id')
            ->map(fn ($id): string => (string) $id)
            ->all();
        $toCreate = array_values(array_diff($validIds, $existing));

        DB::transaction(function () use ($plan, $toCreate): void {
            foreach ($toCreate as $playerId) {
                DailyPlanAssignment::query()->firstOrCreate([
                    'plan_id' => (string) $plan->id,
                    'user_id' => $playerId,
                ]);
            }
        });

        $plan->refresh()->load(['assignments', 'progress']);
        $warnings = [];
        if (empty($validIds)) {
            $warnings[] = 'No valid team players were selected for assignment.';
        }
        if ($plan->progress->isNotEmpty()) {
            $warnings[] = 'Existing player progress was preserved.';
        }

        return [
            'team_id' => (string) $plan->team_id,
            'status' => empty($validIds) ? 'skipped' : 'completed',
            'published_count' => 0,
            'assigned_count' => count($toCreate),
            'skipped_count' => empty($validIds) ? 1 : 0,
            'published_plans' => [$this->publishedPlanPayload($plan, (string) $plan->status, $warnings)],
            'skipped_plans' => empty($validIds) ? [[
                'daily_plan_id' => (string) $plan->id,
                'title' => (string) ($plan->name ?? 'Daily Plan'),
                'reason' => 'No valid team players were selected for assignment.',
            ]] : [],
            'warnings' => $warnings,
            'evidence' => $this->evidence(count($toCreate) > 0, [
                'requested_player_count' => count($requested),
                'valid_player_count' => count($validIds),
                'new_assignment_count' => count($toCreate),
                'player_visibility_check' => 'GET /api/player/daily-plans returns published assigned plans.',
            ]),
        ];
    }

    public function publishAndAssign(string $dailyPlanId, array $playerIds = [], array $options = []): array
    {
        $publish = $this->publishDraftDay($dailyPlanId, $options);
        $plan = DailyPlan::query()->find($dailyPlanId);

        if (! $plan || (string) $plan->status !== 'published') {
            return $publish;
        }

        $assign = $this->assignPublishedPlan($dailyPlanId, $playerIds, $options);
        $publishedPlans = Arr::wrap($publish['published_plans'] ?? []);
        $assignPlans = Arr::wrap($assign['published_plans'] ?? []);

        return [
            'team_id' => (string) $plan->team_id,
            'status' => $assign['status'] === 'completed' || $publish['status'] === 'completed' ? 'completed' : $this->aggregateStatus(count($publishedPlans), (int) ($publish['skipped_count'] ?? 0) + (int) ($assign['skipped_count'] ?? 0)),
            'published_count' => (int) ($publish['published_count'] ?? 0),
            'assigned_count' => (int) ($assign['assigned_count'] ?? 0),
            'skipped_count' => (int) ($publish['skipped_count'] ?? 0) + (int) ($assign['skipped_count'] ?? 0),
            'published_plans' => ! empty($assignPlans) ? $assignPlans : $publishedPlans,
            'skipped_plans' => [
                ...Arr::wrap($publish['skipped_plans'] ?? []),
                ...Arr::wrap($assign['skipped_plans'] ?? []),
            ],
            'warnings' => array_values(array_unique(array_filter([
                ...Arr::wrap($publish['warnings'] ?? []),
                ...Arr::wrap($assign['warnings'] ?? []),
            ]))),
            'evidence' => $this->evidence(true, [
                'publish_status' => $publish['status'] ?? null,
                'assignment_status' => $assign['status'] ?? null,
                'player_visibility_check' => 'GET /api/player/daily-plans returns this after published plus assigned.',
            ]),
        ];
    }

    private function publishedPlanPayload(DailyPlan $plan, ?string $statusBefore = null, array $warnings = []): array
    {
        $plan->loadMissing(['assignments', 'progress']);

        return [
            'daily_plan_id' => (string) $plan->id,
            'title' => (string) ($plan->name ?? 'Daily Plan'),
            'scheduled_for' => optional($plan->date)->toDateString(),
            'status_before' => $statusBefore,
            'status_after' => (string) $plan->status,
            'assigned_player_count' => $plan->assignments->count(),
            'published_at' => optional($plan->published_at)->toIso8601String(),
            'players_assigned' => $this->assignedPlayers((string) $plan->id),
            'warnings' => array_values(array_unique(array_filter($warnings))),
        ];
    }

    private function planPayload(DailyPlan $plan): array
    {
        $plan->loadMissing(['assignments', 'progress']);
        $warnings = [];
        if ((string) $plan->status === 'published') {
            $warnings[] = 'This plan is already published.';
        }
        if ($plan->progress->isNotEmpty()) {
            $warnings[] = 'This plan has player progress. Progress will not be reset.';
        }

        return [
            'daily_plan_id' => (string) $plan->id,
            'title' => (string) ($plan->name ?? 'Daily Plan'),
            'scheduled_for' => optional($plan->date)->toDateString(),
            'primary_focus' => (string) ($plan->primary_goal ?? 'Weekly Plan'),
            'estimated_minutes' => (int) ($plan->estimated_minutes ?? 0),
            'workload_level' => (string) ($plan->workload_level ?? ''),
            'status' => (string) $plan->status,
            'published_at' => optional($plan->published_at)->toIso8601String(),
            'assigned_player_count' => $plan->assignments->count(),
            'players_assigned' => $this->assignedPlayers((string) $plan->id),
            'block_count' => $this->blockCount(Arr::wrap($plan->buckets ?? [])),
            'has_progress' => $plan->progress->isNotEmpty(),
            'source' => 'weekly_rollup_next_week_plan',
            'can_publish' => (string) $plan->status === 'draft',
            'can_assign' => (string) $plan->status === 'published',
            'daily_plan' => $plan->toArray(),
            'warnings' => $warnings,
        ];
    }

    private function isWeeklyGeneratedPlan(DailyPlan $plan): bool
    {
        if (str_starts_with((string) $plan->id, 'dp_weekly_')) {
            return true;
        }
        if (strtolower((string) $plan->phase) === 'weekly plan') {
            return true;
        }

        foreach (Arr::wrap($plan->buckets ?? []) as $bucket) {
            if (! is_array($bucket)) {
                continue;
            }
            $source = (string) ($bucket['source'] ?? '');
            if (in_array($source, ['weekly_rollup_next_week_plan', 'weekly_planner_rollup_generated_day'], true)) {
                return true;
            }
            if (array_intersect(['weekly-draft', 'fmtrx-generated', 'benchmark-plan'], Arr::wrap($bucket['tags'] ?? []))) {
                return true;
            }
            foreach (Arr::wrap($bucket['items'] ?? []) as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $itemSource = (string) ($item['source'] ?? '');
                if (in_array($itemSource, ['weekly_rollup_next_week_plan', 'weekly_planner_rollup_generated_day'], true)) {
                    return true;
                }
                if (array_intersect(['weekly-draft', 'fmtrx-generated', 'benchmark-plan'], Arr::wrap($item['tags'] ?? []))) {
                    return true;
                }
            }
        }

        return false;
    }

    private function publishedPlanForSameDate(DailyPlan $plan): ?DailyPlan
    {
        if (! $plan->date) {
            return null;
        }

        return DailyPlan::query()
            ->where('team_id', (string) $plan->team_id)
            ->whereDate('date', optional($plan->date)->toDateString())
            ->where('status', 'published')
            ->where('id', '!=', (string) $plan->id)
            ->first();
    }

    private function teamPlayerIds(string $teamId): array
    {
        return PlayerTeam::query()
            ->where('team_id', $teamId)
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->pluck('user_id')
            ->map(fn ($id): string => (string) $id)
            ->all();
    }

    private function validTeamPlayerIds(string $teamId, array $playerIds): array
    {
        if (empty($playerIds)) {
            return [];
        }

        return PlayerTeam::query()
            ->where('team_id', $teamId)
            ->whereIn('user_id', array_values(array_unique(array_filter($playerIds))))
            ->pluck('user_id')
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function assignedPlayers(string $dailyPlanId): array
    {
        $ids = DailyPlanAssignment::query()
            ->where('plan_id', $dailyPlanId)
            ->pluck('user_id')
            ->map(fn ($id): string => (string) $id)
            ->all();

        if (empty($ids)) {
            return [];
        }

        $profiles = Profile::query()
            ->whereIn('user_id', $ids)
            ->get()
            ->keyBy('user_id');

        return collect($ids)
            ->map(function (string $id) use ($profiles): array {
                $profile = $profiles->get($id);

                return [
                    'player_id' => $id,
                    'player_name' => trim((string) ($profile->first_name ?? '').' '.(string) ($profile->last_name ?? '')) ?: 'Player',
                ];
            })
            ->values()
            ->all();
    }

    private function blockCount(array $buckets): int
    {
        return collect($buckets)
            ->sum(fn ($bucket): int => is_array($bucket) ? count(Arr::wrap($bucket['items'] ?? [])) + (((string) ($bucket['note'] ?? '') !== '') ? 1 : 0) : 0);
    }

    private function emptyResult(?string $teamId, string $status, array $skippedPlans = [], array $warnings = []): array
    {
        return [
            'team_id' => $teamId,
            'status' => $status,
            'published_count' => 0,
            'assigned_count' => 0,
            'skipped_count' => count($skippedPlans),
            'published_plans' => [],
            'skipped_plans' => $skippedPlans,
            'warnings' => $warnings,
            'evidence' => $this->evidence(false),
        ];
    }

    private function aggregateStatus(int $publishedCount, int $skippedCount): string
    {
        if ($publishedCount > 0 && $skippedCount > 0) {
            return 'partial';
        }
        if ($publishedCount > 0) {
            return 'completed';
        }
        if ($skippedCount > 0) {
            return 'skipped';
        }

        return 'skipped';
    }

    private function evidence(bool $changed, array $extra = []): array
    {
        return [
            'source_of_truth' => 'daily_plans',
            'assignment_table' => 'daily_plan_assignments',
            'progress_table' => 'daily_plan_progress',
            'player_visibility_contract' => 'published daily plan plus assignment',
            'player_progress_reset' => false,
            'database_records_created_or_updated' => $changed,
            ...$extra,
        ];
    }
}
