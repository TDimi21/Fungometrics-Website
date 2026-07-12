<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use Throwable;

class BenchmarkDataQualityRescoreService
{
    public function __construct(
        private readonly PlayerIntelligenceService $playerIntelligenceService,
        private readonly TeamBenchmarkProfileService $teamBenchmarkProfileService,
        private readonly DecisionEngine $decisionEngine,
        private readonly BenchmarkCollectionPlanner $benchmarkCollectionPlanner,
        private readonly CoachActionPracticePlanner $coachActionPracticePlanner,
        private readonly CoachActionReRankingService $coachActionReRankingService,
    ) {
    }

    public function rescoreAfterPromotion(string $teamId, ?string $playerId = null, array $options = []): array
    {
        $days = $this->days($options['days'] ?? 365);
        $warnings = [];
        $before = is_array($options['before'] ?? null) ? $options['before'] : [];

        if (empty($before)) {
            $warnings[] = 'True before snapshot was not available. Returning after state with best-effort promotion changes.';
        }

        try {
            $after = $this->buildCurrentState($teamId, $playerId, $days);
        } catch (Throwable $exception) {
            return [
                'generated_at' => now()->toIso8601String(),
                'team_id' => $teamId,
                'player_id' => $playerId,
                'rescore_status' => 'failed',
                'before' => $before,
                'after' => [],
                'changes' => [],
                'improvement_summary' => [],
                'remaining_gaps' => [],
                'next_recommended_actions' => [],
                'warnings' => [$exception->getMessage()],
                'evidence' => [
                    'days' => $days,
                    'exception' => class_basename($exception),
                ],
            ];
        }

        $warnings = array_values(array_filter([
            ...$warnings,
            ...($after['warnings'] ?? []),
        ]));
        $teamComparison = $this->compareTeamDataQuality($teamId, $before, $after, $days);
        $playerComparison = $playerId
            ? $this->comparePlayerBenchmarkProfile($teamId, $playerId, $before, $after, $days)
            : ['changes' => []];
        $promotionChanges = $this->promotionChanges($options, $after);
        $changes = $this->uniqueChanges([
            ...($teamComparison['changes'] ?? []),
            ...($playerComparison['changes'] ?? []),
            ...$promotionChanges,
        ]);
        $actionRerank = [];

        try {
            $actionRerank = $this->coachActionReRankingService->rerankAfterBenchmarkRefresh($teamId, $before, $after, [
                'days' => $days,
                'rescore_changes' => $changes,
                'include_practice_plan_update_suggestions' => true,
            ]);
        } catch (Throwable $exception) {
            $warnings[] = 'Benchmark data was re-scored, but coach action ranking will update on next dashboard load.';
            $actionRerank = [
                'generated_at' => now()->toIso8601String(),
                'team_id' => $teamId,
                'rerank_status' => 'failed',
                'primary_focus_before' => $this->stateSummaryFromState($before)['decision_focus'] ?? null,
                'primary_focus_after' => $this->stateSummaryFromState($after)['decision_focus'] ?? null,
                'data_collection_priority_before' => $this->stateSummaryFromState($before)['collection_priority'] ?? null,
                'data_collection_priority_after' => $this->stateSummaryFromState($after)['collection_priority'] ?? null,
                'top_actions_before' => [],
                'top_actions_after' => [],
                'action_changes' => [],
                'removed_actions' => [],
                'new_actions' => [],
                'updated_practice_plan' => [],
                'coach_summary' => 'Benchmark data was re-scored, but coach action ranking will update on next dashboard load.',
                'warnings' => [$exception->getMessage()],
            ];
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'player_id' => $playerId,
            'rescore_status' => empty($warnings) ? 'completed' : 'partial',
            'before' => $before,
            'after' => $after,
            'changes' => $changes,
            'improvement_summary' => $this->buildImprovementSummary($before, $after),
            'remaining_gaps' => $this->remainingGaps($after),
            'next_recommended_actions' => $this->nextRecommendedActions($after),
            'action_rerank' => $actionRerank,
            'warnings' => $warnings,
            'evidence' => [
                'days' => $days,
                'before_available' => ! empty($before),
                'change_count' => count($changes),
                'player_rescored' => (bool) $playerId,
                'promotion_task_id' => $options['promotion']['task_id'] ?? $options['trusted_payload']['task_id'] ?? null,
                'trusted_payload_source' => $options['trusted_payload']['submitted_source'] ?? null,
            ],
        ];
    }

