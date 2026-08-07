<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use App\Models\BenchmarkCollectionTask;
use Illuminate\Support\Facades\Cache;
use Throwable;

class BenchmarkRefreshService
{
    public function __construct(
        private readonly PlayerIntelligenceService $playerIntelligenceService,
        private readonly TeamIntelligenceService $teamIntelligenceService,
        private readonly TeamBenchmarkProfileService $teamBenchmarkProfileService,
        private readonly BenchmarkCollectionPlanner $benchmarkCollectionPlanner,
        private readonly DecisionEngine $decisionEngine,
        private readonly CoachActionReRankingService $coachActionReRankingService,
        private readonly PracticePlanUpdateSuggestionService $practicePlanUpdateSuggestionService,
    ) {
    }

    public function refreshAfterTaskCompletion(string $taskId, array $options = []): array
    {
        $days = $this->days($options['days'] ?? 365);

        try {
            $task = BenchmarkCollectionTask::query()->find($taskId);
            if (! $task) {
                return $this->skipped($taskId, null, null, [
                    'Benchmark task was not found.',
                ]);
            }

            $teamId = $this->nullableString($task->team_id);
            $playerId = $this->nullableString($task->assigned_to_player_id);

            if (! $teamId || ! $playerId) {
                return $this->skipped($taskId, $teamId, $playerId, [
                    'Benchmark task is missing a team_id or player_id, so player benchmark refresh was skipped.',
                ]);
            }

            $allowPreview = (bool) ($options['allow_preview'] ?? false);
            if ($task->status !== BenchmarkCollectionTask::STATUS_COMPLETED && ! $allowPreview) {
                return $this->skipped($taskId, $teamId, $playerId, [
                    'Benchmark task is not completed yet.',
                ], $task);
            }

            if (! $allowPreview && $task->review_status === BenchmarkCollectionTask::REVIEW_PENDING) {
                return $this->skipped($taskId, $teamId, $playerId, [
                    'Task is pending coach review. Benchmark refresh will use approved data after review.',
                ], $task);
            }

            if (! $allowPreview && in_array($task->review_status, [
                BenchmarkCollectionTask::REVIEW_REJECTED,
                BenchmarkCollectionTask::REVIEW_CORRECTION_REQUESTED,
            ], true)) {
                return $this->skipped($taskId, $teamId, $playerId, [
                    'Benchmark task is not approved for refresh.',
                ], $task);
            }

            $this->clearRelevantCaches($teamId, $playerId, $days);

            $playerRefresh = $this->refreshPlayerBenchmarks($teamId, $playerId, $days);
            $teamRefresh = $this->refreshTeamBenchmarks($teamId, $days);
            $playerProfile = $playerRefresh['player_benchmark_profile'] ?? [];
            $teamProfile = $teamRefresh['team_benchmark_profile'] ?? [];
            $decisionBrief = $teamRefresh['decision_brief'] ?? [];
            $collectionPlan = $teamRefresh['collection_plan'] ?? [];
            $actionRerank = $this->safeActionRerank($teamId, $decisionBrief, $collectionPlan, $days);
            $practicePlanUpdateSuggestions = $this->safePracticePlanUpdateSuggestions($teamId, $days, $actionRerank['updated_practice_plan'] ?? []);
            $warnings = array_values(array_filter([
                ...($playerRefresh['warnings'] ?? []),
                ...($teamRefresh['warnings'] ?? []),
                ...($actionRerank['warnings'] ?? []),
                ...($practicePlanUpdateSuggestions['warnings'] ?? []),
            ]));

            return [
                'task_id' => $taskId,
                'team_id' => $teamId,
                'player_id' => $playerId,
                'refreshed_at' => now()->toIso8601String(),
                'refresh_status' => empty($warnings) ? 'completed' : 'partial',
                'player_benchmark_profile' => $playerProfile,
                'team_benchmark_profile' => $teamProfile,
                'data_quality_report' => $this->dataQualityReport($teamProfile),
                'decision_brief' => $decisionBrief,
                'collection_plan' => $collectionPlan,
                'coach_action_practice_plan' => $actionRerank['updated_practice_plan'] ?? [],
                'action_rerank' => $actionRerank,
                'practice_plan_update_suggestions' => $practicePlanUpdateSuggestions,
                'changed_signals' => $this->changedSignals($task, $playerProfile, $teamProfile, $collectionPlan),
                'warnings' => $warnings,
                'evidence' => [
                    'days' => $days,
                    'task_type' => $task->task_type,
                    'task_status' => $task->status,
                    'review_status' => $task->review_status,
                    'player_metric_count' => count($playerProfile['metrics'] ?? []),
                    'team_metric_count' => $teamProfile['metric_count'] ?? null,
                    'team_benchmark_confidence' => $teamProfile['benchmark_confidence'] ?? null,
                    'collection_plan_priority' => $collectionPlan['priority_level'] ?? null,
                    'cache_cleared' => true,
                    'persistence' => 'live_rebuild_payload_only',
                ],
            ];
        } catch (Throwable $exception) {
            return [
                'task_id' => $taskId,
                'team_id' => null,
                'player_id' => null,
                'refreshed_at' => now()->toIso8601String(),
                'refresh_status' => 'failed',
                'player_benchmark_profile' => [],
                'team_benchmark_profile' => [],
                'data_quality_report' => [],
                'decision_brief' => [],
                'collection_plan' => [],
                'coach_action_practice_plan' => [],
                'action_rerank' => [],
                'practice_plan_update_suggestions' => [],
                'changed_signals' => [],
                'warnings' => [$exception->getMessage()],
                'evidence' => [
                    'exception' => class_basename($exception),
                    'persistence' => 'live_rebuild_payload_only',
                ],
            ];
        }
    }

