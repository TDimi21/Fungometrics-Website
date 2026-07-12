<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use App\Models\DailyPlan;
use App\Models\DailyPlanAssignment;
use App\Models\PlayerTeam;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BenchmarkPracticePlanDailyPlannerAdapter
{
    private const BUCKET_MAP = [
        'roster_cleanup_block' => 'education',
        'exit_velocity_baseline_block' => 'hitting',
        'power_development_block' => 'hitting',
        'bullpen_baseline_block' => 'pitching',
        'fastball_command_block' => 'pitching',
        'throwing_capacity_block' => 'throwing',
        'strength_baseline_block' => 'strength_primary',
        'athletic_testing_block' => 'speed_agility',
        'mobility_screen_block' => 'recovery',
        'review_debrief_block' => 'education',
    ];

    private const BUCKET_TITLES = [
        'daily_readiness' => 'Daily Readiness',
        'movement_prep' => 'Movement Preparation',
        'arm_care' => 'Arm Care',
        'throwing' => 'Throwing',
        'pitching' => 'Pitching Development',
        'hitting' => 'Hitting',
        'speed_agility' => 'Speed and Agility',
        'strength_primary' => 'Primary Strength',
        'strength_secondary' => 'Secondary Strength',
        'strength_accessory' => 'Accessory Strength',
        'conditioning' => 'Conditioning',
        'recovery' => 'Recovery',
        'education' => 'Education',
        'coach_notes' => 'Coach Notes',
        'player_reflection' => 'Player Reflection',
    ];

    private const BUCKET_KINDS = [
        'daily_readiness' => 'survey',
        'coach_notes' => 'note',
        'player_reflection' => 'survey',
    ];

    public function __construct(
        private readonly CoachActionPracticePlanner $coachActionPracticePlanner,
    ) {
    }

    public function previewMapping(string $teamId, int $days = 365): array
    {
        $days = max(7, min(365, $days));
        $practicePlan = $this->coachActionPracticePlanner->buildPracticePlanFromCoachActions($teamId, $days);
        $mapped = $this->mapPracticePlanToDailyPlanPayload($teamId, $practicePlan);

        return [
            'generated_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'days' => $days,
            'existing_planner_tables_found' => $this->existingPlannerTables(),
            'phase_2z_tables_found' => $this->phase2ZTables(),
            'duplicate_planner_exists' => Schema::hasTable('coach_practice_plans'),
            'source_of_truth' => 'daily_plans',
            'recommendation_layer' => 'coach_action_practice_plan',
            'execution_layer' => 'daily_plans',
            'answers' => $this->reconciliationAnswers(),
            'suggested_practice_plan' => [
                'plan_title' => $practicePlan['plan_title'] ?? null,
                'priority_focus' => $practicePlan['priority_focus'] ?? null,
                'estimated_total_minutes' => $practicePlan['estimated_total_minutes'] ?? 0,
                'block_count' => count($practicePlan['practice_blocks'] ?? []),
                'overflow_count' => count($practicePlan['next_session_blocks'] ?? []),
            ],
            'daily_plan_preview' => $mapped,
            'mapping' => $this->mappingRows($practicePlan['practice_blocks'] ?? []),
            'warnings' => $this->warnings($practicePlan, $mapped),
            'skipped_fields' => [
                'practicePlanId is not used because no separate 2Z persisted plan table exists.',
                'No DailyPlanProgress rows are created during save; progress remains player-completion driven.',
            ],
        ];
    }

    public function saveToExistingDailyPlanner(string $teamId, string $practicePlanId = null, array $options = []): array
    {
        $days = max(7, min(365, (int) ($options['days'] ?? 365)));
        $practicePlan = $this->coachActionPracticePlanner->buildPracticePlanFromCoachActions($teamId, $days, [
            'max_minutes' => $options['max_minutes'] ?? 90,
        ]);
        $payload = $this->mapPracticePlanToDailyPlanPayload($teamId, $practicePlan, $options);
        $requestedAssignments = array_values(array_unique(array_filter(array_map(
            'strval',
            $options['assigned_player_ids'] ?? $payload['assigned_player_ids'] ?? []
        ))));

        if (($options['assign_all'] ?? false) === true) {
            $requestedAssignments = $this->teamPlayerIds($teamId);
        }

        $plan = DB::transaction(function () use ($payload, $teamId, $requestedAssignments) {
            $plan = DailyPlan::updateOrCreate(
                ['id' => $payload['id']],
                [
                    'team_id' => $teamId,
                    'created_by' => $payload['created_by'],
                    'name' => $payload['name'],
                    'date' => $payload['date'],
                    'phase' => $payload['phase'],
                    'primary_goal' => $payload['primary_goal'],
                    'estimated_minutes' => $payload['estimated_minutes'],
                    'workload_level' => $payload['workload_level'],
                    'status' => $payload['status'],
                    'buckets' => $payload['buckets'],
                    'published_at' => $payload['published_at'],
                ]
            );

            $this->syncAssignments((string) $plan->id, $teamId, $requestedAssignments);

            return $plan;
        });

        $plan->load('assignments');

        return [
            'ok' => true,
            'saved_daily_plan_id' => (string) $plan->id,
            'status' => $plan->status,
            'published' => $plan->status === 'published',
            'assigned_player_ids' => $plan->assigned_player_ids,
            'assigned_player_count' => count($plan->assigned_player_ids),
            'source' => 'coach_action_practice_plan',
            'source_practice_plan_id' => $practicePlanId,
            'daily_plan' => $plan->toArray(),
            'warnings' => $this->warnings($practicePlan, $payload),
        ];
    }

    public function assignExistingDailyPlan(string $dailyPlanId, array $playerIds = [], array $options = []): array
    {
        $plan = DailyPlan::query()->find($dailyPlanId);
        if (! $plan) {
            return [
                'ok' => false,
                'message' => 'Daily plan not found.',
                'daily_plan_id' => $dailyPlanId,
            ];
        }

        $teamId = (string) $plan->team_id;
        $assignments = ($options['assign_all'] ?? false) === true
            ? $this->teamPlayerIds($teamId)
            : array_values(array_unique(array_filter(array_map('strval', $playerIds))));

        DB::transaction(function () use ($plan, $teamId, $assignments, $options): void {
            $this->syncAssignments((string) $plan->id, $teamId, $assignments);

            if (($options['publish'] ?? false) === true) {
                $plan->status = 'published';
                $plan->published_at = $plan->published_at ?? now();
                $plan->save();
            }
        });

        $plan->refresh()->load('assignments');

        return [
            'ok' => true,
            'daily_plan_id' => (string) $plan->id,
            'status' => $plan->status,
            'published' => $plan->status === 'published',
            'assigned_player_ids' => $plan->assigned_player_ids,
            'assigned_player_count' => count($plan->assigned_player_ids),
        ];
    }

    private function mapPracticePlanToDailyPlanPayload(string $teamId, array $practicePlan, array $options = []): array
    {
        $blocks = array_values(array_filter($practicePlan['practice_blocks'] ?? [], 'is_array'));
        $buckets = $this->blocksToBuckets($blocks);
        $status = (string) ($options['status'] ?? 'draft');
        $status = in_array($status, ['draft', 'published', 'template'], true) ? $status : 'draft';

        return [
            'id' => (string) ($options['daily_plan_id'] ?? 'dp_benchmark_'.Str::uuid()),
            'team_id' => $teamId,
            'created_by' => (string) ($options['created_by'] ?? Auth::id() ?? ''),
            'name' => (string) ($options['name'] ?? $practicePlan['plan_title'] ?? 'FMTRX Benchmark Practice Plan'),
            'date' => (string) ($options['date'] ?? now()->toDateString()),
            'phase' => (string) ($options['phase'] ?? 'Assessment'),
            'primary_goal' => (string) ($options['primary_goal'] ?? $practicePlan['priority_focus'] ?? 'Benchmark-generated practice'),
            'estimated_minutes' => (int) ($practicePlan['estimated_total_minutes'] ?? array_sum(array_map(fn (array $block) => (int) ($block['duration_minutes'] ?? 0), $blocks))),
            'workload_level' => (string) ($options['workload_level'] ?? $this->workloadLevel($blocks)),
            'status' => $status,
            'buckets' => $buckets,
            'assigned_player_ids' => $this->playersFromBlocks($blocks),
            'published_at' => $status === 'published' ? now() : null,
            'source' => 'coach_action_practice_plan',
        ];
    }

    private function blocksToBuckets(array $blocks): array
    {
        $grouped = [];

        foreach ($blocks as $block) {
            $bucketType = $this->bucketTypeForBlock($block);
            $grouped[$bucketType] ??= [
                'type' => $bucketType,
                'title' => self::BUCKET_TITLES[$bucketType] ?? $this->human($bucketType),
                'kind' => self::BUCKET_KINDS[$bucketType] ?? 'content',
                'items' => [],
                'note' => '',
                'source' => 'coach_action_practice_plan',
            ];

            if (($grouped[$bucketType]['kind'] ?? 'content') === 'note') {
                $grouped[$bucketType]['note'] = trim(($grouped[$bucketType]['note'] ?? '')."\n".$this->blockNote($block));
            } else {
                $grouped[$bucketType]['items'][] = $this->blockToDailyPlanItem($block, $bucketType);
            }
        }

        if (! isset($grouped['coach_notes'])) {
            $grouped['coach_notes'] = [
                'type' => 'coach_notes',
                'title' => self::BUCKET_TITLES['coach_notes'],
                'kind' => 'note',
                'items' => [],
                'note' => 'Generated from FMTRX Benchmark Intelligence. Review before publishing to players.',
                'source' => 'coach_action_practice_plan',
            ];
        }

        $order = array_flip([
            'daily_readiness',
            'movement_prep',
            'arm_care',
            'throwing',
            'pitching',
            'hitting',
            'speed_agility',
            'strength_primary',
            'strength_secondary',
            'strength_accessory',
            'conditioning',
            'recovery',
            'education',
            'coach_notes',
            'player_reflection',
        ]);

        uasort($grouped, fn (array $a, array $b) => ($order[$a['type']] ?? 99) <=> ($order[$b['type']] ?? 99));

        return array_values($grouped);
    }

    private function blockToDailyPlanItem(array $block, string $bucketType): array
    {
        $duration = max(1, (int) ($block['duration_minutes'] ?? 0));
        $throwing = in_array($bucketType, ['throwing', 'pitching'], true);
        $strength = str_starts_with($bucketType, 'strength_');

        return [
            'id' => 'item_benchmark_'.Str::slug((string) ($block['temporary_key'] ?? $block['title'] ?? Str::uuid()), '_'),
            'drillId' => null,
            'name' => (string) ($block['title'] ?? 'Benchmark Practice Block'),
            'instructions' => (string) ($block['description'] ?? ''),
            'coachCue' => (string) ($block['why'] ?? ''),
            'equipment' => '',
            'videoUrl' => '',
            'defaultPrescriptionType' => $strength ? 'custom' : null,
            'oneRMField' => null,
            'setList' => null,
            'sets' => 1,
            'reps' => null,
            'durationSec' => $duration * 60,
            'distance' => null,
            'throws' => $throwing ? $this->defaultThrowCount($block) : null,
            'weight' => null,
            'intensity' => $this->intensityForPriority((string) ($block['priority'] ?? 'medium')),
            'intent' => $throwing ? $this->intentForPriority((string) ($block['priority'] ?? 'medium')) : null,
            'rest' => null,
            'required' => true,
            'workloadType' => $throwing ? 'throwing' : ($strength ? 'strength' : 'time'),
            'bucket' => $bucketType,
            'subcategory' => 'Benchmark Intelligence',
            'bodyRegion' => '',
            'movementPattern' => '',
            'categoryGroup' => 'FMTRX Benchmark',
            'physicalQuality' => '',
            'physicalQualities' => [],
            'baseballCorrelation' => (string) ($block['category'] ?? ''),
            'baseballCorrelations' => array_values(array_filter([$block['category'] ?? null])),
            'relatedMetrics' => array_values($block['metrics_to_collect'] ?? []),
            'benchmark_task_type' => $this->taskTypeForBlock($block),
            'benchmark_task_temporary_key' => $block['temporary_key'] ?? null,
            'tags' => ['benchmark-generated', (string) ($block['source'] ?? 'coach_action')],
            'note' => $this->blockNote($block),
            'source' => 'coach_action_practice_plan',
        ];
    }

    private function taskTypeForBlock(array $block): ?string
    {
        return match ((string) ($block['temporary_key'] ?? '')) {
            'roster_cleanup_block' => 'roster_cleanup',
            'exit_velocity_baseline_block',
            'power_development_block' => 'exit_velocity_baseline',
            'bullpen_baseline_block',
            'fastball_command_block' => 'bullpen_baseline',
            'throwing_capacity_block' => 'long_toss_weighted_ball',
            'strength_baseline_block' => 'strength_baseline',
            'athletic_testing_block' => 'athletic_testing',
            'mobility_screen_block' => 'mobility_screen',
            default => null,
        };
    }

    private function bucketTypeForBlock(array $block): string
    {
        $key = (string) ($block['temporary_key'] ?? '');
        if (isset(self::BUCKET_MAP[$key])) {
            return self::BUCKET_MAP[$key];
        }

        $category = strtolower((string) ($block['category'] ?? ''));

        return match ($category) {
            'hitting' => 'hitting',
            'pitching' => 'pitching',
            'throwing' => 'throwing',
            'strength' => 'strength_primary',
            'athletic' => 'speed_agility',
            'mobility', 'recovery' => 'recovery',
            'roster', 'data_collection' => 'education',
            default => 'education',
        };
    }

    private function syncAssignments(string $planId, string $teamId, array $requestedPlayerIds): void
    {
        $validIds = PlayerTeam::query()
            ->where('team_id', $teamId)
            ->whereIn('user_id', array_values(array_unique($requestedPlayerIds)))
            ->pluck('user_id')
            ->all();

        DailyPlanAssignment::query()
            ->where('plan_id', $planId)
            ->whereNotIn('user_id', $validIds)
            ->delete();

        $already = DailyPlanAssignment::query()
            ->where('plan_id', $planId)
            ->pluck('user_id')
            ->all();

        foreach (array_diff($validIds, $already) as $userId) {
            DailyPlanAssignment::create([
                'plan_id' => $planId,
                'user_id' => $userId,
            ]);
        }
    }

    private function playersFromBlocks(array $blocks): array
    {
        return collect($blocks)
            ->flatMap(fn (array $block) => $block['players'] ?? [])
            ->map(fn ($player) => is_array($player) ? ($player['player_id'] ?? null) : null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function teamPlayerIds(string $teamId): array
    {
        return PlayerTeam::query()
            ->where('team_id', $teamId)
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->pluck('user_id')
            ->all();
    }

    private function mappingRows(array $blocks): array
    {
        return collect($blocks)
            ->map(fn (array $block) => [
                'block' => $block['title'] ?? 'Practice Block',
                'temporary_key' => $block['temporary_key'] ?? null,
                'category' => $block['category'] ?? null,
                'daily_plan_bucket' => $this->bucketTypeForBlock($block),
                'bucket_title' => self::BUCKET_TITLES[$this->bucketTypeForBlock($block)] ?? $this->human($this->bucketTypeForBlock($block)),
                'metrics' => $block['metrics_to_collect'] ?? [],
                'player_count' => count($block['players'] ?? []),
            ])
            ->values()
            ->all();
    }

    private function existingPlannerTables(): array
    {
        return [
            'practice_plans' => Schema::hasTable('practice_plans'),
            'daily_plans' => Schema::hasTable('daily_plans'),
            'daily_plan_assignments' => Schema::hasTable('daily_plan_assignments'),
            'daily_plan_progress' => Schema::hasTable('daily_plan_progress'),
            'planner_custom_drills' => Schema::hasTable('planner_custom_drills'),
        ];
    }

    private function phase2ZTables(): array
    {
        return [
            'coach_practice_plans' => Schema::hasTable('coach_practice_plans'),
            'coach_practice_plan_assignments' => Schema::hasTable('coach_practice_plan_assignments'),
        ];
    }

    private function reconciliationAnswers(): array
    {
        return [
            'does_2z_reuse_existing_daily_plan_system' => 'Not directly. 2Z produces the coach recommendation draft, and this adapter maps it into the existing Daily Plan / Workout system.',
            'did_2z_create_duplicate_coach_practice_plans_table' => Schema::hasTable('coach_practice_plans'),
            'where_saved_2z_plan_appears_for_player' => 'After adapter save and publish, player/daily-plans returns it in the existing My Workouts screen.',
            'can_existing_player_daily_plan_screen_see_2z_plans' => 'Yes, after the adapter saves the mapped plan into daily_plans and assigns players through daily_plan_assignments.',
            'can_completion_flow_back' => 'Yes. Player completion remains daily_plan_progress via the existing player/daily-plans/{id}/progress endpoint.',
            'source_of_truth' => 'Existing Daily Plan / Workout is execution source of truth. Coach Action Practice Plan remains recommendation/draft generator.',
        ];
    }

    private function warnings(array $practicePlan, array $payload): array
    {
        $warnings = [];

        if (empty($practicePlan['practice_blocks'])) {
            $warnings[] = 'Suggested practice plan has no blocks to map.';
        }

        if (empty($payload['assigned_player_ids'])) {
            $warnings[] = 'No player assignments were found from the suggested blocks; use assign_all or pass assigned_player_ids before publishing.';
        }

        if (($payload['status'] ?? 'draft') !== 'published') {
            $warnings[] = 'Daily plan is a draft; players only see published daily plans.';
        }

        return $warnings;
    }

    private function workloadLevel(array $blocks): string
    {
        $minutes = array_sum(array_map(fn (array $block) => (int) ($block['duration_minutes'] ?? 0), $blocks));
        $highCount = collect($blocks)->filter(fn (array $block) => in_array($block['priority'] ?? '', ['high', 'critical'], true))->count();

        if ($minutes >= 90 || $highCount >= 4) {
            return 'High';
        }

        if ($minutes >= 60 || $highCount >= 2) {
            return 'Moderate';
        }

        return 'Low';
    }

    private function intensityForPriority(string $priority): string
    {
        return match (strtolower($priority)) {
            'critical', 'high' => 'High',
            'medium' => 'Moderate',
            'low' => 'Low',
            default => 'Moderate',
        };
    }

    private function intentForPriority(string $priority): int
    {
        return match (strtolower($priority)) {
            'critical', 'high' => 80,
            'medium' => 70,
            'low' => 50,
            default => 70,
        };
    }

    private function defaultThrowCount(array $block): int
    {
        $minutes = (int) ($block['duration_minutes'] ?? 15);

        return max(8, min(30, $minutes));
    }

    private function blockNote(array $block): string
    {
        $metrics = implode(', ', array_values($block['metrics_to_collect'] ?? []));
        $instructions = implode(' ', array_values($block['instructions'] ?? []));

        return trim(sprintf(
            '%s%s%s',
            (string) ($block['why'] ?? ''),
            $metrics !== '' ? ' Metrics: '.$metrics.'.' : '',
            $instructions !== '' ? ' Instructions: '.$instructions : '',
        ));
    }

    private function human(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }
}
