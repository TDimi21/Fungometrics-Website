<?php

declare(strict_types=1);

namespace App\Services\Planner;

use App\Models\DailyPlan;
use App\Services\Intelligence\BenchmarkCollectionPlanner;
use App\Services\Intelligence\DecisionEngine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Throwable;

class NextWeekPlanGeneratorService
{
    public function __construct(
        private readonly WeeklyPlannerRollupService $weeklyPlannerRollupService,
        private readonly DecisionEngine $decisionEngine,
        private readonly BenchmarkCollectionPlanner $benchmarkCollectionPlanner,
    ) {
    }

    public function generateForTeam(string $teamId, array $options = []): array
    {
        try {
            $options = $this->normalizeOptions($options);
            $warnings = [];
            $weeklyRollup = $this->weeklyPlannerRollupService->buildTeamWeeklyRollup($teamId, [
                ...$options,
                'include_players' => true,
                'include_benchmark_intelligence' => true,
            ]);
            $decisionBrief = $this->safeDecisionBrief($teamId, (int) $options['days'], $warnings);
            $collectionPlan = $this->safeCollectionPlan($teamId, (int) $options['days'], $warnings);
            $currentIntelligence = [
                'decision_brief' => $decisionBrief,
                'collection_plan' => $collectionPlan,
            ];
            $priorities = $this->buildWeeklyPriorities($weeklyRollup, $currentIntelligence);
            $suggestedDays = $this->shouldBuildSuggestedDays($weeklyRollup, $priorities)
                ? $this->buildSuggestedPlanDays($teamId, $priorities, [
                    ...$options,
                    'weekly_rollup' => $weeklyRollup,
                ])
                : [];
            $playerAssignments = $this->playerAssignments($weeklyRollup, $collectionPlan, $suggestedDays);
            $targets = $this->benchmarkCollectionTargets($weeklyRollup, $collectionPlan);
            $coachNotes = $this->coachNotes($weeklyRollup, $decisionBrief, $collectionPlan, $suggestedDays);
            $status = $this->generationStatus($weeklyRollup, $priorities, $suggestedDays);

            return [
                'generated_at' => now()->toIso8601String(),
                'team_id' => $teamId,
                'source' => 'weekly_planner_rollup',
                'week_reviewed' => $this->weekReviewed($weeklyRollup),
                'next_week_start_date' => $options['next_week_start_date'],
                'generation_status' => $status,
                'weekly_summary' => [
                    'coach_summary' => $weeklyRollup['coach_summary'] ?? null,
                    'summary_status' => $weeklyRollup['summary_status'] ?? null,
                    'players_needing_follow_up_count' => count(Arr::wrap(Arr::get($weeklyRollup, 'player_completion_summary.players_needing_follow_up', []))),
                    'remaining_benchmark_gap_count' => count(Arr::wrap(Arr::get($weeklyRollup, 'benchmark_collection_summary.top_missing_metrics_remaining', []))),
                ],
                'priority_focuses' => $priorities,
                'suggested_plan_days' => $suggestedDays,
                'player_assignments' => $playerAssignments,
                'benchmark_collection_targets' => $targets,
                'coach_notes' => $coachNotes,
                'warnings' => array_values(array_unique(array_filter([
                    ...$warnings,
                    ...Arr::wrap($weeklyRollup['warnings'] ?? []),
                ]))),
                'evidence' => [
                    'weekly_rollup_status' => $weeklyRollup['summary_status'] ?? null,
                    'decision_brief_available' => $decisionBrief !== null,
                    'collection_plan_available' => $collectionPlan !== null,
                    'priority_count' => count($priorities),
                    'suggested_day_count' => count($suggestedDays),
                    'database_records_created' => false,
                    'persistence' => 'preview_only',
                ],
            ];
        } catch (Throwable $exception) {
            return [
                'generated_at' => now()->toIso8601String(),
                'team_id' => $teamId,
                'source' => 'weekly_planner_rollup',
                'week_reviewed' => [],
                'next_week_start_date' => null,
                'generation_status' => 'failed',
                'weekly_summary' => [],
                'priority_focuses' => [],
                'suggested_plan_days' => [],
                'player_assignments' => [],
                'benchmark_collection_targets' => [],
                'coach_notes' => [],
                'warnings' => [$exception->getMessage()],
                'evidence' => [
                    'exception' => class_basename($exception),
                    'database_records_created' => false,
                ],
            ];
        }
    }

    public function generateForPlayer(string $teamId, string $playerId, array $options = []): array
    {
        $draft = $this->generateForTeam($teamId, $options);
        $draft['player_id'] = $playerId;
        $draft['player_assignments'] = collect(Arr::wrap($draft['player_assignments'] ?? []))
            ->filter(fn (array $row): bool => (string) ($row['player_id'] ?? '') === $playerId)
            ->values()
            ->all();
        $draft['suggested_plan_days'] = collect(Arr::wrap($draft['suggested_plan_days'] ?? []))
            ->map(function (array $day) use ($playerId): array {
                $day['player_assignments'] = collect(Arr::wrap($day['player_assignments'] ?? []))
                    ->filter(fn (array $row): bool => (string) ($row['player_id'] ?? '') === $playerId)
                    ->values()
                    ->all();

                return $day;
            })
            ->values()
            ->all();

        return $draft;
    }