    public function buildCurrentState(string $teamId, ?string $playerId = null, int $days = 365): array
    {
        $days = $this->days($days);
        $warnings = [];
        $teamProfile = [];
        $decisionBrief = [];
        $collectionPlan = [];
        $coachActionPracticePlan = [];
        $playerSnapshot = [];
        $playerProfile = [];

        try {
            $teamProfile = $this->teamBenchmarkProfileService->build($teamId, $days);
        } catch (Throwable $exception) {
            $warnings[] = 'Team benchmark profile unavailable: '.$exception->getMessage();
        }

        try {
            $decisionBrief = $this->decisionEngine->buildTeamDecisionBrief($teamId, $days);
        } catch (Throwable $exception) {
            $warnings[] = 'Decision brief unavailable: '.$exception->getMessage();
        }

        try {
            $collectionPlan = ! empty($teamProfile)
                ? $this->benchmarkCollectionPlanner->buildTeamCollectionPlanFromData($teamId, $days, $teamProfile, $decisionBrief)
                : $this->benchmarkCollectionPlanner->buildTeamCollectionPlan($teamId, $days);
        } catch (Throwable $exception) {
            $warnings[] = 'Benchmark collection plan unavailable: '.$exception->getMessage();
        }

        try {
            $coachActionPracticePlan = $this->coachActionPracticePlanner->buildPracticePlanFromCoachActions($teamId, $days);
        } catch (Throwable $exception) {
            $warnings[] = 'Coach action practice plan unavailable: '.$exception->getMessage();
        }

        if ($playerId) {
            try {
                $playerSnapshot = $this->playerIntelligenceService->build($teamId, $playerId, $days);
                $playerProfile = is_array($playerSnapshot['benchmark_profile'] ?? null)
                    ? $playerSnapshot['benchmark_profile']
                    : [];
            } catch (Throwable $exception) {
                $warnings[] = 'Player benchmark profile unavailable: '.$exception->getMessage();
            }
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'player_id' => $playerId,
            'team_benchmark_profile' => $teamProfile,
            'player_benchmark_profile' => $playerProfile,
            'player_snapshot' => $playerSnapshot,
            'decision_brief' => $decisionBrief,
            'collection_plan' => $collectionPlan,
            'coach_action_practice_plan' => $coachActionPracticePlan,
            'summary' => $this->stateSummary($teamProfile, $decisionBrief, $collectionPlan),
            'warnings' => $warnings,
        ];
    }

