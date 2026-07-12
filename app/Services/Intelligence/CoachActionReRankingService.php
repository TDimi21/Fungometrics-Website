<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use Throwable;

class CoachActionReRankingService
{
    public function __construct(
        private readonly DecisionEngine $decisionEngine,
        private readonly BenchmarkCollectionPlanner $benchmarkCollectionPlanner,
        private readonly CoachActionPracticePlanner $coachActionPracticePlanner,
    ) {
    }

    public function rerankAfterBenchmarkRefresh(string $teamId, array $before = [], array $after = [], array $options = []): array
    {
        $days = $this->days($options['days'] ?? 365);
        $warnings = [];

        try {
            $beforeRanking = empty($before)
                ? $this->emptyRanking($teamId, $days)
                : $this->rankingFromState($teamId, $before, $days, false);
        } catch (Throwable $exception) {
            $beforeRanking = $this->emptyRanking($teamId, $days);
            $warnings[] = 'Before coach action ranking unavailable: '.$exception->getMessage();
        }

        try {
            $afterRanking = empty($after)
                ? $this->buildCurrentActionRanking($teamId, $days)
                : $this->rankingFromState($teamId, $after, $days, true);
        } catch (Throwable $exception) {
            return [
                'generated_at' => now()->toIso8601String(),
                'team_id' => $teamId,
                'rerank_status' => 'failed',
                'primary_focus_before' => $beforeRanking['primary_focus'] ?? null,
                'primary_focus_after' => null,
                'data_collection_priority_before' => $beforeRanking['data_collection_priority'] ?? null,
                'data_collection_priority_after' => null,
                'top_actions_before' => $beforeRanking['top_actions'] ?? [],
                'top_actions_after' => [],
                'action_changes' => [],
                'removed_actions' => [],
                'new_actions' => [],
                'updated_practice_plan' => [],
                'coach_summary' => 'Coach action ranking could not be rebuilt.',
                'warnings' => [$exception->getMessage()],
                'evidence' => [
                    'days' => $days,
                    'exception' => class_basename($exception),
                ],
            ];
        }

        $comparison = $this->compareActionRankings($beforeRanking, $afterRanking);
        $actionChanges = [
            ...($comparison['action_changes'] ?? []),
            ...$this->changesFromRescore($options['rescore_changes'] ?? [], $beforeRanking, $afterRanking),
        ];
        $actionChanges = $this->uniqueChanges($actionChanges);

        $warnings = array_values(array_filter([
            ...$warnings,
            ...($beforeRanking['warnings'] ?? []),
            ...($afterRanking['warnings'] ?? []),
        ]));

        return [
            'generated_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'rerank_status' => empty($warnings) ? 'completed' : 'partial',
            'primary_focus_before' => $beforeRanking['primary_focus'] ?? null,
            'primary_focus_after' => $afterRanking['primary_focus'] ?? null,
            'data_collection_priority_before' => $beforeRanking['data_collection_priority'] ?? null,
            'data_collection_priority_after' => $afterRanking['data_collection_priority'] ?? null,
            'top_actions_before' => $beforeRanking['top_actions'] ?? [],
            'top_actions_after' => $afterRanking['top_actions'] ?? [],
            'action_changes' => $actionChanges,
            'removed_actions' => $comparison['removed_actions'] ?? [],
            'new_actions' => $comparison['new_actions'] ?? [],
            'updated_practice_plan' => $afterRanking['updated_practice_plan'] ?? [],
            'coach_summary' => $this->buildCoachActionChangeSummary([
                ...$beforeRanking,
                'action_changes' => [],
            ], [
                ...$afterRanking,
                'action_changes' => $actionChanges,
                'removed_actions' => $comparison['removed_actions'] ?? [],
                'new_actions' => $comparison['new_actions'] ?? [],
            ]),
            'warnings' => $warnings,
            'evidence' => [
                'days' => $days,
                'before_available' => ! empty($before),
                'after_provided' => ! empty($after),
                'top_action_before' => $beforeRanking['top_actions'][0]['title'] ?? null,
                'top_action_after' => $afterRanking['top_actions'][0]['title'] ?? null,
                'practice_plan_before' => $beforeRanking['updated_practice_plan']['plan_title'] ?? null,
                'practice_plan_after' => $afterRanking['updated_practice_plan']['plan_title'] ?? null,
                'change_count' => count($actionChanges),
            ],
        ];
    }

    public function buildCurrentActionRanking(string $teamId, int $days = 365): array
    {
        return $this->rankingFromState($teamId, [], $this->days($days), true);
    }