    public function refreshPlayerBenchmarks(string $teamId, string $playerId, int $days = 365): array
    {
        $days = $this->days($days);
        $warnings = [];

        $this->clearRelevantCaches($teamId, $playerId, $days);

        try {
            $snapshot = $this->playerIntelligenceService->build($teamId, $playerId, $days);
        } catch (Throwable $exception) {
            return [
                'team_id' => $teamId,
                'player_id' => $playerId,
                'refreshed_at' => now()->toIso8601String(),
                'refresh_status' => 'failed',
                'player_benchmark_profile' => [],
                'warnings' => [$exception->getMessage()],
                'evidence' => [
                    'days' => $days,
                    'exception' => class_basename($exception),
                ],
            ];
        }

        return [
            'team_id' => $teamId,
            'player_id' => $playerId,
            'refreshed_at' => now()->toIso8601String(),
            'refresh_status' => 'completed',
            'player_benchmark_profile' => $snapshot['benchmark_profile'] ?? [],
            'player_snapshot' => $snapshot,
            'warnings' => $warnings,
            'evidence' => [
                'days' => $days,
                'metric_count' => count($snapshot['benchmark_profile']['metrics'] ?? []),
                'benchmark_confidence' => $snapshot['benchmark_profile']['benchmark_confidence'] ?? null,
                'persistence' => 'live_rebuild_payload_only',
            ],
        ];
    }

    public function refreshTeamBenchmarks(string $teamId, int $days = 365): array
    {
        $days = $this->days($days);
        $warnings = [];

        $this->clearRelevantCaches($teamId, null, $days);

        try {
            $teamSnapshot = $this->teamIntelligenceService->build($teamId, $days);
            $teamProfile = is_array($teamSnapshot['benchmark_profile'] ?? null) ? $teamSnapshot['benchmark_profile'] : [];
        } catch (Throwable $exception) {
            $teamSnapshot = [];
            $teamProfile = [];
            $warnings[] = 'Team intelligence unavailable: '.$exception->getMessage();
        }

        try {
            $decisionBrief = ! empty($teamSnapshot)
                ? $this->decisionEngine->buildTeamDecisionBriefFromSnapshot($teamId, $teamSnapshot, $days)
                : $this->decisionEngine->buildTeamDecisionBrief($teamId, $days);
        } catch (Throwable $exception) {
            $decisionBrief = [];
            $warnings[] = 'Decision brief unavailable: '.$exception->getMessage();
        }

        try {
            $collectionPlan = ! empty($teamProfile)
                ? $this->benchmarkCollectionPlanner->buildTeamCollectionPlanFromData($teamId, $days, $teamProfile, $decisionBrief)
                : $this->benchmarkCollectionPlanner->buildTeamCollectionPlan($teamId, $days);
        } catch (Throwable $exception) {
            $collectionPlan = [];
            $warnings[] = 'Benchmark collection plan unavailable: '.$exception->getMessage();
        }

        $actionRerank = $this->safeActionRerank($teamId, $decisionBrief, $collectionPlan, $days);
        $practicePlanUpdateSuggestions = $this->safePracticePlanUpdateSuggestions($teamId, $days, $actionRerank['updated_practice_plan'] ?? []);
        $warnings = array_values(array_filter([
            ...$warnings,
            ...($actionRerank['warnings'] ?? []),
            ...($practicePlanUpdateSuggestions['warnings'] ?? []),
        ]));

        return [
            'team_id' => $teamId,
            'refreshed_at' => now()->toIso8601String(),
            'refresh_status' => empty($warnings) ? 'completed' : 'partial',
            'team_benchmark_profile' => $teamProfile,
            'data_quality_report' => $this->dataQualityReport($teamProfile),
            'decision_brief' => $decisionBrief,
            'collection_plan' => $collectionPlan,
            'coach_action_practice_plan' => $actionRerank['updated_practice_plan'] ?? [],
            'action_rerank' => $actionRerank,
            'practice_plan_update_suggestions' => $practicePlanUpdateSuggestions,
            'changed_signals' => $this->teamChangedSignals($teamProfile, $collectionPlan),
            'warnings' => $warnings,
            'evidence' => [
                'days' => $days,
                'metric_count' => $teamProfile['metric_count'] ?? null,
                'benchmark_confidence' => $teamProfile['benchmark_confidence'] ?? null,
                'collection_plan_priority' => $collectionPlan['priority_level'] ?? null,
                'cache_cleared' => true,
                'persistence' => 'live_rebuild_payload_only',
            ],
        ];
    }