    public function buildCalendarDraft(string $teamId, array $options = []): array
    {
        $options = $this->normalizeOptions($options);
        $draft = $this->generateForTeam($teamId, $options);
        $calendarDays = $this->calendarDaysFromDraft($teamId, $draft, $options);
        $weekStart = ! empty($calendarDays)
            ? (string) ($calendarDays[0]['scheduled_for'] ?? $options['next_week_start_date'])
            : (string) $options['next_week_start_date'];
        $weekEnd = ! empty($calendarDays)
            ? (string) ($calendarDays[count($calendarDays) - 1]['scheduled_for'] ?? CarbonImmutable::parse($weekStart)->addDays(max(0, count($calendarDays) - 1))->toDateString())
            : CarbonImmutable::parse($weekStart)->addDays(max(0, ((int) $options['plan_days']) - 1))->toDateString();

        return [
            'generated_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'source' => 'weekly_rollup_next_week_plan',
            'week_start_date' => $weekStart,
            'week_end_date' => $weekEnd,
            'weekly_summary' => $draft['weekly_summary'] ?? [],
            'priority_focuses' => $draft['priority_focuses'] ?? [],
            'calendar_days' => $calendarDays,
            'weekly_workload_summary' => $this->weeklyWorkloadSummary($calendarDays, $draft),
            'benchmark_collection_targets' => $draft['benchmark_collection_targets'] ?? [],
            'coach_notes' => $draft['coach_notes'] ?? [],
            'warnings' => array_values(array_unique(array_filter([
                ...Arr::wrap($draft['warnings'] ?? []),
                ...$this->calendarWarnings($calendarDays, (int) $options['max_minutes_per_day']),
            ]))),
            'draft' => $draft,
            'evidence' => [
                'generation_status' => $draft['generation_status'] ?? null,
                'suggested_day_count' => count(Arr::wrap($draft['suggested_plan_days'] ?? [])),
                'calendar_day_count' => count($calendarDays),
                'database_records_created' => false,
                'persistence' => 'preview_only',
            ],
        ];
    }

    public function buildWeeklyPriorities(array $weeklyRollup, array $currentIntelligence = []): array
    {
        $priorities = [];
        $review = Arr::wrap($weeklyRollup['review_summary'] ?? []);
        $benchmark = Arr::wrap($weeklyRollup['benchmark_collection_summary'] ?? []);
        $trusted = Arr::wrap($weeklyRollup['trusted_data_summary'] ?? []);
        $playerSummary = Arr::wrap($weeklyRollup['player_completion_summary'] ?? []);
        $decision = Arr::wrap($currentIntelligence['decision_brief'] ?? []);
        $collection = Arr::wrap($currentIntelligence['collection_plan'] ?? []);
        $trustedMetrics = array_values(array_filter(Arr::wrap($trusted['metrics_improved'] ?? [])));

        if ((int) ($review['pending_review_count'] ?? 0) > 0) {
            $priorities[] = $this->priority(
                'Review Submitted Benchmark Values',
                ((int) ($review['pending_review_count'] ?? 0) >= 5) ? 'critical' : 'high',
                'data_collection',
                (int) ($review['pending_review_count'] ?? 0).' benchmark submission(s) are waiting for coach review before they can become trusted data.',
                'weekly_rollup',
                Arr::wrap($review['tasks_pending_review'] ?? []),
                [],
                12,
            );
        }

        if ((int) ($review['correction_requested_count'] ?? 0) > 0 || (int) ($benchmark['metric_values_correction_requested'] ?? 0) > 0) {
            $priorities[] = $this->priority(
                'Retest Corrected Benchmark Values',
                'high',
                'data_collection',
                'Some submitted benchmark values need correction or retesting before they can be trusted.',
                'weekly_rollup',
                [],
                [],
                15,
            );
        }

        foreach (Arr::wrap($collection['collection_sessions'] ?? []) as $session) {
            if (! is_array($session)) {
                continue;
            }

            $title = (string) ($session['title'] ?? 'Benchmark Collection');
            $metrics = array_values(array_diff(Arr::wrap($session['metric_keys'] ?? $session['metrics'] ?? []), $trustedMetrics));
            if (empty($metrics) && $this->categoryForCollection((string) ($session['collection_type'] ?? '')) !== 'roster') {
                continue;
            }

            $priorities[] = $this->priority(
                $title,
                (string) ($session['priority'] ?? 'medium'),
                $this->categoryForCollection((string) ($session['collection_type'] ?? '')),
                (string) ($session['why'] ?? 'This benchmark gap is still limiting player intelligence confidence.'),
                'collection_plan',
                Arr::wrap($session['players'] ?? []),
                $metrics,
                (int) ($session['duration_minutes'] ?? 20),
            );
        }

        foreach (Arr::wrap($benchmark['top_missing_metrics_remaining'] ?? []) as $metric) {
            if (! is_array($metric)) {
                continue;
            }
            $metricKey = (string) ($metric['metric_key'] ?? '');
            if ($metricKey !== '' && in_array($metricKey, $trustedMetrics, true)) {
                continue;
            }

            $priorities[] = $this->priority(
                'Collect '.(string) ($metric['display_name'] ?? $metricKey ?: 'Benchmark Baseline'),
                'medium',
                $this->categoryForMetric($metricKey, (string) ($metric['category'] ?? 'data_collection')),
                ((string) ($metric['display_name'] ?? 'Benchmark data')).' remains one of the largest missing baseline gaps.',
                'weekly_rollup',
                Arr::wrap($metric['players'] ?? []),
                array_values(array_filter([$metricKey])),
                18,
            );
        }

        $primary = Arr::wrap($decision['primary_focus'] ?? []);
        $focusTitle = (string) ($primary['title'] ?? $primary['focus'] ?? '');
        if ($focusTitle !== '' && strtolower($focusTitle) !== 'data collection') {
            $priorities[] = $this->priority(
                $focusTitle,
                (string) ($primary['priority'] ?? 'medium'),
                $this->categoryForFocus($focusTitle),
                (string) ($primary['why'] ?? 'Current FMTRX decision brief lists this as the team performance focus.'),
                'decision_brief',
                Arr::wrap($primary['players'] ?? []),
                Arr::wrap($primary['metrics'] ?? []),
                25,
            );
        }

        if (! empty($playerSummary['players_needing_follow_up'])) {
            $priorities[] = $this->priority(
                'Player Follow-Up',
                'medium',
                'recovery',
                count(Arr::wrap($playerSummary['players_needing_follow_up'])).' player(s) missed or partially completed assigned work this week.',
                'player_follow_up',
                Arr::wrap($playerSummary['players_needing_follow_up']),
                [],
                10,
            );
        }

        $completion = (float) Arr::get($weeklyRollup, 'plan_execution_summary.average_completion_percentage', 0);
        if ($completion > 0 && $completion < 65) {
            $priorities[] = $this->priority(
                'Simplify Next Week Workload',
                'high',
                'recovery',
                'Team completion was '.$completion.'%, so next week should use shorter, simpler blocks.',
                'weekly_rollup',
                [],
                [],
                10,
            );
        } elseif ((int) Arr::get($weeklyRollup, 'plan_execution_summary.plans_published', 0) >= 3 && $completion >= 85) {
            $priorities[] = $this->priority(
                'Recovery / Mobility Day',
                'medium',
                'recovery',
                'The team completed a strong workload this week, so next week should include recovery and mobility.',
                'weekly_rollup',
                [],
                ['mobility_score'],
                18,
            );
        }

        return collect($priorities)
            ->unique(fn (array $row): string => strtolower((string) ($row['title'] ?? '')).'|'.(string) ($row['category'] ?? ''))
            ->sort(function (array $a, array $b): int {
                return ($this->priorityRank((string) ($b['priority'] ?? 'low')) <=> $this->priorityRank((string) ($a['priority'] ?? 'low')))
                    ?: ((int) ($a['rank'] ?? 99) <=> (int) ($b['rank'] ?? 99));
            })
            ->values()
            ->map(function (array $row, int $index): array {
                $row['rank'] = $index + 1;

                return $row;
            })
            ->take(8)
            ->all();
    }