    public function compareActionRankings(array $beforeActions, array $afterActions): array
    {
        $before = $this->actionMap($beforeActions['top_actions'] ?? []);
        $after = $this->actionMap($afterActions['top_actions'] ?? []);
        $changes = [];
        $removed = [];
        $new = [];

        $beforeFocus = $beforeActions['primary_focus'] ?? null;
        $afterFocus = $afterActions['primary_focus'] ?? null;
        if ($beforeFocus && $afterFocus && $beforeFocus !== $afterFocus) {
            $changes[] = [
                'type' => 'primary_focus_changed',
                'before' => $beforeFocus,
                'after' => $afterFocus,
                'message' => 'Top focus changed from '.$beforeFocus.' to '.$afterFocus.'.',
            ];
        }

        $beforePriority = $beforeActions['data_collection_priority'] ?? null;
        $afterPriority = $afterActions['data_collection_priority'] ?? null;
        if ($beforePriority && $afterPriority && $beforePriority !== $afterPriority) {
            $lowered = $this->priorityRank($afterPriority) < $this->priorityRank($beforePriority);
            $changes[] = [
                'type' => $lowered ? 'data_collection_priority_lowered' : 'data_collection_priority_changed',
                'before' => $beforePriority,
                'after' => $afterPriority,
                'message' => $lowered
                    ? 'Data collection priority dropped from '.$this->label($beforePriority).' to '.$this->label($afterPriority).'.'
                    : 'Data collection priority changed from '.$this->label($beforePriority).' to '.$this->label($afterPriority).'.',
            ];
        }

        foreach ($before as $key => $action) {
            if (isset($after[$key])) {
                continue;
            }

            $removed[] = $action;
            $changes[] = [
                'type' => 'action_removed',
                'title' => $action['title'] ?? 'Coach Action',
                'message' => ($action['title'] ?? 'Coach action').' is no longer in the top action list.',
            ];
        }

        foreach ($after as $key => $action) {
            if (isset($before[$key])) {
                continue;
            }

            $new[] = $action;
            $changes[] = [
                'type' => 'action_added',
                'title' => $action['title'] ?? 'Coach Action',
                'message' => 'New action added: '.($action['title'] ?? 'Coach Action').'.',
            ];
        }

        $beforePlan = $beforeActions['updated_practice_plan']['plan_title'] ?? null;
        $afterPlan = $afterActions['updated_practice_plan']['plan_title'] ?? null;
        if ($beforePlan && $afterPlan && $beforePlan !== $afterPlan) {
            $changes[] = [
                'type' => 'practice_plan_changed',
                'before' => $beforePlan,
                'after' => $afterPlan,
                'message' => 'Suggested practice plan changed from '.$beforePlan.' to '.$afterPlan.'.',
            ];
        }

        return [
            'action_changes' => $this->uniqueChanges($changes),
            'removed_actions' => array_values($removed),
            'new_actions' => array_values($new),
        ];
    }

    public function buildCoachActionChangeSummary(array $before, array $after): string
    {
        $changes = $after['action_changes'] ?? [];
        $beforeFocus = $before['primary_focus'] ?? null;
        $afterFocus = $after['primary_focus'] ?? null;
        $beforePriority = $before['data_collection_priority'] ?? null;
        $afterPriority = $after['data_collection_priority'] ?? null;

        if ($beforeFocus && $afterFocus && $beforeFocus !== $afterFocus) {
            return 'FMTRX updated the action list after approved benchmark data changed the top focus from '.$beforeFocus.' to '.$afterFocus.'.';
        }

        if ($beforePriority && $afterPriority && $this->priorityRank($afterPriority) < $this->priorityRank($beforePriority)) {
            return 'Data collection is still tracked, but its priority dropped after approved benchmark data improved coverage.';
        }

        if (! empty($after['new_actions'] ?? [])) {
            return 'FMTRX added a new coach action after the refreshed benchmark profile changed the team priorities.';
        }

        if (! empty($after['removed_actions'] ?? [])) {
            return 'FMTRX removed completed data actions from the top list after trusted benchmark data was promoted.';
        }

        if (! empty($changes)) {
            return 'FMTRX updated the coach action list after approved benchmark data improved team coverage.';
        }

        return 'Coach actions were refreshed from the latest trusted benchmark data.';
    }