    public function compareTeamDataQuality(string $teamId, array $before = [], array $after = [], int $days = 365): array
    {
        $beforeSummary = $this->stateSummaryFromState($before);
        $afterSummary = $this->stateSummaryFromState($after);
        $changes = [];

        $coverageBefore = $beforeSummary['completion_percentage'] ?? null;
        $coverageAfter = $afterSummary['completion_percentage'] ?? null;
        if (is_numeric($coverageBefore) && is_numeric($coverageAfter) && $coverageAfter > $coverageBefore) {
            $changes[] = [
                'type' => 'coverage_improved',
                'message' => 'Team benchmark coverage improved from '.$this->fmt($coverageBefore).'% to '.$this->fmt($coverageAfter).'%.',
                'before' => $coverageBefore,
                'after' => $coverageAfter,
            ];
        }

        $metricBefore = $beforeSummary['benchmark_metric_count'] ?? null;
        $metricAfter = $afterSummary['benchmark_metric_count'] ?? null;
        if (is_numeric($metricBefore) && is_numeric($metricAfter) && $metricAfter > $metricBefore) {
            $changes[] = [
                'type' => 'metric_count_improved',
                'message' => 'Team benchmark metric count increased from '.$metricBefore.' to '.$metricAfter.'.',
                'before' => $metricBefore,
                'after' => $metricAfter,
            ];
        }

        if ($this->confidenceRank($afterSummary['benchmark_confidence'] ?? null) > $this->confidenceRank($beforeSummary['benchmark_confidence'] ?? null)) {
            $changes[] = [
                'type' => 'confidence_changed',
                'message' => 'Benchmark confidence improved from '.$this->label($beforeSummary['benchmark_confidence'] ?? null).' to '.$this->label($afterSummary['benchmark_confidence'] ?? null).'.',
                'before' => $beforeSummary['benchmark_confidence'] ?? null,
                'after' => $afterSummary['benchmark_confidence'] ?? null,
            ];
        }

        if (($beforeSummary['decision_focus'] ?? null) && ($afterSummary['decision_focus'] ?? null) && $beforeSummary['decision_focus'] !== $afterSummary['decision_focus']) {
            $changes[] = [
                'type' => 'decision_focus_changed',
                'message' => 'Decision focus changed from '.$beforeSummary['decision_focus'].' to '.$afterSummary['decision_focus'].'.',
                'before' => $beforeSummary['decision_focus'],
                'after' => $afterSummary['decision_focus'],
            ];
        }

        return [
            'team_id' => $teamId,
            'days' => $this->days($days),
            'changes' => $changes,
            'summary_before' => $beforeSummary,
            'summary_after' => $afterSummary,
        ];
    }

    public function comparePlayerBenchmarkProfile(string $teamId, string $playerId, array $before = [], array $after = [], int $days = 365): array
    {
        $beforeMetrics = $this->metricMap($before['player_benchmark_profile']['metrics'] ?? []);
        $afterMetrics = $this->metricMap($after['player_benchmark_profile']['metrics'] ?? []);
        $playerName = $this->playerName($after) ?: $this->playerName($before) ?: 'Player';
        $changes = [];

        foreach ($afterMetrics as $metricKey => $metric) {
            $beforeMetric = $beforeMetrics[$metricKey] ?? null;
            if ($beforeMetric && is_numeric($beforeMetric['raw_value'] ?? null)) {
                continue;
            }

            if (! is_numeric($metric['raw_value'] ?? null)) {
                continue;
            }

            $changes[] = [
                'type' => 'new_trusted_metric',
                'player_id' => $playerId,
                'player_name' => $playerName,
                'metric_key' => $metricKey,
                'display_name' => $metric['display_name'] ?? $this->label($metricKey),
                'before' => $beforeMetric['raw_value'] ?? null,
                'after' => $metric['raw_value'],
                'message' => ($metric['display_name'] ?? $this->label($metricKey)).' is now available as trusted benchmark data for '.$playerName.'.',
            ];
        }

        foreach ($this->removedMissingMetrics($before, $after, $playerId) as $gap) {
            $changes[] = $gap;
        }

        return [
            'team_id' => $teamId,
            'player_id' => $playerId,
            'days' => $this->days($days),
            'changes' => $changes,
        ];
    }

    public function buildImprovementSummary(array $before, array $after): array
    {
        $beforeSummary = $this->stateSummaryFromState($before);
        $afterSummary = $this->stateSummaryFromState($after);

        return [
            'players_with_benchmark_data_before' => $beforeSummary['players_with_benchmark_data'] ?? null,
            'players_with_benchmark_data_after' => $afterSummary['players_with_benchmark_data'] ?? null,
            'benchmark_metric_count_before' => $beforeSummary['benchmark_metric_count'] ?? null,
            'benchmark_metric_count_after' => $afterSummary['benchmark_metric_count'] ?? null,
            'completion_percentage_before' => $beforeSummary['completion_percentage'] ?? null,
            'completion_percentage_after' => $afterSummary['completion_percentage'] ?? null,
            'benchmark_confidence_before' => $beforeSummary['benchmark_confidence'] ?? null,
            'benchmark_confidence_after' => $afterSummary['benchmark_confidence'] ?? null,
            'source_mix_before' => $beforeSummary['source_mix'] ?? [],
            'source_mix_after' => $afterSummary['source_mix'] ?? [],
            'decision_focus_before' => $beforeSummary['decision_focus'] ?? null,
            'decision_focus_after' => $afterSummary['decision_focus'] ?? null,
            'collection_priority_before' => $beforeSummary['collection_priority'] ?? null,
            'collection_priority_after' => $afterSummary['collection_priority'] ?? null,
        ];
    }