    public function buildRefreshStatus(string $teamId, ?string $playerId = null, int $days = 365): array
    {
        return [
            'team_id' => $teamId,
            'player_id' => $playerId,
            'last_refreshed_at' => null,
            'status' => 'unknown',
            'reason' => 'Benchmark intelligence is calculated live from current data.',
            'evidence' => [
                'days' => $this->days($days),
                'persistence' => 'live_rebuild_payload_only',
                'snapshot_persistence' => false,
            ],
        ];
    }

    private function clearRelevantCaches(string $teamId, ?string $playerId = null, int $days = 365): void
    {
        foreach ([
            "player_cards_v3_{$teamId}",
            "player_dev_board_{$teamId}",
            "player_dev_board_v2_{$teamId}",
            "performance_overview_{$teamId}",
            "dashboard_graphics_{$teamId}",
            "roster_team_{$teamId}",
        ] as $key) {
            Cache::forget($key);
        }

        if (! $playerId) {
            return;
        }

        foreach (array_unique([7, 30, 60, 90, 180, 365, $this->days($days)]) as $window) {
            Cache::forget("dev_dashboard_{$teamId}_{$playerId}_{$window}");
            Cache::forget("dev_dashboard_v2_{$teamId}_{$playerId}_{$window}");
            Cache::forget("dev_dashboard_v3_{$teamId}_{$playerId}_{$window}");
            Cache::forget("dev_dashboard_v3_all_{$playerId}_{$window}");
            Cache::forget("player_intelligence_v1_{$teamId}_{$playerId}_{$window}");
        }
    }

    private function dataQualityReport(array $teamProfile): array
    {
        $evidence = is_array($teamProfile['evidence'] ?? null) ? $teamProfile['evidence'] : [];

        return [
            'benchmark_confidence' => $teamProfile['benchmark_confidence'] ?? 'low',
            'player_count' => $teamProfile['player_count'] ?? 0,
            'metric_count' => $teamProfile['metric_count'] ?? 0,
            'players_with_benchmark_metrics' => $evidence['players_with_benchmark_metrics'] ?? null,
            'players_without_benchmark_metrics' => $evidence['players_without_benchmark_metrics'] ?? null,
            'missing_metric_count' => count($teamProfile['missing_metrics'] ?? []),
            'team_gap_count' => count($teamProfile['team_gaps'] ?? []),
        ];
    }

    private function safeActionRerank(string $teamId, array $decisionBrief, array $collectionPlan, int $days): array
    {
        try {
            return $this->coachActionReRankingService->rerankAfterBenchmarkRefresh($teamId, [], [
                'decision_brief' => $decisionBrief,
                'collection_plan' => $collectionPlan,
            ], [
                'days' => $days,
            ]);
        } catch (Throwable $exception) {
            return [
                'generated_at' => now()->toIso8601String(),
                'team_id' => $teamId,
                'rerank_status' => 'failed',
                'top_actions_after' => [],
                'updated_practice_plan' => [],
                'coach_summary' => 'Coach action ranking will update on next dashboard load.',
                'warnings' => [$exception->getMessage()],
            ];
        }
    }

    private function safePracticePlanUpdateSuggestions(string $teamId, int $days, array $latestSuggestedPlan = []): array
    {
        try {
            return $this->practicePlanUpdateSuggestionService->suggestUpdatesForTeam($teamId, $days, [
                'latest_suggested_plan' => $latestSuggestedPlan,
            ]);
        } catch (Throwable $exception) {
            return [
                'generated_at' => now()->toIso8601String(),
                'team_id' => $teamId,
                'daily_plan_id' => null,
                'suggestion_status' => 'failed',
                'current_plan' => [],
                'latest_suggested_plan' => [],
                'focus_change' => [
                    'changed' => false,
                    'current_focus' => null,
                    'latest_focus' => null,
                    'reason' => 'Practice plan update suggestions could not be generated.',
                ],
                'suggestions' => [],
                'summary' => 'Practice plan update suggestions could not be generated.',
                'requires_coach_review' => true,
                'warnings' => [$exception->getMessage()],
                'evidence' => [
                    'days' => $days,
                    'exception' => class_basename($exception),
                ],
            ];
        }
    }