    private function rankingFromState(string $teamId, array $state, int $days, bool $allowLiveFallback): array
    {
        $warnings = [];
        $decisionBrief = is_array($state['decision_brief'] ?? null) ? $state['decision_brief'] : [];
        $collectionPlan = is_array($state['collection_plan'] ?? null) ? $state['collection_plan'] : [];
        $practicePlan = is_array($state['coach_action_practice_plan'] ?? null) ? $state['coach_action_practice_plan'] : [];

        if (empty($decisionBrief) && $allowLiveFallback) {
            try {
                $decisionBrief = $this->decisionEngine->buildTeamDecisionBrief($teamId, $days);
            } catch (Throwable $exception) {
                $warnings[] = 'Decision brief unavailable: '.$exception->getMessage();
            }
        }

        if (empty($collectionPlan) && $allowLiveFallback) {
            try {
                $collectionPlan = $this->benchmarkCollectionPlanner->buildTeamCollectionPlan($teamId, $days);
            } catch (Throwable $exception) {
                $warnings[] = 'Benchmark collection plan unavailable: '.$exception->getMessage();
            }
        }

        if (empty($practicePlan) && $allowLiveFallback) {
            try {
                $practicePlan = $this->coachActionPracticePlanner->buildPracticePlanFromCoachActions($teamId, $days);
            } catch (Throwable $exception) {
                $warnings[] = 'Coach action practice plan unavailable: '.$exception->getMessage();
            }
        }

        $actions = $this->rankActions($this->buildActions($decisionBrief, $collectionPlan, $practicePlan));

        return [
            'generated_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'days' => $days,
            'primary_focus' => $this->primaryFocus($decisionBrief),
            'data_collection_priority' => $this->dataCollectionPriority($decisionBrief, $collectionPlan),
            'top_actions' => array_slice($actions, 0, 8),
            'updated_practice_plan' => $practicePlan,
            'warnings' => $warnings,
            'evidence' => [
                'decision_brief_available' => ! empty($decisionBrief),
                'collection_plan_available' => ! empty($collectionPlan),
                'practice_plan_available' => ! empty($practicePlan),
                'action_count' => count($actions),
            ],
        ];
    }

    private function buildActions(array $decisionBrief, array $collectionPlan, array $practicePlan): array
    {
        $actions = [];
        $primary = $decisionBrief['primary_focus'] ?? null;
        if (is_array($primary) && ! empty($primary['title'] ?? null)) {
            $actions[] = $this->action([
                'title' => $primary['title'],
                'priority' => $this->priorityForDecision($decisionBrief),
                'category' => $primary['category'] ?? 'practice',
                'why' => $primary['why'] ?? null,
                'action' => $primary['action'] ?? null,
                'players' => $decisionBrief['players_needing_attention'] ?? [],
                'metrics' => [],
                'source' => 'decision_brief',
                'reason_for_rank' => 'Primary performance focus from DecisionEngine.',
            ]);
        }

        $next = $collectionPlan['next_best_action'] ?? null;
        if (is_array($next) && ($next['title'] ?? '') !== 'No Benchmark Collection Needed') {
            $actions[] = $this->action([
                'title' => $next['title'] ?? 'Benchmark Collection',
                'priority' => $next['priority'] ?? $collectionPlan['priority_level'] ?? 'low',
                'category' => 'data_collection',
                'why' => $next['why'] ?? null,
                'action' => implode(' ', $next['coach_instructions'] ?? []),
                'players' => $next['players'] ?? [],
                'metrics' => $next['metrics'] ?? [],
                'estimated_minutes' => $next['duration_minutes'] ?? null,
                'source' => 'collection_plan',
                'reason_for_rank' => 'Top data action from BenchmarkCollectionPlanner.',
            ]);
        }

        foreach (array_slice($collectionPlan['collection_sessions'] ?? [], 0, 5) as $session) {
            if (! is_array($session)) {
                continue;
            }

            $actions[] = $this->action([
                'title' => $session['title'] ?? 'Collection Session',
                'priority' => $session['priority'] ?? $collectionPlan['priority_level'] ?? 'low',
                'category' => $session['collection_type'] ?? 'data_collection',
                'why' => $session['why'] ?? null,
                'action' => $session['description'] ?? null,
                'players' => $session['players'] ?? [],
                'metrics' => $session['metric_keys'] ?? $session['metrics'] ?? [],
                'estimated_minutes' => $session['duration_minutes'] ?? null,
                'source' => 'collection_plan',
                'reason_for_rank' => 'Collection block from BenchmarkCollectionPlanner.',
            ]);
        }

        foreach (array_slice($practicePlan['source_actions'] ?? [], 0, 5) as $sourceAction) {
            if (! is_array($sourceAction)) {
                continue;
            }

            $actions[] = $this->action([
                'title' => $sourceAction['title'] ?? null,
                'priority' => $sourceAction['priority'] ?? 'medium',
                'category' => $sourceAction['category'] ?? null,
                'why' => $sourceAction['why'] ?? null,
                'action' => $sourceAction['action'] ?? null,
                'players' => $sourceAction['players'] ?? [],
                'metrics' => $sourceAction['metrics'] ?? [],
                'estimated_minutes' => $sourceAction['duration_minutes'] ?? null,
                'source' => $sourceAction['source'] ?? 'practice_plan',
                'reason_for_rank' => 'Source action from CoachActionPracticePlanner.',
            ]);
        }

        foreach (array_slice($practicePlan['practice_blocks'] ?? [], 0, 5) as $block) {
            if (! is_array($block) || empty($block['title'] ?? null)) {
                continue;
            }

            $actions[] = $this->action([
                'title' => $block['title'],
                'priority' => $block['priority'] ?? 'medium',
                'category' => $block['category'] ?? null,
                'why' => $block['why'] ?? null,
                'action' => $block['description'] ?? null,
                'players' => $block['players'] ?? [],
                'metrics' => $block['metrics_to_collect'] ?? [],
                'estimated_minutes' => $block['duration_minutes'] ?? null,
                'source' => 'practice_plan',
                'reason_for_rank' => 'Practice block from CoachActionPracticePlanner.',
            ]);
        }

        return $this->uniqueActions($actions);
    }