    private function stateSummaryFromState(array $state): array
    {
        if (is_array($state['summary'] ?? null)) {
            return $state['summary'];
        }

        return $this->stateSummary(
            is_array($state['team_benchmark_profile'] ?? null) ? $state['team_benchmark_profile'] : [],
            is_array($state['decision_brief'] ?? null) ? $state['decision_brief'] : [],
            is_array($state['collection_plan'] ?? null) ? $state['collection_plan'] : [],
        );
    }

    private function stateSummary(array $teamProfile, array $decisionBrief, array $collectionPlan): array
    {
        $evidence = is_array($teamProfile['evidence'] ?? null) ? $teamProfile['evidence'] : [];
        $playerCount = $this->intOrNull($teamProfile['player_count'] ?? null);
        $playersWithData = $this->intOrNull($evidence['players_with_benchmark_metrics'] ?? null);

        return [
            'player_count' => $playerCount,
            'players_with_benchmark_data' => $playersWithData,
            'players_without_benchmark_data' => $this->intOrNull($evidence['players_without_benchmark_metrics'] ?? null),
            'benchmark_metric_count' => $this->intOrNull($teamProfile['metric_count'] ?? null),
            'completion_percentage' => $playerCount && $playersWithData !== null
                ? round(($playersWithData / max(1, $playerCount)) * 100, 1)
                : null,
            'benchmark_confidence' => $teamProfile['benchmark_confidence'] ?? null,
            'source_mix' => is_array($teamProfile['source_mix'] ?? null) ? $teamProfile['source_mix'] : [],
            'decision_focus' => $this->decisionFocus($decisionBrief),
            'collection_priority' => $collectionPlan['priority_level'] ?? ($decisionBrief['data_collection_priority']['level'] ?? null),
            'missing_metric_count' => count($teamProfile['missing_metrics'] ?? []),
        ];
    }

    private function promotionChanges(array $options, array $after): array
    {
        $trustedPayload = is_array($options['trusted_payload'] ?? null) ? $options['trusted_payload'] : [];
        $values = is_array($trustedPayload['values'] ?? null) ? $trustedPayload['values'] : [];
        $playerId = $trustedPayload['player_id'] ?? $after['player_id'] ?? null;
        $playerName = $this->playerName($after) ?: 'Player';
        $changes = [];

        foreach ($values as $metricKey => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $display = $this->label((string) $metricKey);
            $changes[] = [
                'type' => 'new_trusted_metric',
                'player_id' => $playerId,
                'player_name' => $playerName,
                'metric_key' => (string) $metricKey,
                'display_name' => $display,
                'before' => null,
                'after' => $value,
                'message' => $display.' is now trusted for '.$playerName.'.',
            ];
        }

        return $changes;
    }

    private function removedMissingMetrics(array $before, array $after, string $playerId): array
    {
        $beforeKeys = $this->missingMetricKeys($before['team_benchmark_profile']['missing_metrics'] ?? [], $playerId);
        if (empty($beforeKeys)) {
            return [];
        }

        $afterKeys = $this->missingMetricKeys($after['team_benchmark_profile']['missing_metrics'] ?? [], $playerId);
        $removed = array_values(array_diff($beforeKeys, $afterKeys));

        return array_map(fn (string $metricKey): array => [
            'type' => 'collection_gap_removed',
            'player_id' => $playerId,
            'metric_key' => $metricKey,
            'display_name' => $this->label($metricKey),
            'before' => 'missing',
            'after' => 'available',
            'message' => $this->label($metricKey).' is no longer missing for this player.',
        ], $removed);
    }