    private function changedSignals(BenchmarkCollectionTask $task, array $playerProfile, array $teamProfile, array $collectionPlan): array
    {
        $signals = [];
        $baseline = $this->baselineLabel((string) $task->task_type);

        if ($baseline) {
            $signals[] = [
                'type' => 'data_quality',
                'message' => $baseline.' was marked collected for this player.',
                'before' => null,
                'after' => 'available',
            ];
        }

        $approvedPayload = is_array($task->approved_payload ?? null) ? $task->approved_payload : [];
        if (($approvedPayload['source'] ?? null) === 'daily_plan_progress') {
            foreach ($this->approvedMetricLabels($approvedPayload) as $label) {
                $signals[] = [
                    'type' => 'trusted_daily_plan_metric',
                    'message' => $label.' was approved from a daily plan submission.',
                    'before' => 'pending_review',
                    'after' => 'trusted',
                ];
            }

            if (! empty($approvedPayload['daily_plan_item_title'] ?? null)) {
                $signals[] = [
                    'type' => 'daily_plan_item',
                    'message' => 'Trusted values came from '.$approvedPayload['daily_plan_item_title'].'.',
                    'before' => null,
                    'after' => 'approved',
                ];
            }
        }

        if (count($playerProfile['metrics'] ?? []) > 0) {
            $signals[] = [
                'type' => 'player_benchmark_profile',
                'message' => 'Player benchmark profile was rebuilt from current data.',
                'before' => null,
                'after' => count($playerProfile['metrics'] ?? []).' metric(s)',
            ];
        }

        return [
            ...$signals,
            ...$this->teamChangedSignals($teamProfile, $collectionPlan),
        ];
    }

    private function approvedMetricLabels(array $payload): array
    {
        $values = [];
        foreach (['metric_values', 'actuals', 'results', 'submitted_values', 'values'] as $key) {
            if (is_array($payload[$key] ?? null)) {
                $values = $payload[$key];
                break;
            }
        }

        return collect(array_keys($values))
            ->map(fn ($key): string => ucwords(str_replace('_', ' ', (string) $key)))
            ->filter()
            ->take(6)
            ->values()
            ->all();
    }

    private function teamChangedSignals(array $teamProfile, array $collectionPlan): array
    {
        $signals = [];

        if (($teamProfile['metric_count'] ?? 0) > 0) {
            $signals[] = [
                'type' => 'team_benchmark_profile',
                'message' => 'Team benchmark profile was rebuilt from current data.',
                'before' => null,
                'after' => ($teamProfile['metric_count'] ?? 0).' metric(s)',
            ];
        }

        if (($teamProfile['benchmark_confidence'] ?? null) !== null) {
            $signals[] = [
                'type' => 'benchmark_confidence',
                'message' => 'Team benchmark confidence is now '.($teamProfile['benchmark_confidence'] ?? 'unknown').'.',
                'before' => null,
                'after' => $teamProfile['benchmark_confidence'] ?? null,
            ];
        }

        if (($collectionPlan['priority_level'] ?? null) !== null) {
            $signals[] = [
                'type' => 'collection_plan',
                'message' => 'Benchmark collection plan was refreshed.',
                'before' => null,
                'after' => $collectionPlan['priority_level'] ?? null,
            ];
        }

        return $signals;
    }

    private function baselineLabel(string $taskType): ?string
    {
        return [
            'roster_cleanup' => 'Roster cleanup',
            'exit_velocity_baseline' => 'Exit velocity baseline',
            'bullpen_baseline' => 'Bullpen baseline',
            'long_toss_weighted_ball' => 'Long toss / weighted ball baseline',
            'strength_baseline' => 'Strength baseline',
            'athletic_testing' => 'Athletic testing baseline',
            'mobility_screen' => 'Mobility screen',
        ][$taskType] ?? null;
    }

    private function skipped(string $taskId, ?string $teamId, ?string $playerId, array $warnings, ?BenchmarkCollectionTask $task = null): array
    {
        return [
            'task_id' => $taskId,
            'team_id' => $teamId,
            'player_id' => $playerId,
            'refreshed_at' => now()->toIso8601String(),
            'refresh_status' => 'skipped',
            'player_benchmark_profile' => [],
            'team_benchmark_profile' => [],
            'data_quality_report' => [],
            'decision_brief' => [],
            'collection_plan' => [],
            'changed_signals' => [],
            'warnings' => $warnings,
            'evidence' => [
                'task_status' => $task?->status,
                'review_status' => $task?->review_status,
                'persistence' => 'live_rebuild_payload_only',
            ],
        ];
    }

    private function days(mixed $days): int
    {
        return max(7, min(365, (int) $days));
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}