    public function buildSuggestedPlanDays(string $teamId, array $priorities, array $options = []): array
    {
        $options = $this->normalizeOptions($options);
        $start = CarbonImmutable::parse((string) $options['next_week_start_date'])->startOfDay();
        $planDays = max(1, min(7, (int) $options['plan_days']));
        $maxMinutes = max(30, min(180, (int) $options['max_minutes_per_day']));
        $weeklyRollup = Arr::wrap($options['weekly_rollup'] ?? []);
        $completion = (float) Arr::get($weeklyRollup, 'plan_execution_summary.average_completion_percentage', 0);
        $volumeFactor = ($completion > 0 && $completion < 65) ? 0.75 : 1.0;
        $days = [];
        $templates = $this->dayTemplates($priorities, (bool) $options['include_recovery_day']);

        for ($i = 0; $i < $planDays; $i++) {
            $template = $templates[$i] ?? $this->reviewDayTemplate($priorities);
            $date = $start->addDays($i);
            $blocks = $this->blocksForTemplate($template, $priorities, $maxMinutes, $volumeFactor);
            $minutes = array_sum(array_map(fn (array $block): int => (int) ($block['duration_minutes'] ?? 0), $blocks));

            $days[] = [
                'day_index' => $i + 1,
                'day_label' => $date->format('l'),
                'scheduled_for' => $date->toDateString(),
                'title' => $template['title'],
                'primary_focus' => $template['primary_focus'],
                'estimated_total_minutes' => $minutes,
                'blocks' => $blocks,
                'player_assignments' => $this->assignmentsForBlocks($blocks),
                'coach_notes' => $template['coach_notes'],
                'why_this_day' => $template['why_this_day'],
            ];
        }

        return $days;
    }

    private function calendarDaysFromDraft(string $teamId, array $draft, array $options): array
    {
        $maxMinutes = max(30, min(180, (int) ($options['max_minutes_per_day'] ?? 90)));

        return collect(Arr::wrap($draft['suggested_plan_days'] ?? []))
            ->filter('is_array')
            ->map(function (array $day) use ($teamId, $maxMinutes): array {
                $blocks = array_values(array_filter(Arr::wrap($day['blocks'] ?? []), 'is_array'));
                $minutes = (int) ($day['estimated_total_minutes'] ?? array_sum(array_map(
                    fn (array $block): int => (int) ($block['duration_minutes'] ?? 0),
                    $blocks
                )));
                $scheduledFor = (string) ($day['scheduled_for'] ?? now()->toDateString());
                $saveStatus = $this->saveStatusForDate($teamId, $scheduledFor);
                $warnings = Arr::wrap($day['warnings'] ?? []);
                if ($minutes > $maxMinutes) {
                    $warnings[] = 'This day is over the target length. Move a lower-priority block to another day.';
                }
                if (($saveStatus['blocking_existing_plan'] ?? false) === true) {
                    $warnings[] = 'A plan already exists for this date.';
                }

                return [
                    'day_index' => (int) ($day['day_index'] ?? 0),
                    'day_label' => (string) ($day['day_label'] ?? CarbonImmutable::parse($scheduledFor)->format('l')),
                    'scheduled_for' => $scheduledFor,
                    'title' => (string) ($day['title'] ?? 'FMTRX Suggested Plan Day'),
                    'primary_focus' => (string) ($day['primary_focus'] ?? 'Weekly Plan'),
                    'estimated_total_minutes' => $minutes,
                    'workload_label' => $this->workloadLabel($minutes),
                    'blocks' => $blocks,
                    'metrics_to_collect' => $this->metricsFromBlocks($blocks),
                    'players' => $this->playersFromBlocks($blocks),
                    'player_assignments' => Arr::wrap($day['player_assignments'] ?? []),
                    'coach_notes' => Arr::wrap($day['coach_notes'] ?? []),
                    'why_this_day' => $day['why_this_day'] ?? null,
                    'warnings' => array_values(array_unique(array_filter($warnings))),
                    'save_status' => $saveStatus,
                ];
            })
            ->filter(fn (array $day): bool => (int) ($day['day_index'] ?? 0) > 0)
            ->values()
            ->all();
    }