    private function missingMetricKeys(array $rows, string $playerId): array
    {
        $keys = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $players = [
                ...$this->arrayValue($row['players_missing'] ?? []),
                ...$this->arrayValue($row['players'] ?? []),
            ];
            if (empty($players)) {
                $keys[] = (string) ($row['metric_key'] ?? $row['display_name'] ?? '');
                continue;
            }

            foreach ($players as $player) {
                if (is_array($player) && (string) ($player['player_id'] ?? '') === $playerId) {
                    $keys[] = (string) ($row['metric_key'] ?? $row['display_name'] ?? '');
                }
            }
        }

        return array_values(array_unique(array_filter($keys)));
    }

    private function remainingGaps(array $after): array
    {
        return collect($after['team_benchmark_profile']['missing_metrics'] ?? [])
            ->filter(fn ($row): bool => is_array($row))
            ->map(fn (array $row): array => [
                'metric_key' => $row['metric_key'] ?? null,
                'display_name' => $row['display_name'] ?? $this->label((string) ($row['metric_key'] ?? 'Benchmark Baseline')),
                'category' => $row['category'] ?? null,
                'missing_count' => $row['missing_count'] ?? null,
                'players_missing' => array_slice($this->arrayValue($row['players_missing'] ?? $row['players'] ?? []), 0, 5),
            ])
            ->take(8)
            ->values()
            ->all();
    }

    private function nextRecommendedActions(array $after): array
    {
        $plan = is_array($after['collection_plan'] ?? null) ? $after['collection_plan'] : [];
        $actions = [];

        if (is_array($plan['next_best_action'] ?? null)) {
            $actions[] = [
                'title' => $plan['next_best_action']['title'] ?? 'Next Benchmark Collection',
                'priority' => $plan['next_best_action']['priority'] ?? $plan['priority_level'] ?? null,
                'why' => $plan['next_best_action']['why'] ?? null,
                'duration_minutes' => $plan['next_best_action']['duration_minutes'] ?? null,
            ];
        }

        foreach (array_slice($this->arrayValue($plan['collection_sessions'] ?? []), 0, 3) as $session) {
            if (! is_array($session)) {
                continue;
            }

            $actions[] = [
                'title' => $session['title'] ?? 'Benchmark Collection',
                'priority' => $session['priority'] ?? $plan['priority_level'] ?? null,
                'why' => $session['why'] ?? null,
                'duration_minutes' => $session['duration_minutes'] ?? null,
            ];
        }

        return array_values(array_filter($actions));
    }

    private function metricMap(array $metrics): array
    {
        $map = [];
        foreach ($metrics as $metric) {
            if (! is_array($metric)) {
                continue;
            }

            $key = (string) ($metric['metric_key'] ?? '');
            if ($key !== '') {
                $map[$key] = $metric;
            }
        }

        return $map;
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
                $change['player_id'] ?? '',
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

    private function decisionFocus(array $decisionBrief): ?string
    {
        $focus = $decisionBrief['primary_focus'] ?? null;
        if (is_array($focus)) {
            return $focus['title'] ?? $focus['focus'] ?? $focus['name'] ?? null;
        }

        return is_string($focus) && trim($focus) !== '' ? $focus : null;
    }

    private function playerName(array $state): ?string
    {
        $summaryPlayer = $state['player_snapshot']['summary']['player'] ?? [];
        $contextPlayer = $state['player_snapshot']['player_context'] ?? [];

        foreach ([
            $summaryPlayer['name'] ?? null,
            $contextPlayer['name'] ?? null,
            trim((string) (($contextPlayer['first_name'] ?? '').' '.($contextPlayer['last_name'] ?? ''))),
        ] as $name) {
            $name = trim((string) ($name ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        return null;
    }

    private function confidenceRank(?string $confidence): int
    {
        return [
            'unknown' => 0,
            'low' => 1,
            'medium' => 2,
            'high' => 3,
        ][strtolower((string) ($confidence ?? 'unknown'))] ?? 0;
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function fmt(mixed $value): string
    {
        return is_numeric($value) ? number_format((float) $value, 1) : '-';
    }

    private function label(?string $value): string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? ucwords(str_replace('_', ' ', $value)) : 'Needs Data';
    }

    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    private function days(mixed $days): int
    {
        return max(7, min(365, (int) $days));
    }
}
