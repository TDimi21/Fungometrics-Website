<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use App\Models\BenchmarkCollectionTask;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PopulationMetricRepository
{
    private const SUPPORTED_METRICS = [
        'average_exit_velocity',
        'max_exit_velocity',
        'average_fastball_velocity',
        'max_fastball_velocity',
        'strike_percentage',
        'long_toss_max_distance',
        'weighted_ball_5oz_velocity',
        'bench_press',
        'squat',
        'deadlift',
        'pull_ups',
        'pushups',
        'forty_yard_dash',
        'sixty_yard_dash',
        'broad_jump',
        'vertical_jump',
        'mobility_score',
        'shoulder_mobility_score',
        'hip_mobility_score',
        't_spine_mobility_score',
    ];

    public function __construct(
        private readonly PopulationValueGuardrail $guardrail,
        private readonly BenchmarkLibrary $benchmarkLibrary,
    ) {}

    public function valuesForMetric(string $metricKey, array $context = [], int $days = 365): array
    {
        return $this->auditForMetric($metricKey, $context, $days)['values'] ?? [];
    }

    public function auditForMetric(string $metricKey, array $context = [], int $days = 365): array
    {
        try {
            $metricKey = BenchmarkDefinitions::normalizeMetricKey($metricKey);
            $days = max(1, $days);
            $stats = $this->emptyAuditStats();
            $includeTrustedTasks = $this->includeTrustedTasks($context);
            $tableRows = $this->populationRowsForMetric($metricKey, $context, $days, $stats);
            $tableRows = $this->guardRows($tableRows, $metricKey, $stats, 'aggregate');
            $stats['table_values_count'] = $tableRows->count();
            if ($includeTrustedTasks) {
                $this->recordIneligibleTrustedTaskPayloads($metricKey, $context, $days, $stats);
            }
            $trustedRows = $includeTrustedTasks
                ? $this->trustedTaskPayloadRowsForMetric($metricKey, $context, $days, $stats)
                : collect();
            $rows = $this->mergeTableAndTrustedRows($tableRows, $trustedRows, $stats);
            $guardrailFilteredCount = $rows->count();
            $guardrailFilteredSample = $this->sampleRowValues($rows);
            $rows = $this->filterRowsByContextWithAudit($rows, $context, $stats);
            $values = $rows
                ->map(fn (array $row) => $this->numberOrNull($row['value'] ?? null, $metricKey))
                ->filter(fn (?float $value) => $value !== null)
                ->values()
                ->all();
            $trustedFinalCount = $rows
                ->filter(fn (array $row) => ($row['source'] ?? null) === 'trusted_task_payload')
                ->count();

            return [
                'metric_key' => $metricKey,
                'days' => $days,
                'trusted_tasks_included' => $includeTrustedTasks,
                'raw_count' => $stats['raw_values_found'],
                'raw_values_found' => $stats['raw_values_found'],
                'raw_values_included' => $stats['raw_values_included'],
                'raw_sample_before_guardrails' => $stats['raw_samples'],
                'raw_included_sample' => $stats['raw_included_samples'],
                'table_values_count' => $stats['table_values_count'],
                'trusted_task_values_found' => $stats['trusted_task_values_found'],
                'trusted_task_values_included_before_dedupe' => $stats['trusted_task_values_included_before_dedupe'],
                'trusted_task_values_count' => $trustedFinalCount,
                'trusted_task_values_included' => $trustedFinalCount,
                'trusted_task_values_excluded' => count($stats['trusted_task_excluded']),
                'trusted_task_values_status_excluded' => count($stats['trusted_task_status_excluded']),
                'trusted_task_excluded_reasons' => $this->excludedReasonCounts($stats['trusted_task_excluded']),
                'trusted_task_status_excluded_reasons' => $this->excludedReasonCounts($stats['trusted_task_status_excluded']),
                'trusted_task_excluded_samples' => array_slice($stats['trusted_task_excluded'], 0, 10),
                'trusted_task_status_excluded_samples' => array_slice($stats['trusted_task_status_excluded'], 0, 10),
                'trusted_task_sample' => $stats['trusted_task_samples'],
                'deduped_count' => $stats['trusted_task_deduped_count'],
                'trusted_task_deduped_count' => $stats['trusted_task_deduped_count'],
                'included_count' => $guardrailFilteredCount,
                'guardrail_filtered_count' => $guardrailFilteredCount,
                'guardrail_filtered_sample' => $guardrailFilteredSample,
                'bucket_filter_applied' => $stats['bucket_filter_applied'],
                'bucket_key' => $this->bucketKeyForContext($context),
                'bucket_context_before_count' => $stats['bucket_context_before_count'],
                'bucket_context_after_count' => $stats['bucket_context_after_count'],
                'bucket_filtered_count' => $stats['bucket_context_removed_count'],
                'bucket_context_removed_count' => $stats['bucket_context_removed_count'],
                'bucket_context_removed_samples' => $stats['bucket_context_removed_samples'],
                'values_included' => count($values),
                'final_values_count' => count($values),
                'final_population_values_count' => count($values),
                'values_excluded' => count($stats['excluded']),
                'excluded_count' => count($stats['excluded']),
                'excluded_reasons' => $this->excludedReasonCounts($stats['excluded']),
                'excluded_reason_counts' => $this->excludedReasonCounts($stats['excluded']),
                'excluded_samples' => array_slice($stats['excluded'], 0, 10),
                'values' => $values,
                'final_values' => $values,
            ];
        } catch (\Throwable) {
            return [
                'metric_key' => BenchmarkDefinitions::normalizeMetricKey($metricKey),
                'days' => max(1, $days),
                'raw_count' => 0,
                'raw_values_found' => 0,
                'raw_values_included' => 0,
                'raw_sample_before_guardrails' => [],
                'raw_included_sample' => [],
                'included_count' => 0,
                'guardrail_filtered_count' => 0,
                'guardrail_filtered_sample' => [],
                'bucket_filter_applied' => false,
                'bucket_key' => $this->bucketKeyForContext($context),
                'bucket_context_before_count' => 0,
                'bucket_context_after_count' => 0,
                'bucket_filtered_count' => 0,
                'bucket_context_removed_count' => 0,
                'bucket_context_removed_samples' => [],
                'values_included' => 0,
                'final_values_count' => 0,
                'values_excluded' => 0,
                'excluded_count' => 0,
                'excluded_reasons' => [],
                'excluded_reason_counts' => [],
                'excluded_samples' => [],
                'values' => [],
                'final_values' => [],
                'trusted_tasks_included' => $this->includeTrustedTasks($context),
                'table_values_count' => 0,
                'trusted_task_values_found' => 0,
                'trusted_task_values_included_before_dedupe' => 0,
                'trusted_task_values_count' => 0,
                'trusted_task_values_included' => 0,
                'trusted_task_values_excluded' => 0,
                'trusted_task_values_status_excluded' => 0,
                'trusted_task_excluded_reasons' => [],
                'trusted_task_status_excluded_reasons' => [],
                'trusted_task_excluded_samples' => [],
                'trusted_task_status_excluded_samples' => [],
                'trusted_task_sample' => [],
                'deduped_count' => 0,
                'trusted_task_deduped_count' => 0,
                'final_population_values_count' => 0,
            ];
        }
    }

    public function countForMetric(string $metricKey, array $context = [], int $days = 365): int
    {
        return count($this->valuesForMetric($metricKey, $context, $days));
    }

    public function availableMetrics(array $context = [], int $days = 365): array
    {
        $metrics = [];

        foreach (self::SUPPORTED_METRICS as $metricKey) {
            $count = $this->countForMetric($metricKey, $context, $days);
            if ($count > 0) {
                $metrics[$metricKey] = $count;
            }
        }

        return $metrics;
    }

    public function trustedTaskPayloadValuesForMetric(string $metricKey, array $context = [], int $days = 365): array
    {
        return $this->trustedTaskPayloadAuditForMetric($metricKey, $context, $days)['values'] ?? [];
    }

    public function trustedTaskPayloadAuditForMetric(string $metricKey, array $context = [], int $days = 365): array
    {
        $stats = $this->emptyAuditStats();
        $rows = $this->trustedTaskPayloadRowsForMetric($metricKey, $context, max(1, $days), $stats);
        $rows = $this->filterRowsByContext($rows, $context);
        $values = $rows
            ->map(fn (array $row) => $this->numberOrNull($row['value'] ?? null, $metricKey))
            ->filter(fn (?float $value) => $value !== null)
            ->values()
            ->all();

        return [
            'metric_key' => BenchmarkDefinitions::normalizeMetricKey($metricKey),
            'days' => max(1, $days),
            'trusted_task_values_found' => $stats['trusted_task_values_found'],
            'trusted_task_values_included' => $rows->count(),
            'trusted_task_values_excluded' => count($stats['trusted_task_excluded']),
            'trusted_task_excluded_reasons' => $this->excludedReasonCounts($stats['trusted_task_excluded']),
            'trusted_task_excluded_samples' => array_slice($stats['trusted_task_excluded'], 0, 10),
            'trusted_task_sample' => $stats['trusted_task_samples'],
            'values' => $values,
        ];
    }

    public function supportedMetricKeys(): array
    {
        return self::SUPPORTED_METRICS;
    }

    private function trustedMetricValue(BenchmarkCollectionTask $task, string $metricKey): mixed
    {
        $metricKey = BenchmarkDefinitions::normalizeMetricKey($metricKey);
        $payloadPromotion = ($task->payload ?? [])['promotion'] ?? [];
        $trustedPayload = is_array($task->promotion_result ?? null)
            ? ($task->promotion_result['trusted_payload'] ?? [])
            : [];
        if (empty($trustedPayload) && is_array($payloadPromotion)) {
            $trustedPayload = $payloadPromotion['trusted_payload'] ?? [];
        }

        foreach ([
            $trustedPayload['values'] ?? null,
            $trustedPayload ?? null,
            $task->approved_payload['submitted_values'] ?? null,
            $task->approved_payload['values'] ?? null,
            $task->approved_payload['payload']['values'] ?? null,
            $task->approved_payload['payload']['submitted_values'] ?? null,
            $task->submitted_payload['submitted_values'] ?? null,
            $task->submitted_payload['values'] ?? null,
            $task->submitted_payload['payload']['values'] ?? null,
            $task->approved_payload ?? null,
        ] as $values) {
            if (! is_array($values)) {
                continue;
            }

            foreach ($this->metricAliases($metricKey) as $alias) {
                if (array_key_exists($alias, $values)) {
                    return $values[$alias];
                }
            }
        }

        return null;
    }

    private function includeTrustedTasks(array $context): bool
    {
        if (($context['exclude_trusted_tasks'] ?? false) === true) {
            return false;
        }

        if (array_key_exists('include_trusted_tasks', $context)) {
            return (bool) $context['include_trusted_tasks'];
        }

        return true;
    }

    private function trustedTaskPayloadRowsForMetric(string $metricKey, array $context, int $days, array &$stats): Collection
    {
        $metricKey = BenchmarkDefinitions::normalizeMetricKey($metricKey);
        if (! in_array($metricKey, self::SUPPORTED_METRICS, true)) {
            return collect();
        }

        try {
            $query = $this->trustedTaskQuery($context, $days);
            if ($query === null) {
                return collect();
            }

            $rows = $query->get()
                ->map(function (BenchmarkCollectionTask $task) use ($metricKey, &$stats) {
                    $value = $this->trustedMetricValue($task, $metricKey);
                    if ($value === null) {
                        return null;
                    }

                    $stats['trusted_task_values_found']++;
                    $validation = $this->guardrail->validate($metricKey, $value);
                    if (($validation['included'] ?? false) !== true) {
                        $this->recordTrustedExcluded($stats, $validation, $task, $metricKey);

                        return null;
                    }

                    $this->recordTrustedSample($stats, $task, $metricKey, $validation['value']);

                    return [
                        'user_id' => (string) ($task->assigned_to_player_id ?? ''),
                        'value' => $validation['value'],
                        'created_at' => $task->promoted_at ?? $task->reviewed_at ?? $task->updated_at ?? null,
                        'source' => 'trusted_task_payload',
                        'task_id' => (string) $task->id,
                        'task_type' => (string) ($task->task_type ?? ''),
                        'team_id' => $task->team_id ? (string) $task->team_id : null,
                    ];
                })
                ->filter()
                ->values();

            $stats['trusted_task_values_included_before_dedupe'] = $rows->count();

            return $this->aggregateTrustedRows($rows, $this->aggregateForMetric($metricKey));
        } catch (\Throwable) {
            return collect();
        }
    }

    private function trustedTaskQuery(array $context, int $days)
    {
        if (! Schema::hasTable('benchmark_collection_tasks')) {
            return null;
        }

        foreach (['review_status', 'status', 'promotion_status', 'promotion_mode', 'assigned_to_player_id'] as $column) {
            if (! Schema::hasColumn('benchmark_collection_tasks', $column)) {
                return null;
            }
        }

        $query = BenchmarkCollectionTask::query()
            ->where('status', BenchmarkCollectionTask::STATUS_COMPLETED)
            ->where('review_status', BenchmarkCollectionTask::REVIEW_APPROVED)
            ->where(function ($scope): void {
                $scope->where('promotion_status', BenchmarkCollectionTask::PROMOTION_PROMOTED)
                    ->orWhere('promotion_status', BenchmarkCollectionTask::PROMOTION_PARTIAL)
                    ->orWhere('promotion_mode', BenchmarkCollectionTask::MODE_TRUSTED_PAYLOAD_ONLY);
            })
            ->whereNotNull('assigned_to_player_id');

        if (Schema::hasColumn('benchmark_collection_tasks', 'promoted_at') || Schema::hasColumn('benchmark_collection_tasks', 'reviewed_at')) {
            $query->where(function ($scope) use ($days): void {
                if (Schema::hasColumn('benchmark_collection_tasks', 'promoted_at')) {
                    $scope->where('promoted_at', '>=', now()->subDays($days));
                }

                if (Schema::hasColumn('benchmark_collection_tasks', 'reviewed_at')) {
                    $scope->orWhere(function ($fallback) use ($days): void {
                        if (Schema::hasColumn('benchmark_collection_tasks', 'promoted_at')) {
                            $fallback->whereNull('promoted_at');
                        }
                        $fallback->where('reviewed_at', '>=', now()->subDays($days));
                    });
                }
            });
        }

        if (! empty($context['team_id'] ?? $context['teamId'] ?? null) && Schema::hasColumn('benchmark_collection_tasks', 'team_id')) {
            $query->where('team_id', (string) ($context['team_id'] ?? $context['teamId']));
        }

        if (! empty($context['player_id'] ?? $context['playerId'] ?? null)) {
            $query->where('assigned_to_player_id', (string) ($context['player_id'] ?? $context['playerId']));
        }

        return $query;
    }

    private function recordIneligibleTrustedTaskPayloads(string $metricKey, array $context, int $days, array &$stats): void
    {
        if (! Schema::hasTable('benchmark_collection_tasks')) {
            return;
        }

        foreach (['review_status', 'status', 'promotion_status', 'promotion_mode', 'assigned_to_player_id'] as $column) {
            if (! Schema::hasColumn('benchmark_collection_tasks', $column)) {
                return;
            }
        }

        try {
            $query = BenchmarkCollectionTask::query()
                ->whereNotNull('assigned_to_player_id')
                ->where(function ($scope): void {
                    $scope->where('status', '!=', BenchmarkCollectionTask::STATUS_COMPLETED)
                        ->orWhere('review_status', '!=', BenchmarkCollectionTask::REVIEW_APPROVED)
                        ->orWhere(function ($promotion): void {
                            $promotion->where(function ($status): void {
                                $status->whereNotIn('promotion_status', [
                                    BenchmarkCollectionTask::PROMOTION_PROMOTED,
                                    BenchmarkCollectionTask::PROMOTION_PARTIAL,
                                ])->orWhereNull('promotion_status');
                            })->where(function ($mode): void {
                                $mode->where('promotion_mode', '!=', BenchmarkCollectionTask::MODE_TRUSTED_PAYLOAD_ONLY)
                                    ->orWhereNull('promotion_mode');
                            });
                        });
                });

            if (Schema::hasColumn('benchmark_collection_tasks', 'created_at')) {
                $query->where('created_at', '>=', now()->subDays($days));
            }

            if (! empty($context['team_id'] ?? $context['teamId'] ?? null) && Schema::hasColumn('benchmark_collection_tasks', 'team_id')) {
                $query->where('team_id', (string) ($context['team_id'] ?? $context['teamId']));
            }

            if (! empty($context['player_id'] ?? $context['playerId'] ?? null)) {
                $query->where('assigned_to_player_id', (string) ($context['player_id'] ?? $context['playerId']));
            }

            $query->get()->each(function (BenchmarkCollectionTask $task) use ($metricKey, &$stats): void {
                $value = $this->trustedMetricValue($task, $metricKey);
                if ($value === null) {
                    return;
                }

                $this->recordTrustedStatusExcluded($stats, $task, $metricKey, $value);
            });
        } catch (\Throwable) {
            return;
        }
    }

    private function mergeTableAndTrustedRows(Collection $tableRows, Collection $trustedRows, array &$stats): Collection
    {
        if ($trustedRows->isEmpty()) {
            return $tableRows->values();
        }

        $tableUserIds = $tableRows
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
        $seenTrustedUsers = [];
        $keptTrusted = collect();

        foreach ($trustedRows as $row) {
            $userId = (string) ($row['user_id'] ?? '');
            if ($userId === '') {
                $stats['trusted_task_deduped_count']++;
                continue;
            }

            if (in_array($userId, $tableUserIds, true) || in_array($userId, $seenTrustedUsers, true)) {
                $stats['trusted_task_deduped_count']++;
                continue;
            }

            $seenTrustedUsers[] = $userId;
            $keptTrusted->push($row);
        }

        return $tableRows->merge($keptTrusted)->values();
    }

    private function aggregateTrustedRows(Collection $rows, string $aggregate): Collection
    {
        return $rows
            ->groupBy('user_id')
            ->map(function (Collection $playerRows) use ($aggregate) {
                $values = $playerRows
                    ->pluck('value')
                    ->map(fn ($value) => is_numeric($value) ? (float) $value : null)
                    ->filter(fn ($value) => $value !== null);

                if ($values->isEmpty()) {
                    $value = null;
                } else {
                    $value = match ($aggregate) {
                        'avg' => round((float) $values->avg(), 1),
                        'min' => round((float) $values->min(), 2),
                        'latest' => $this->latestValue($playerRows),
                        default => round((float) $values->max(), 1),
                    };
                }

                return [
                    'user_id' => $playerRows->first()['user_id'] ?? null,
                    'value' => $value,
                    'source' => 'trusted_task_payload',
                    'source_count' => $playerRows->count(),
                    'task_ids' => $playerRows->pluck('task_id')->filter()->unique()->values()->all(),
                ];
            })
            ->values();
    }

    private function aggregateForMetric(string $metricKey): string
    {
        return match (BenchmarkDefinitions::normalizeMetricKey($metricKey)) {
            'average_exit_velocity',
            'average_fastball_velocity',
            'strike_percentage' => 'avg',
            'forty_yard_dash',
            'sixty_yard_dash' => 'min',
            'mobility_score',
            'shoulder_mobility_score',
            'hip_mobility_score',
            't_spine_mobility_score' => 'latest',
            default => 'max',
        };
    }

    private function metricAliases(string $metricKey): array
    {
        $metricKey = BenchmarkDefinitions::normalizeMetricKey($metricKey);
        $aliases = match ($metricKey) {
            'average_exit_velocity' => ['average_exit_velocity', 'avg_exit_velocity', 'avg_ev', 'exit_velocity_avg', 'batting_avg_ev', 'cage_avg_ev'],
            'max_exit_velocity' => ['max_exit_velocity', 'max_ev', 'exit_velocity_max'],
            'average_fastball_velocity' => ['average_fastball_velocity', 'avg_fastball_velocity', 'avg_fastball', 'avg_pitch_velocity', 'bullpen_avg_velocity'],
            'max_fastball_velocity' => ['max_fastball_velocity', 'max_fastball', 'max_pitch_velocity', 'bullpen_max_velocity'],
            'strike_percentage' => ['strike_percentage', 'strike_pct'],
            'forty_yard_dash' => ['forty_yard_dash', 'forty', '40_yard_dash', 'forty_yard_sec', 'yd_40_dash'],
            'sixty_yard_dash' => ['sixty_yard_dash', 'sixty', '60_yard_dash', 'sixty_yard_sec', 'yd_60_dash'],
            'pushups' => ['pushups', 'push_ups'],
            'deadlift' => ['deadlift', 'dead_lift'],
            'squat' => ['squat', 'back_squat', 'front_squat'],
            default => [$metricKey],
        };

        return array_values(array_unique(array_merge([$metricKey], $aliases)));
    }

    private function recordTrustedSample(array &$stats, BenchmarkCollectionTask $task, string $metricKey, mixed $value): void
    {
        if (count($stats['trusted_task_samples']) >= 10) {
            return;
        }

        $stats['trusted_task_samples'][] = [
            'task_id' => (string) $task->id,
            'task_type' => $task->task_type,
            'team_id' => $task->team_id,
            'user_id' => $task->assigned_to_player_id,
            'metric_key' => $metricKey,
            'value' => $value,
        ];
    }

    private function recordTrustedExcluded(array &$stats, array $validation, BenchmarkCollectionTask $task, string $metricKey): void
    {
        $stats['trusted_task_excluded'][] = [
            'metric_key' => $validation['metric_key'] ?? $metricKey,
            'reason' => $validation['reason'] ?? 'invalid_value',
            'raw_value' => $validation['raw_value'] ?? null,
            'parsed_value' => $validation['value'] ?? null,
            'valid_range' => $validation['range'] ?? null,
            'table' => 'benchmark_collection_tasks',
            'column' => 'trusted_payload',
            'user_id' => $task->assigned_to_player_id ? (string) $task->assigned_to_player_id : null,
            'task_id' => (string) $task->id,
            'task_type' => $task->task_type,
            'stage' => 'trusted_task_payload',
        ];
    }

    private function recordTrustedStatusExcluded(array &$stats, BenchmarkCollectionTask $task, string $metricKey, mixed $value): void
    {
        $reason = match (true) {
            $task->status !== BenchmarkCollectionTask::STATUS_COMPLETED => 'task_not_completed',
            $task->review_status !== BenchmarkCollectionTask::REVIEW_APPROVED => 'task_not_approved',
            ! in_array($task->promotion_status, [
                BenchmarkCollectionTask::PROMOTION_PROMOTED,
                BenchmarkCollectionTask::PROMOTION_PARTIAL,
            ], true) && $task->promotion_mode !== BenchmarkCollectionTask::MODE_TRUSTED_PAYLOAD_ONLY => 'task_not_promoted',
            default => 'task_not_population_eligible',
        };

        $stats['trusted_task_status_excluded'][] = [
            'metric_key' => $metricKey,
            'reason' => $reason,
            'raw_value' => $value,
            'parsed_value' => $this->numberOrNull($value, $metricKey),
            'table' => 'benchmark_collection_tasks',
            'column' => 'submitted_or_approved_payload',
            'user_id' => $task->assigned_to_player_id ? (string) $task->assigned_to_player_id : null,
            'task_id' => (string) $task->id,
            'task_type' => $task->task_type,
            'stage' => 'trusted_task_status',
        ];
    }

    private function populationRowsForMetric(string $metricKey, array $context, int $days, array &$stats): Collection
    {
        return match ($metricKey) {
            'average_exit_velocity' => $this->exitVelocityRows($context, $days, 'avg', $metricKey, $stats),
            'max_exit_velocity' => $this->exitVelocityRows($context, $days, 'max', $metricKey, $stats),
            'average_fastball_velocity' => $this->aggregateRows($this->sourceRows('bullpen_practice_results', 'pitcher_id', 'miles_per_hour', $context, $days, $metricKey, $stats), 'avg'),
            'max_fastball_velocity' => $this->aggregateRows($this->sourceRows('bullpen_practice_results', 'pitcher_id', 'miles_per_hour', $context, $days, $metricKey, $stats), 'max'),
            'strike_percentage' => $this->strikePercentageRows($context, $days, $stats),
            'long_toss_max_distance' => $this->aggregateRows($this->sourceRows('long_toss_practices', 'user_id', 'distance', $context, $days, $metricKey, $stats), 'max'),
            'weighted_ball_5oz_velocity' => $this->aggregateRows($this->sourceRows('weight_ball_practices', 'user_id', 'velocity', $context, $days, $metricKey, $stats, ['weight' => 5]), 'max'),
            'bench_press' => $this->strengthRows($context, $days, [
                ['player_fitnesses', 'user_id', 'bench_press'],
                ['player_assessments', 'user_id', 'bench_lbs'],
            ], 'max', $metricKey, $stats),
            'squat' => $this->strengthRows($context, $days, [
                ['player_fitnesses', 'user_id', 'back_squat'],
                ['player_fitnesses', 'user_id', 'front_squat'],
                ['player_assessments', 'user_id', 'squat_lbs'],
            ], 'max', $metricKey, $stats),
            'deadlift' => $this->strengthRows($context, $days, [
                ['player_fitnesses', 'user_id', 'dead_lift'],
                ['player_assessments', 'user_id', 'deadlift_lbs'],
            ], 'max', $metricKey, $stats),
            'pull_ups' => $this->strengthRows($context, $days, [
                ['player_fitnesses', 'user_id', 'pull_ups'],
            ], 'max', $metricKey, $stats),
            'pushups' => $this->strengthRows($context, $days, [
                ['player_fitnesses', 'user_id', 'push_ups'],
            ], 'max', $metricKey, $stats),
            'forty_yard_dash' => $this->strengthRows($context, $days, [
                ['player_fitnesses', 'user_id', 'yd_40_dash'],
            ], 'min', $metricKey, $stats),
            'sixty_yard_dash' => $this->strengthRows($context, $days, [
                ['player_fitnesses', 'user_id', 'yd_60_dash'],
            ], 'min', $metricKey, $stats),
            'broad_jump' => $this->strengthRows($context, $days, [
                ['player_fitnesses', 'user_id', 'broad_jump'],
                ['player_assessments', 'user_id', 'broad_jump_in'],
            ], 'max', $metricKey, $stats),
            'vertical_jump' => $this->strengthRows($context, $days, [
                ['player_fitnesses', 'user_id', 'vertical_jump'],
                ['player_assessments', 'user_id', 'vertical_jump_in'],
            ], 'max', $metricKey, $stats),
            'mobility_score' => $this->strengthRows($context, $days, [
                ['player_fitnesses', 'user_id', 'mobility_score'],
                ['player_assessments', 'user_id', 'mobility_overall_score'],
            ], 'latest', $metricKey, $stats),
            'shoulder_mobility_score' => $this->strengthRows($context, $days, [
                ['player_assessments', 'user_id', 'shoulder_mobility'],
            ], 'latest', $metricKey, $stats),
            'hip_mobility_score' => $this->strengthRows($context, $days, [
                ['player_assessments', 'user_id', 'hip_mobility'],
            ], 'latest', $metricKey, $stats),
            't_spine_mobility_score' => $this->strengthRows($context, $days, [
                ['player_assessments', 'user_id', 'rotational_mobility'],
            ], 'latest', $metricKey, $stats),
            default => collect(),
        };
    }

    private function exitVelocityRows(array $context, int $days, string $aggregate, string $metricKey, array &$stats): Collection
    {
        $rows = collect()
            ->merge($this->sourceRows('exit_velocity_practices', 'user_id', 'velocity', $context, $days, $metricKey, $stats))
            ->merge($this->sourceRows('batting_practice_results', 'batter_id', 'velocity', $context, $days, $metricKey, $stats))
            ->merge($this->sourceRows('cage_practice_results', 'user_id', 'launch_angle_velocity', $context, $days, $metricKey, $stats));

        return $this->aggregateRows($rows, $aggregate);
    }

    private function strengthRows(array $context, int $days, array $sources, string $aggregate, string $metricKey, array &$stats): Collection
    {
        $rows = collect();

        foreach ($sources as [$table, $userColumn, $valueColumn]) {
            $rows = $rows->merge($this->sourceRows($table, $userColumn, $valueColumn, $context, $days, $metricKey, $stats));
        }

        return $this->aggregateRows($rows, $aggregate);
    }

    private function strikePercentageRows(array $context, int $days, array &$stats): Collection
    {
        $rows = $this->sourceRows('bullpen_practice_results', 'pitcher_id', 'is_strike', $context, $days, 'strike_percentage', $stats);

        return $rows
            ->groupBy('user_id')
            ->map(function (Collection $playerRows) {
                $values = $playerRows
                    ->map(fn (array $row) => $this->numberOrNull($row['value'] ?? null, 'strike_percentage'))
                    ->filter(fn ($value) => $value !== null);

                return [
                    'user_id' => $playerRows->first()['user_id'] ?? null,
                    'value' => $values->isNotEmpty() ? round((float) $values->avg() * 100, 1) : null,
                    'source' => 'table',
                    'source_count' => $playerRows->count(),
                ];
            })
            ->values();
    }

    private function sourceRows(string $table, string $userColumn, string $valueColumn, array $context, int $days, string $metricKey, array &$stats, array $where = []): Collection
    {
        if (! $this->hasColumns($table, [$userColumn, $valueColumn])) {
            return collect();
        }

        $query = DB::table($table)
            ->select([
                $userColumn.' as user_id',
                $valueColumn.' as value',
            ]);

        if (Schema::hasColumn($table, 'created_at')) {
            $query->addSelect('created_at')->where('created_at', '>=', now()->subDays($days));
        }

        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        foreach ($where as $column => $value) {
            if (Schema::hasColumn($table, (string) $column)) {
                $query->where((string) $column, $value);
            }
        }

        $this->applyTeamScope($query, $table, $userColumn, $context);

        return collect($query->get())
            ->map(function ($row) use ($metricKey, &$stats, $table, $valueColumn) {
                $stats['raw_values_found']++;
                $this->recordRawSample($stats, $table, $valueColumn, $row->user_id ?? null, $row->value ?? null);
                $validation = $this->guardrail->validate($metricKey, $row->value ?? null);
                if (($validation['included'] ?? false) !== true) {
                    $this->recordExcluded($stats, $validation, $table, $valueColumn, $row->user_id ?? null, 'raw');

                    return null;
                }

                $stats['raw_values_included']++;
                $this->recordRawIncludedSample($stats, $table, $valueColumn, $row->user_id ?? null, $validation['value']);

                return [
                    'user_id' => (string) ($row->user_id ?? ''),
                    'value' => $validation['value'],
                    'created_at' => $row->created_at ?? null,
                    'source' => 'table',
                    'table' => $table,
                    'column' => $valueColumn,
                ];
            })
            ->filter()
            ->values();
    }

    private function aggregateRows(Collection $rows, string $aggregate): Collection
    {
        return $rows
            ->groupBy('user_id')
            ->map(function (Collection $playerRows) use ($aggregate) {
                $values = $playerRows
                    ->pluck('value')
                    ->map(fn ($value) => is_numeric($value) ? (float) $value : null)
                    ->filter(fn ($value) => $value !== null);

                if ($values->isEmpty()) {
                    $value = null;
                } else {
                    $value = match ($aggregate) {
                        'avg' => round((float) $values->avg(), 1),
                        'min' => round((float) $values->min(), 2),
                        'latest' => $this->latestValue($playerRows),
                        default => round((float) $values->max(), 1),
                    };
                }

                return [
                    'user_id' => $playerRows->first()['user_id'] ?? null,
                    'value' => $value,
                    'source' => (string) ($playerRows->first()['source'] ?? 'table'),
                    'source_count' => $playerRows->count(),
                ];
            })
            ->values();
    }

    private function guardRows(Collection $rows, string $metricKey, array &$stats, string $stage): Collection
    {
        return $rows
            ->map(function (array $row) use ($metricKey, &$stats, $stage) {
                $validation = $this->guardrail->validate($metricKey, $row['value'] ?? null);
                if (($validation['included'] ?? false) !== true) {
                    $this->recordExcluded($stats, $validation, 'population_aggregate', 'value', $row['user_id'] ?? null, $stage);

                    return null;
                }

                $row['value'] = $validation['value'];

                return $row;
            })
            ->filter()
            ->values();
    }

    private function emptyAuditStats(): array
    {
        return [
            'raw_values_found' => 0,
            'raw_values_included' => 0,
            'excluded' => [],
            'raw_samples' => [],
            'raw_included_samples' => [],
            'table_values_count' => 0,
            'trusted_task_values_found' => 0,
            'trusted_task_values_included_before_dedupe' => 0,
            'trusted_task_deduped_count' => 0,
            'trusted_task_excluded' => [],
            'trusted_task_status_excluded' => [],
            'trusted_task_samples' => [],
            'bucket_filter_applied' => false,
            'bucket_context_before_count' => 0,
            'bucket_context_after_count' => 0,
            'bucket_context_removed_count' => 0,
            'bucket_context_removed_samples' => [],
        ];
    }

    private function recordRawSample(array &$stats, string $table, string $valueColumn, mixed $userId, mixed $value): void
    {
        if (count($stats['raw_samples']) >= 10) {
            return;
        }

        $stats['raw_samples'][] = [
            'table' => $table,
            'column' => $valueColumn,
            'user_id' => $userId !== null ? (string) $userId : null,
            'raw_value' => $value,
        ];
    }

    private function recordRawIncludedSample(array &$stats, string $table, string $valueColumn, mixed $userId, mixed $value): void
    {
        if (count($stats['raw_included_samples']) >= 10) {
            return;
        }

        $stats['raw_included_samples'][] = [
            'table' => $table,
            'column' => $valueColumn,
            'user_id' => $userId !== null ? (string) $userId : null,
            'value' => $value,
        ];
    }

    private function recordExcluded(array &$stats, array $validation, string $table, string $valueColumn, mixed $userId, string $stage): void
    {
        $stats['excluded'][] = [
            'metric_key' => $validation['metric_key'] ?? null,
            'reason' => $validation['reason'] ?? 'invalid_value',
            'raw_value' => $validation['raw_value'] ?? null,
            'parsed_value' => $validation['value'] ?? null,
            'valid_range' => $validation['range'] ?? null,
            'table' => $table,
            'column' => $valueColumn,
            'user_id' => $userId !== null ? (string) $userId : null,
            'stage' => $stage,
        ];
    }

    private function excludedReasonCounts(array $excluded): array
    {
        $counts = [];

        foreach ($excluded as $row) {
            $reason = (string) ($row['reason'] ?? 'invalid_value');
            $counts[$reason] = ($counts[$reason] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    private function sampleRowValues(Collection $rows, int $limit = 12): array
    {
        return $rows
            ->take($limit)
            ->map(fn (array $row) => [
                'user_id' => $row['user_id'] ?? null,
                'value' => $row['value'] ?? null,
                'source' => $row['source'] ?? null,
            ])
            ->values()
            ->all();
    }

    private function latestValue(Collection $rows): ?float
    {
        $latest = $rows
            ->sortByDesc(fn (array $row) => $row['created_at'] ?? '')
            ->first();

        return $this->numberOrNull($latest['value'] ?? null);
    }

    private function applyTeamScope($query, string $table, string $userColumn, array $context): void
    {
        $teamId = $context['team_id'] ?? $context['teamId'] ?? null;
        if (! $teamId) {
            return;
        }

        $rosterIds = $this->rosterUserIds((string) $teamId);
        if (! empty($rosterIds)) {
            $query->whereIn($userColumn, $rosterIds);
        }

        if (Schema::hasColumn($table, 'team_id')) {
            $query->where(function ($scope) use ($teamId) {
                $scope->where('team_id', $teamId)->orWhereNull('team_id');
            });
        }
    }

    private function filterRowsByContext(Collection $rows, array $context): Collection
    {
        if (! $this->hasBucketFilters($context)) {
            return $rows;
        }

        $userIds = $rows->pluck('user_id')->filter()->unique()->values()->all();
        $userContexts = $this->userContexts($userIds);

        return $rows->filter(function (array $row) use ($context, $userContexts) {
            $userContext = $userContexts[(string) ($row['user_id'] ?? '')] ?? null;

            return $userContext !== null && $this->matchesContext($userContext, $context);
        })->values();
    }

    private function filterRowsByContextWithAudit(Collection $rows, array $context, array &$stats): Collection
    {
        $stats['bucket_filter_applied'] = $this->hasBucketFilters($context);
        $stats['bucket_context_before_count'] = $rows->count();

        if (! $stats['bucket_filter_applied']) {
            $stats['bucket_context_after_count'] = $rows->count();
            $stats['bucket_context_removed_count'] = 0;

            return $rows;
        }

        $userIds = $rows->pluck('user_id')->filter()->unique()->values()->all();
        $userContexts = $this->userContexts($userIds);
        $kept = collect();

        foreach ($rows as $row) {
            $userContext = $userContexts[(string) ($row['user_id'] ?? '')] ?? null;
            $matches = $userContext !== null && $this->matchesContext($userContext, $context);

            if ($matches) {
                $kept->push($row);
                continue;
            }

            if (count($stats['bucket_context_removed_samples']) < 10) {
                $stats['bucket_context_removed_samples'][] = [
                    'user_id' => $row['user_id'] ?? null,
                    'value' => $row['value'] ?? null,
                    'reason' => $userContext === null ? 'missing_player_context' : 'context_mismatch',
                ];
            }
        }

        $stats['bucket_context_after_count'] = $kept->count();
        $stats['bucket_context_removed_count'] = max(0, $stats['bucket_context_before_count'] - $stats['bucket_context_after_count']);

        return $kept->values();
    }

    private function bucketKeyForContext(array $context): string
    {
        $level = (string) ($context['_bucket_level'] ?? BenchmarkLibrary::BUCKET_EXACT_PEER);

        return $this->benchmarkLibrary->bucketKeyForLevel($context, $level);
    }

    private function matchesContext(array $userContext, array $context): bool
    {
        if ($this->filled($context['age_group'] ?? null) && $this->benchmarkLibrary->normalizeAgeGroup($context['age_group']) !== $userContext['age_group']) {
            return false;
        }

        if ($this->filled($context['level'] ?? null) && $this->benchmarkLibrary->normalizeLevel($context['level']) !== $this->benchmarkLibrary->normalizeLevel($userContext['level'] ?? null)) {
            return false;
        }

        if ($this->filled($context['throws'] ?? $context['throw_side'] ?? null) && $this->benchmarkLibrary->normalizeSide($context['throws'] ?? $context['throw_side']) !== $this->benchmarkLibrary->normalizeSide($userContext['throws'] ?? null)) {
            return false;
        }

        if ($this->filled($context['bats'] ?? $context['hit_side'] ?? null) && $this->benchmarkLibrary->normalizeSide($context['bats'] ?? $context['hit_side']) !== $this->benchmarkLibrary->normalizeSide($userContext['bats'] ?? null)) {
            return false;
        }

        if ($this->filled($context['position'] ?? $context['positions'] ?? null) && ! $this->positionMatches($context['position'] ?? $context['positions'], $userContext['positions'] ?? [])) {
            return false;
        }

        if ($this->filled($context['bodyweight_band'] ?? null) && $this->benchmarkLibrary->bodyweightBand($context['bodyweight_band']) !== $this->benchmarkLibrary->bodyweightBand($userContext['body_weight'] ?? null)) {
            return false;
        }

        if ($this->filled($context['height_band'] ?? null) && $this->benchmarkLibrary->heightBand($context['height_band']) !== $this->benchmarkLibrary->heightBand($userContext['height_inches'] ?? null)) {
            return false;
        }

        return true;
    }

    private function userContexts(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        return User::query()
            ->with(['profile', 'player', 'positions'])
            ->whereIn('id', $userIds)
            ->get()
            ->mapWithKeys(function (User $user) {
                $bornDate = $user->player?->born_date;
                $heightInches = $this->heightInches($user->player?->height_in_ft, $user->player?->height_in_inch);

                return [(string) $user->id => [
                    'age_group' => $this->benchmarkLibrary->normalizeAgeGroup($this->ageGroupFromDate($bornDate)),
                    'level' => $this->benchmarkLibrary->normalizeLevel($user->profile?->level),
                    'positions' => $user->positions?->pluck('position')->filter()->values()->all() ?? [],
                    'body_weight' => $this->playerBodyWeight($user),
                    'height_inches' => $heightInches,
                    'throws' => $this->benchmarkLibrary->normalizeSide($user->player?->throw_side),
                    'bats' => $this->benchmarkLibrary->normalizeSide($user->player?->hit_side),
                ]];
            })
            ->all();
    }

    private function playerBodyWeight(User $user): ?float
    {
        $playerWeight = $user->player && isset($user->player->weight) ? $this->numberOrNull($user->player->weight) : null;
        if ($playerWeight !== null) {
            return $playerWeight;
        }

        if ($this->hasColumns('player_fitnesses', ['user_id', 'body_weight'])) {
            $fitnessWeight = DB::table('player_fitnesses')
                ->where('user_id', $user->id)
                ->when(Schema::hasColumn('player_fitnesses', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
                ->orderByDesc('fitness_date')
                ->orderByDesc('created_at')
                ->value('body_weight');

            if ($this->numberOrNull($fitnessWeight) !== null) {
                return (float) $fitnessWeight;
            }
        }

        if ($this->hasColumns('player_assessments', ['user_id', 'body_weight_lbs'])) {
            $assessmentWeight = DB::table('player_assessments')
                ->where('user_id', $user->id)
                ->when(Schema::hasColumn('player_assessments', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
                ->orderByDesc('assessment_date')
                ->orderByDesc('created_at')
                ->value('body_weight_lbs');

            return $this->numberOrNull($assessmentWeight);
        }

        return null;
    }

    private function rosterUserIds(string $teamId): array
    {
        if (! $this->hasColumns('player_teams', ['team_id', 'user_id'])) {
            return [];
        }

        return DB::table('player_teams')
            ->where('team_id', $teamId)
            ->when(Schema::hasColumn('player_teams', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
            ->pluck('user_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function hasBucketFilters(array $context): bool
    {
        foreach (['age_group', 'position', 'positions', 'level', 'bodyweight_band', 'height_band', 'throws', 'throw_side', 'bats', 'hit_side'] as $key) {
            if ($this->filled($context[$key] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private function hasColumns(string $table, array $columns): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }

    private function numberOrNull(mixed $value, ?string $metricKey = null): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value) && str_contains($value, ':')) {
            return $this->timeStringToSeconds($value);
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function parseStoredValue(mixed $value): ?float
    {
        return $this->numberOrNull($value);
    }

    private function timeStringToSeconds(string $value): ?float
    {
        $parts = array_map('trim', explode(':', $value));
        if (count($parts) === 0) {
            return null;
        }

        $seconds = 0.0;
        foreach ($parts as $part) {
            if (! is_numeric($part)) {
                return null;
            }
            $seconds = ($seconds * 60) + (float) $part;
        }

        return round($seconds, 2);
    }

    private function validPopulationValue(string $metricKey, float $value): bool
    {
        if ($metricKey === 'strike_percentage') {
            return $value >= 0;
        }

        return $value > 0;
    }

    private function ageGroupFromDate(mixed $date): string
    {
        if (! $date) {
            return BenchmarkDefinitions::AGE_UNKNOWN;
        }

        try {
            return BenchmarkDefinitions::ageGroup(Carbon::parse((string) $date)->age);
        } catch (\Throwable) {
            return BenchmarkDefinitions::AGE_UNKNOWN;
        }
    }

    private function positionMatches(mixed $requested, array $actual): bool
    {
        $requested = $this->normalizePositionList($requested);
        $actual = $this->normalizePositionList($actual);

        return ! empty(array_intersect($requested, $actual));
    }

    private function normalizePositionList(mixed $value): array
    {
        if (is_array($value)) {
            $items = $value;
        } else {
            $items = preg_split('/[\s,\/|;]+/', (string) $value) ?: [];
        }

        return collect($items)
            ->map(fn ($item) => $this->benchmarkLibrary->normalizePosition($item))
            ->filter(fn (string $item) => $item !== '' && $item !== 'unknown')
            ->unique()
            ->values()
            ->all();
    }

    private function heightInches(mixed $feet, mixed $inches): ?float
    {
        $feet = is_numeric($feet) ? (float) $feet : null;
        $inches = is_numeric($inches) ? (float) $inches : null;

        if ($feet === null && $inches === null) {
            return null;
        }

        return (($feet ?? 0.0) * 12) + ($inches ?? 0.0);
    }

    private function bodyWeightBand(mixed $value): string
    {
        $value = $this->numberOrNull($value);
        if ($value === null || $value <= 0) {
            return 'unknown';
        }

        return match (true) {
            $value < 120 => 'under_120',
            $value < 150 => '120_149',
            $value < 180 => '150_179',
            $value < 210 => '180_209',
            default => '210_plus',
        };
    }

    private function heightBand(mixed $value): string
    {
        $value = $this->numberOrNull($value);
        if ($value === null || $value <= 0) {
            return 'unknown';
        }

        return match (true) {
            $value < 63 => 'under_63',
            $value < 66 => '63_65',
            $value < 69 => '66_68',
            $value < 72 => '69_71',
            $value < 75 => '72_74',
            default => '75_plus',
        };
    }

    private function filled(mixed $value): bool
    {
        if (is_array($value)) {
            return ! empty(array_filter($value, fn ($item) => trim((string) $item) !== ''));
        }

        $value = trim((string) $value);

        return $value !== '' && strtolower($value) !== 'unknown';
    }
}