    private function saveStatusForDate(string $teamId, string $scheduledFor): array
    {
        $plan = DailyPlan::query()
            ->where('team_id', $teamId)
            ->whereDate('date', $scheduledFor)
            ->orderByDesc('created_at')
            ->first();

        if (! $plan) {
            return [
                'already_saved' => false,
                'existing_daily_plan_id' => null,
                'status' => null,
                'blocking_existing_plan' => false,
                'message' => null,
            ];
        }

        $generated = $this->isWeeklyGeneratedPlan($plan);

        return [
            'already_saved' => $generated,
            'existing_daily_plan_id' => (string) $plan->id,
            'status' => $plan->status,
            'blocking_existing_plan' => ! $generated,
            'message' => $generated
                ? 'Draft already saved'
                : 'A plan already exists for this date.',
        ];
    }

    private function isWeeklyGeneratedPlan(DailyPlan $plan): bool
    {
        $buckets = Arr::wrap($plan->buckets ?? []);
        foreach ($buckets as $bucket) {
            if (! is_array($bucket)) {
                continue;
            }
            if (($bucket['source'] ?? null) === 'weekly_rollup_next_week_plan') {
                return true;
            }
            foreach (Arr::wrap($bucket['items'] ?? []) as $item) {
                if (! is_array($item)) {
                    continue;
                }
                if (($item['source'] ?? null) === 'weekly_rollup_next_week_plan') {
                    return true;
                }
                if (in_array('weekly-draft', Arr::wrap($item['tags'] ?? []), true)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function weeklyWorkloadSummary(array $calendarDays, array $draft): array
    {
        $total = array_sum(array_map(fn (array $day): int => (int) ($day['estimated_total_minutes'] ?? 0), $calendarDays));
        $count = count($calendarDays);
        $labels = collect($calendarDays)->pluck('workload_label')->countBy();

        return [
            'total_planned_minutes' => $total,
            'average_minutes_per_day' => $count > 0 ? round($total / $count, 1) : 0,
            'high_workload_days' => (int) (($labels['heavy'] ?? 0) + ($labels['too_heavy'] ?? 0)),
            'too_heavy_days' => (int) ($labels['too_heavy'] ?? 0),
            'recovery_support_days' => collect($calendarDays)->filter(function (array $day): bool {
                $text = strtolower((string) ($day['primary_focus'] ?? '').' '.(string) ($day['title'] ?? ''));
                return str_contains($text, 'recovery') || str_contains($text, 'mobility') || str_contains($text, 'support');
            })->count(),
            'benchmark_collection_targets' => count(Arr::wrap($draft['benchmark_collection_targets'] ?? [])),
            'players_needing_follow_up' => count(Arr::wrap($draft['player_assignments'] ?? [])),
            'workload_counts' => [
                'light' => (int) ($labels['light'] ?? 0),
                'moderate' => (int) ($labels['moderate'] ?? 0),
                'heavy' => (int) ($labels['heavy'] ?? 0),
                'too_heavy' => (int) ($labels['too_heavy'] ?? 0),
            ],
        ];
    }

    private function calendarWarnings(array $calendarDays, int $maxMinutes): array
    {
        $warnings = [];
        foreach ($calendarDays as $day) {
            if ((int) ($day['estimated_total_minutes'] ?? 0) > $maxMinutes) {
                $warnings[] = ((string) ($day['day_label'] ?? 'A day')).' is over the target length.';
            }
            if (Arr::get($day, 'save_status.blocking_existing_plan') === true) {
                $warnings[] = ((string) ($day['day_label'] ?? 'A day')).' already has a Daily Planner plan.';
            }
        }

        return array_values(array_unique($warnings));
    }

    private function workloadLabel(int $minutes): string
    {
        return match (true) {
            $minutes < 45 => 'light',
            $minutes <= 75 => 'moderate',
            $minutes <= 90 => 'heavy',
            default => 'too_heavy',
        };
    }

    private function metricsFromBlocks(array $blocks): array
    {
        return collect($blocks)
            ->flatMap(fn (array $block): array => Arr::wrap($block['metrics_to_collect'] ?? []))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function playersFromBlocks(array $blocks): array
    {
        return collect($blocks)
            ->flatMap(fn (array $block): array => Arr::wrap($block['players'] ?? []))
            ->filter('is_array')
            ->map(fn (array $player): array => [
                'player_id' => (string) ($player['player_id'] ?? ''),
                'player_name' => (string) ($player['player_name'] ?? $player['name'] ?? 'Player'),
            ])
            ->filter(fn (array $player): bool => $player['player_id'] !== '' || $player['player_name'] !== '')
            ->unique(fn (array $player): string => $player['player_id'] ?: $player['player_name'])
            ->values()
            ->all();
    }

    private function normalizeOptions(array $options): array
    {
        $days = max(1, min(365, (int) ($options['days'] ?? 7)));
        $nextWeekStart = ! empty($options['next_week_start_date'])
            ? CarbonImmutable::parse((string) $options['next_week_start_date'])->startOfDay()
            : now()->toImmutable()->startOfWeek()->addWeek()->startOfDay();

        return [
            'start_date' => $options['start_date'] ?? null,
            'end_date' => $options['end_date'] ?? null,
            'next_week_start_date' => $nextWeekStart->toDateString(),
            'days' => $days,
            'plan_days' => max(1, min(7, (int) ($options['plan_days'] ?? 5))),
            'max_minutes_per_day' => max(30, min(180, (int) ($options['max_minutes_per_day'] ?? 90))),
            'include_player_assignments' => filter_var($options['include_player_assignments'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'include_benchmark_collection' => filter_var($options['include_benchmark_collection'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'include_recovery_day' => filter_var($options['include_recovery_day'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    private function safeDecisionBrief(string $teamId, int $days, array &$warnings): ?array
    {
        try {
            return $this->decisionEngine->buildTeamDecisionBrief($teamId, max(7, min(365, $days)));
        } catch (Throwable $exception) {
            $warnings[] = 'Decision brief unavailable: '.$exception->getMessage();

            return null;
        }
    }

    private function safeCollectionPlan(string $teamId, int $days, array &$warnings): ?array
    {
        try {
            return $this->benchmarkCollectionPlanner->buildTeamCollectionPlan($teamId, max(7, min(365, $days)));
        } catch (Throwable $exception) {
            $warnings[] = 'Benchmark collection plan unavailable: '.$exception->getMessage();

            return null;
        }
    }

    private function weekReviewed(array $weeklyRollup): array
    {
        return [
            'start_date' => $weeklyRollup['start_date'] ?? null,
            'end_date' => $weeklyRollup['end_date'] ?? null,
            'plans_completed' => (int) Arr::get($weeklyRollup, 'plan_execution_summary.plans_completed', 0),
            'team_completion_percentage' => (float) Arr::get($weeklyRollup, 'plan_execution_summary.average_completion_percentage', 0),
            'benchmark_values_submitted' => (int) Arr::get($weeklyRollup, 'benchmark_collection_summary.metric_values_submitted', 0),
            'benchmark_values_approved' => (int) Arr::get($weeklyRollup, 'benchmark_collection_summary.metric_values_approved', 0),
            'pending_review_count' => (int) Arr::get($weeklyRollup, 'review_summary.pending_review_count', 0),
            'trusted_values_added' => (int) Arr::get($weeklyRollup, 'trusted_data_summary.trusted_values_added', 0),
        ];
    }

    private function priority(string $title, string $priority, string $category, string $why, string $source, array $players, array $metrics, ?int $minutes): array
    {
        return [
            'rank' => 999,
            'title' => $title,
            'priority' => $this->normalizePriority($priority),
            'category' => $category,
            'why' => $why,
            'source' => $source,
            'players' => $this->normalizePlayers($players),
            'metrics' => array_values(array_unique(array_filter(array_map('strval', $metrics)))),
            'recommended_minutes' => $minutes,
        ];
    }

    private function dayTemplates(array $priorities, bool $includeRecovery): array
    {
        $primaryPerformance = collect($priorities)->first(fn (array $row): bool => ! in_array((string) ($row['category'] ?? ''), ['data_collection', 'roster', 'recovery'], true));

        return array_values(array_filter([
            [
                'title' => 'Roster Cleanup + Benchmark Baseline Day',
                'primary_focus' => 'Benchmark Baseline',
                'categories' => ['roster', 'data_collection'],
                'why_this_day' => 'Start the week by clearing context gaps and collecting the highest-priority baselines.',
                'coach_notes' => ['Keep this as a coach-reviewable draft before assigning players.'],
            ],
            [
                'title' => ($primaryPerformance['title'] ?? 'Primary Performance Focus').' Day',
                'primary_focus' => $primaryPerformance['title'] ?? 'Primary Performance Focus',
                'categories' => [$primaryPerformance['category'] ?? 'hitting', 'hitting', 'pitching'],
                'why_this_day' => 'Use the current FMTRX decision brief as the main performance block.',
                'coach_notes' => ['Track the same metrics FMTRX used to choose the focus.'],
            ],
            [
                'title' => 'Strength / Athletic / Mobility Support',
                'primary_focus' => 'Physical Support',
                'categories' => ['strength', 'athletic', 'mobility'],
                'why_this_day' => 'Support baseball output with the physical qualities that may limit development.',
                'coach_notes' => ['Keep testing standards consistent so benchmark confidence improves.'],
            ],
            [
                'title' => 'Skill Transfer Day',
                'primary_focus' => 'Transfer to Baseball Skill',
                'categories' => ['throwing', 'hitting', 'pitching'],
                'why_this_day' => 'Turn baseline and performance findings into baseball-specific transfer work.',
                'coach_notes' => ['Avoid adding extra volume just to collect data.'],
            ],
            $includeRecovery ? [
                'title' => 'Review + Retest + Recovery',
                'primary_focus' => 'Review and Recovery',
                'categories' => ['data_collection', 'recovery', 'mobility'],
                'why_this_day' => 'Close the loop on submitted values, retest corrections, and recover before the next cycle.',
                'coach_notes' => ['Do not promote submitted values until coach review is complete.'],
            ] : null,
        ]));
    }

    private function reviewDayTemplate(array $priorities): array
    {
        return [
            'title' => 'Review + Retest',
            'primary_focus' => 'Review',
            'categories' => ['data_collection', 'recovery'],
            'why_this_day' => 'Use overflow priorities for review, retest, and recovery.',
            'coach_notes' => ['Review before publishing.'],
        ];
    }

    private function blocksForTemplate(array $template, array $priorities, int $maxMinutes, float $volumeFactor): array
    {
        $categories = Arr::wrap($template['categories'] ?? []);
        $matched = collect($priorities)
            ->filter(fn (array $priority): bool => in_array((string) ($priority['category'] ?? ''), $categories, true))
            ->values()
            ->all();

        if (empty($matched)) {
            $matched = [$this->fallbackPriorityForTemplate($template)];
        }

        $blocks = [];
        $minutes = 0;
        foreach ($matched as $priority) {
            $block = $this->blockFromPriority($priority, $volumeFactor);
            $duration = (int) ($block['duration_minutes'] ?? 0);
            if ($duration <= 0) {
                continue;
            }
            if (($minutes + $duration) > $maxMinutes && ! empty($blocks)) {
                continue;
            }
            $blocks[] = $block;
            $minutes += $duration;
        }

        if ($minutes + 6 <= $maxMinutes) {
            $blocks[] = [
                'title' => 'Coach Review / Notes',
                'category' => 'data_collection',
                'temporary_key' => 'review_debrief_block',
                'duration_minutes' => 6,
                'description' => 'Review the day, flag players needing follow-up, and confirm what should be trusted later.',
                'why' => 'This keeps the weekly loop coach-reviewed instead of automatic.',
                'instructions' => ['Review submitted values before approving or promoting trusted data.'],
                'metrics_to_collect' => [],
                'players' => [],
                'source' => 'weekly_rollup',
            ];
        }

        return array_values($blocks);
    }

    private function fallbackPriorityForTemplate(array $template): array
    {
        return $this->priority(
            (string) ($template['primary_focus'] ?? 'Daily Plan Block'),
            'low',
            (string) (($template['categories'][0] ?? null) ?: 'data_collection'),
            (string) ($template['why_this_day'] ?? 'Keep the week moving with a simple coach-reviewed block.'),
            'weekly_rollup',
            [],
            [],
            15,
        );
    }

    private function blockFromPriority(array $priority, float $volumeFactor): array
    {
        $minutes = (int) ($priority['recommended_minutes'] ?? 20);
        $minutes = max(6, (int) round($minutes * $volumeFactor));
        $category = (string) ($priority['category'] ?? 'data_collection');

        return [
            'title' => (string) ($priority['title'] ?? 'Practice Block'),
            'category' => $category,
            'temporary_key' => $this->temporaryKeyForPriority($priority),
            'duration_minutes' => $minutes,
            'description' => $this->descriptionForCategory($category, (string) ($priority['title'] ?? 'Practice Block')),
            'why' => (string) ($priority['why'] ?? ''),
            'instructions' => $this->instructionsForCategory($category),
            'metrics_to_collect' => Arr::wrap($priority['metrics'] ?? []),
            'players' => Arr::wrap($priority['players'] ?? []),
            'source' => (string) ($priority['source'] ?? 'weekly_rollup'),
        ];
    }

    private function temporaryKeyForPriority(array $priority): string
    {
        $title = strtolower((string) ($priority['title'] ?? ''));
        $category = (string) ($priority['category'] ?? '');

        return match (true) {
            $category === 'roster' || str_contains($title, 'roster') => 'roster_cleanup_block',
            str_contains($title, 'exit velocity') || $category === 'hitting' => 'exit_velocity_baseline_block',
            str_contains($title, 'fastball') || str_contains($title, 'bullpen') || $category === 'pitching' => 'bullpen_baseline_block',
            str_contains($title, 'long toss') || str_contains($title, 'throwing') || $category === 'throwing' => 'throwing_capacity_block',
            $category === 'strength' => 'strength_baseline_block',
            $category === 'athletic' => 'athletic_testing_block',
            $category === 'mobility' || $category === 'recovery' => 'mobility_screen_block',
            default => 'review_debrief_block',
        };
    }

    private function assignmentsForBlocks(array $blocks): array
    {
        return collect($blocks)
            ->flatMap(fn (array $block): array => collect(Arr::wrap($block['players'] ?? []))
                ->map(fn (array $player): array => [
                    'player_id' => (string) ($player['player_id'] ?? ''),
                    'player_name' => (string) ($player['player_name'] ?? $player['name'] ?? 'Player'),
                    'block' => $block['title'] ?? 'Practice Block',
                    'metrics_to_collect' => Arr::wrap($block['metrics_to_collect'] ?? []),
                ])
                ->filter(fn (array $player): bool => $player['player_id'] !== '')
                ->all())
            ->values()
            ->all();
    }

    private function playerAssignments(array $weeklyRollup, ?array $collectionPlan, array $suggestedDays): array
    {
        $rows = [];

        foreach (Arr::wrap(Arr::get($weeklyRollup, 'player_completion_summary.players_needing_follow_up', [])) as $player) {
            if (! is_array($player)) {
                continue;
            }
            $this->mergeAssignment($rows, [
                'player_id' => (string) ($player['player_id'] ?? ''),
                'player_name' => (string) ($player['player_name'] ?? 'Player'),
                'priority' => 'medium',
                'reason' => (string) ($player['reason'] ?? 'Needs follow-up from this week.'),
                'metrics_to_collect' => [],
                'recommended_days' => [],
            ]);
        }

        foreach (Arr::wrap($collectionPlan['player_tasks'] ?? []) as $task) {
            if (! is_array($task)) {
                continue;
            }
            $this->mergeAssignment($rows, [
                'player_id' => (string) ($task['player_id'] ?? ''),
                'player_name' => (string) ($task['player_name'] ?? 'Player'),
                'priority' => (string) ($task['priority'] ?? 'medium'),
                'reason' => (string) ($task['next_action'] ?? 'Complete benchmark baseline.'),
                'metrics_to_collect' => collect(Arr::wrap($task['missing_metrics'] ?? []))->pluck('metric_key')->filter()->values()->all(),
                'recommended_days' => [],
            ]);
        }

        foreach ($suggestedDays as $day) {
            foreach (Arr::wrap($day['player_assignments'] ?? []) as $assignment) {
                if (! is_array($assignment)) {
                    continue;
                }
                $this->mergeAssignment($rows, [
                    'player_id' => (string) ($assignment['player_id'] ?? ''),
                    'player_name' => (string) ($assignment['player_name'] ?? 'Player'),
                    'priority' => 'medium',
                    'reason' => 'Included in suggested day block.',
                    'metrics_to_collect' => Arr::wrap($assignment['metrics_to_collect'] ?? []),
                    'recommended_days' => [(int) ($day['day_index'] ?? 0)],
                ]);
            }
        }

        return collect($rows)
            ->map(function (array $row): array {
                $row['recommended_days'] = array_values(array_unique(array_filter($row['recommended_days'] ?? [])));
                $row['metrics_to_collect'] = array_values(array_unique(array_filter($row['metrics_to_collect'] ?? [])));
                $row['individual_notes'] = $row['reason'] ?? '';

                return $row;
            })
            ->sortByDesc(fn (array $row): int => $this->priorityRank((string) ($row['priority'] ?? 'low')))
            ->values()
            ->all();
    }

    private function mergeAssignment(array &$rows, array $assignment): void
    {
        $playerId = (string) ($assignment['player_id'] ?? '');
        if ($playerId === '') {
            return;
        }

        $rows[$playerId] ??= [
            'player_id' => $playerId,
            'player_name' => (string) ($assignment['player_name'] ?? 'Player'),
            'recommended_days' => [],
            'priority' => 'low',
            'reason' => '',
            'metrics_to_collect' => [],
            'individual_notes' => '',
        ];

        $rows[$playerId]['priority'] = $this->highestPriority([$rows[$playerId]['priority'], $assignment['priority'] ?? 'low']);
        $rows[$playerId]['reason'] = trim($rows[$playerId]['reason'].' '.($assignment['reason'] ?? ''));
        $rows[$playerId]['metrics_to_collect'] = array_values(array_unique([
            ...Arr::wrap($rows[$playerId]['metrics_to_collect']),
            ...Arr::wrap($assignment['metrics_to_collect'] ?? []),
        ]));
        $rows[$playerId]['recommended_days'] = array_values(array_unique([
            ...Arr::wrap($rows[$playerId]['recommended_days']),
            ...Arr::wrap($assignment['recommended_days'] ?? []),
        ]));
    }

    private function benchmarkCollectionTargets(array $weeklyRollup, ?array $collectionPlan): array
    {
        $targets = [];
        foreach (Arr::wrap($collectionPlan['collection_sessions'] ?? []) as $session) {
            if (! is_array($session)) {
                continue;
            }
            $targets[] = [
                'title' => $session['title'] ?? 'Benchmark Collection',
                'priority' => $session['priority'] ?? 'medium',
                'metrics' => Arr::wrap($session['metric_keys'] ?? $session['metrics'] ?? []),
                'players' => Arr::wrap($session['players'] ?? []),
                'duration_minutes' => (int) ($session['duration_minutes'] ?? 0),
                'why' => $session['why'] ?? null,
            ];
        }

        foreach (Arr::wrap(Arr::get($weeklyRollup, 'benchmark_collection_summary.top_missing_metrics_remaining', [])) as $metric) {
            if (! is_array($metric)) {
                continue;
            }
            $targets[] = [
                'title' => 'Collect '.(string) ($metric['display_name'] ?? $metric['metric_key'] ?? 'Benchmark Metric'),
                'priority' => 'medium',
                'metrics' => array_values(array_filter([(string) ($metric['metric_key'] ?? '')])),
                'players' => Arr::wrap($metric['players'] ?? []),
                'duration_minutes' => 12,
                'why' => 'This metric remains missing after the reviewed week.',
            ];
        }

        return collect($targets)
            ->unique(fn (array $row): string => strtolower((string) ($row['title'] ?? '')).implode(',', Arr::wrap($row['metrics'] ?? [])))
            ->values()
            ->take(8)
            ->all();
    }

    private function coachNotes(array $weeklyRollup, ?array $decisionBrief, ?array $collectionPlan, array $suggestedDays): array
    {
        $notes = [
            'This is a coach-reviewable draft. Nothing is published or assigned until the coach saves and edits a day in Daily Planner.',
        ];

        $completion = (float) Arr::get($weeklyRollup, 'plan_execution_summary.average_completion_percentage', 0);
        if ($completion > 0 && $completion < 65) {
            $notes[] = 'Team completion was low this week, so FMTRX reduced next-week volume and kept blocks simpler.';
        }

        if ((int) Arr::get($weeklyRollup, 'review_summary.pending_review_count', 0) > 0) {
            $notes[] = 'Review pending benchmark submissions before trusting them in future intelligence.';
        }

        if ((int) Arr::get($weeklyRollup, 'trusted_data_summary.trusted_values_added', 0) > 0) {
            $notes[] = 'Approved trusted data was added this week, so repeated collection should be intentional rather than automatic.';
        }

        $focus = Arr::get($decisionBrief ?? [], 'primary_focus.title');
        if ($focus) {
            $notes[] = 'Current FMTRX primary focus: '.$focus.'.';
        }

        if (($collectionPlan['priority_level'] ?? null) && ($collectionPlan['priority_level'] ?? null) !== 'low') {
            $notes[] = 'Benchmark collection priority remains '.ucfirst((string) $collectionPlan['priority_level']).'.';
        }

        if (collect($suggestedDays)->sum('estimated_total_minutes') <= 0) {
            $notes[] = 'Complete or assign plans this week to generate stronger next-week recommendations.';
        }

        return array_values(array_unique(array_filter($notes)));
    }

    private function generationStatus(array $weeklyRollup, array $priorities, array $days): string
    {
        if (($weeklyRollup['summary_status'] ?? null) === 'failed') {
            return 'failed';
        }
        if (empty($priorities) && (int) Arr::get($weeklyRollup, 'plan_execution_summary.plans_created', 0) === 0) {
            return 'empty';
        }
        if (empty($days)) {
            return 'partial';
        }

        return 'complete';
    }

    private function shouldBuildSuggestedDays(array $weeklyRollup, array $priorities): bool
    {
        if (! empty($priorities)) {
            return true;
        }

        return (int) Arr::get($weeklyRollup, 'plan_execution_summary.plans_created', 0) > 0
            || (int) Arr::get($weeklyRollup, 'benchmark_collection_summary.benchmark_items_assigned', 0) > 0
            || (int) Arr::get($weeklyRollup, 'benchmark_collection_summary.metric_values_submitted', 0) > 0;
    }

    private function categoryForCollection(string $type): string
    {
        return match (strtolower($type)) {
            'roster' => 'roster',
            'hitting' => 'hitting',
            'pitching' => 'pitching',
            'throwing' => 'throwing',
            'strength' => 'strength',
            'athletic' => 'athletic',
            'mobility' => 'mobility',
            default => 'data_collection',
        };
    }

    private function categoryForMetric(string $metricKey, string $fallback): string
    {
        return match (true) {
            str_contains($metricKey, 'exit_velocity'), str_contains($metricKey, 'hit'), str_contains($metricKey, 'line_drive') => 'hitting',
            str_contains($metricKey, 'fastball'), str_contains($metricKey, 'strike') => 'pitching',
            str_contains($metricKey, 'long_toss'), str_contains($metricKey, 'weighted_ball') => 'throwing',
            str_contains($metricKey, 'bench'), str_contains($metricKey, 'squat'), str_contains($metricKey, 'deadlift') => 'strength',
            str_contains($metricKey, 'dash'), str_contains($metricKey, 'jump') => 'athletic',
            str_contains($metricKey, 'mobility') => 'mobility',
            default => $fallback ?: 'data_collection',
        };
    }

    private function categoryForFocus(string $focus): string
    {
        $focus = strtolower($focus);

        return match (true) {
            str_contains($focus, 'exit velocity'), str_contains($focus, 'barrel'), str_contains($focus, 'power') => 'hitting',
            str_contains($focus, 'fastball'), str_contains($focus, 'command') => 'pitching',
            str_contains($focus, 'long toss'), str_contains($focus, 'transfer') => 'throwing',
            str_contains($focus, 'strength'), str_contains($focus, 'lower body') => 'strength',
            str_contains($focus, 'mobility'), str_contains($focus, 'arm care') => 'mobility',
            default => 'data_collection',
        };
    }

    private function descriptionForCategory(string $category, string $title): string
    {
        return match ($category) {
            'hitting' => 'Run a controlled hitting block and record EV, contact quality, and trajectory when applicable.',
            'pitching' => 'Run a focused mound block and track velocity, strike percentage, and execution.',
            'throwing' => 'Use throwing work to connect arm strength to baseball output without adding unnecessary volume.',
            'strength' => 'Collect or train strength qualities with safe standards and coach oversight.',
            'athletic' => 'Collect or train speed and power qualities with consistent testing standards.',
            'mobility', 'recovery' => 'Use mobility and recovery work to support the next high-intent session.',
            'roster' => 'Confirm player context fields before relying on age and peer benchmarks.',
            default => $title,
        };
    }

    private function instructionsForCategory(string $category): array
    {
        return match ($category) {
            'hitting' => ['Use controlled rounds first.', 'Record EV and contact quality.', 'Stop if contact quality collapses.'],
            'pitching' => ['Use a consistent pitch count.', 'Track velocity and strike percentage.', 'Review miss patterns.'],
            'throwing' => ['Build up normally.', 'Record clean max efforts only when appropriate.', 'Avoid extra high-intent volume.'],
            'strength' => ['Use safe loads.', 'Record best valid attempts only.', 'Keep standards consistent.'],
            'athletic' => ['Warm up fully.', 'Use consistent timing and measurement.', 'Record best valid attempt.'],
            'mobility', 'recovery' => ['Keep intensity low.', 'Screen movement quality.', 'Note restrictions for follow-up.'],
            'roster' => ['Confirm DOB and position first.', 'Add height, weight, throws, bats, and level.', 'Save each profile.'],
            default => ['Coach reviews before publishing.'],
        };
    }

    private function normalizePlayers(array $players): array
    {
        return collect($players)
            ->map(function ($player): ?array {
                if (! is_array($player)) {
                    return null;
                }
                $id = (string) ($player['player_id'] ?? $player['assigned_to_player_id'] ?? '');
                $name = (string) ($player['player_name'] ?? $player['name'] ?? $player['assigned_to_player_name'] ?? 'Player');

                return [
                    'player_id' => $id,
                    'player_name' => $name,
                ];
            })
            ->filter(fn (?array $player): bool => is_array($player) && (($player['player_id'] ?? '') !== '' || ($player['player_name'] ?? '') !== ''))
            ->unique(fn (array $player): string => (string) ($player['player_id'] ?: $player['player_name']))
            ->values()
            ->all();
    }

    private function normalizePriority(string $priority): string
    {
        $priority = strtolower($priority);

        return in_array($priority, ['critical', 'high', 'medium', 'low'], true) ? $priority : 'medium';
    }

    private function highestPriority(array $priorities): string
    {
        return collect($priorities)
            ->map(fn ($priority): string => $this->normalizePriority((string) $priority))
            ->sortByDesc(fn (string $priority): int => $this->priorityRank($priority))
            ->first() ?? 'low';
    }

    private function priorityRank(string $priority): int
    {
        return match ($this->normalizePriority($priority)) {
            'critical' => 4,
            'high' => 3,
            'medium' => 2,
            'low' => 1,
        };
    }
}