    private function action(array $data): array
    {
        $metricKeys = $this->metricKeys($data['metrics'] ?? []);

        return [
            'rank' => 0,
            'title' => (string) ($data['title'] ?? 'Coach Action'),
            'priority' => $this->normalizePriority((string) ($data['priority'] ?? 'medium')),
            'category' => $data['category'] ?? null,
            'why' => (string) ($data['why'] ?? 'FMTRX found a benchmark signal that needs coach attention.'),
            'action' => (string) ($data['action'] ?? 'Review the current benchmark profile and run the recommended block.'),
            'players' => $this->players($data['players'] ?? []),
            'metrics' => array_map(fn (string $metric): string => $this->label($metric), $metricKeys),
            'metric_keys' => $metricKeys,
            'estimated_minutes' => is_numeric($data['estimated_minutes'] ?? null) ? (int) $data['estimated_minutes'] : null,
            'source' => (string) ($data['source'] ?? 'decision_brief'),
            'reason_for_rank' => (string) ($data['reason_for_rank'] ?? 'Ranked from current intelligence output.'),
        ];
    }

    private function rankActions(array $actions): array
    {
        usort($actions, function (array $a, array $b) {
            $aScore = $this->actionScore($a);
            $bScore = $this->actionScore($b);

            return $bScore <=> $aScore ?: strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
        });

        return array_values(array_map(function (array $action, int $index): array {
            $action['rank'] = $index + 1;

            return $action;
        }, $actions, array_keys($actions)));
    }

    private function actionScore(array $action): int
    {
        $score = $this->priorityRank((string) ($action['priority'] ?? 'low')) * 100;
        $source = (string) ($action['source'] ?? '');
        $category = (string) ($action['category'] ?? '');

        if ($source === 'decision_brief') {
            $score += 40;
        }
        if ($category === 'data_collection') {
            $score += $this->priorityRank((string) ($action['priority'] ?? 'low')) >= 4 ? 50 : 0;
        }
        if ($source === 'collection_plan') {
            $score += 20;
        }
        if ($source === 'practice_plan') {
            $score += 10;
        }

        return $score;
    }

    private function changesFromRescore(array $changes, array $beforeRanking, array $afterRanking): array
    {
        $actionChanges = [];
        foreach ($changes as $change) {
            if (! is_array($change)) {
                continue;
            }

            $type = (string) ($change['type'] ?? '');
            if ($type === 'collection_gap_removed') {
                $actionChanges[] = [
                    'type' => 'action_removed',
                    'title' => 'Collect '.$this->label((string) ($change['metric_key'] ?? $change['display_name'] ?? 'Benchmark Data')),
                    'message' => ($change['message'] ?? 'A missing benchmark action was completed.'),
                    'metric_key' => $change['metric_key'] ?? null,
                    'player_id' => $change['player_id'] ?? null,
                ];
            }

            if ($type === 'new_trusted_metric') {
                $actionChanges[] = [
                    'type' => 'trusted_metric_promoted',
                    'title' => $change['display_name'] ?? $this->label((string) ($change['metric_key'] ?? 'Benchmark Data')),
                    'message' => ($change['message'] ?? 'A new trusted benchmark metric is available.'),
                    'metric_key' => $change['metric_key'] ?? null,
                    'player_id' => $change['player_id'] ?? null,
                ];
            }

            if ($type === 'confidence_changed') {
                $actionChanges[] = [
                    'type' => 'benchmark_confidence_changed',
                    'before' => $change['before'] ?? null,
                    'after' => $change['after'] ?? null,
                    'message' => $change['message'] ?? 'Benchmark confidence changed.',
                ];
            }
        }

        return $actionChanges;
    }

    private function emptyRanking(string $teamId, int $days): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'days' => $days,
            'primary_focus' => null,
            'data_collection_priority' => null,
            'top_actions' => [],
            'updated_practice_plan' => [],
            'warnings' => [],
            'evidence' => [
                'decision_brief_available' => false,
                'collection_plan_available' => false,
                'practice_plan_available' => false,
                'action_count' => 0,
            ],
        ];
    }

    private function actionMap(array $actions): array
    {
        $map = [];
        foreach ($actions as $action) {
            if (! is_array($action)) {
                continue;
            }

            $key = $this->actionKey($action);
            if ($key !== '') {
                $map[$key] = $action;
            }
        }

        return $map;
    }

    private function actionKey(array $action): string
    {
        return strtolower(trim((string) ($action['title'] ?? ''))).'|'.strtolower(trim((string) ($action['category'] ?? '')));
    }

    private function uniqueActions(array $actions): array
    {
        $seen = [];
        $unique = [];

        foreach ($actions as $action) {
            $key = $this->actionKey($action);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $action;
        }

        return $unique;
    }

    private function uniqueChanges(array $changes): array
    {
        $seen = [];
        $unique = [];

        foreach ($changes as $change) {
            if (! is_array($change)) {
                continue;
            }

            $key = implode('|', [
                $change['type'] ?? '',
                $change['title'] ?? '',
                $change['metric_key'] ?? '',
                $change['message'] ?? '',
            ]);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $change;
        }

        return $unique;
    }

    private function priorityForDecision(array $decisionBrief): string
    {
        $confidence = strtolower((string) ($decisionBrief['confidence'] ?? 'medium'));

        return match ($confidence) {
            'high' => 'high',
            'low' => 'medium',
            default => 'high',
        };
    }

    private function primaryFocus(array $decisionBrief): ?string
    {
        $focus = $decisionBrief['primary_focus'] ?? null;

        return is_array($focus) ? ($focus['title'] ?? null) : null;
    }

    private function dataCollectionPriority(array $decisionBrief, array $collectionPlan): ?string
    {
        return $collectionPlan['priority_level'] ?? ($decisionBrief['data_collection_priority']['level'] ?? null);
    }

    private function players(mixed $players): array
    {
        return collect(is_array($players) ? $players : [])
            ->map(function ($player): ?array {
                if (! is_array($player)) {
                    return null;
                }

                $name = $player['player_name'] ?? $player['name'] ?? $player['assigned_to_player_name'] ?? $player['player_id'] ?? null;
                if (! $name && empty($player['player_id'])) {
                    return null;
                }

                return [
                    'player_id' => $player['player_id'] ?? $player['id'] ?? $player['assigned_to_player_id'] ?? null,
                    'player_name' => $name ?? 'Unknown Player',
                    'name' => $name ?? 'Unknown Player',
                ];
            })
            ->filter()
            ->unique(fn (array $player): string => (string) ($player['player_id'] ?? $player['player_name'] ?? $player['name'] ?? ''))
            ->values()
            ->all();
    }

    private function metricKeys(mixed $metrics): array
    {
        return collect(is_array($metrics) ? $metrics : [])
            ->map(function ($metric): ?string {
                if (is_array($metric)) {
                    $metric = $metric['metric_key'] ?? $metric['display_name'] ?? null;
                }

                return $metric ? BenchmarkDefinitions::normalizeMetricKey((string) $metric) : null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalizePriority(string $priority): string
    {
        $priority = strtolower(trim($priority));

        return match ($priority) {
            'critical' => 'critical',
            'high' => 'high',
            'medium', 'moderate' => 'medium',
            default => 'low',
        };
    }

    private function priorityRank(?string $priority): int
    {
        return [
            'none' => 0,
            'low' => 1,
            'medium' => 2,
            'moderate' => 2,
            'high' => 3,
            'critical' => 4,
        ][strtolower((string) ($priority ?? 'low'))] ?? 1;
    }

    private function label(?string $value): string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? ucwords(str_replace('_', ' ', $value)) : 'Needs Data';
    }

    private function days(mixed $days): int
    {
        return max(7, min(365, (int) $days));
    }
}
