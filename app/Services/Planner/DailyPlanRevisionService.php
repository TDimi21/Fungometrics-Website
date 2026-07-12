<?php

declare(strict_types=1);

namespace App\Services\Planner;

use App\Models\DailyPlan;
use App\Models\DailyPlanRevision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class DailyPlanRevisionService
{
    public function createRevision(string $dailyPlanId, array $before, array $after, array $metadata = []): array
    {
        $diff = $this->buildPlanDiff($before, $after);
        $hasChanges = $this->diffHasChanges($diff);

        if (! $hasChanges) {
            return [
                'revision_status' => 'skipped_no_changes',
                'revision_id' => null,
                'daily_plan_id' => $dailyPlanId,
                'revision_number' => null,
                'source' => $metadata['source'] ?? 'system',
                'change_type' => $metadata['change_type'] ?? 'edited',
                'reason' => $metadata['reason'] ?? 'No material daily plan changes were detected.',
                'diff_summary' => $diff,
                'applied_suggestions' => $metadata['applied_suggestions'] ?? [],
                'created_at' => now()->toIso8601String(),
            ];
        }

        try {
            $revision = DB::transaction(function () use ($dailyPlanId, $before, $after, $metadata, $diff): DailyPlanRevision {
                $revisionNumber = ((int) DailyPlanRevision::query()
                    ->where('daily_plan_id', $dailyPlanId)
                    ->lockForUpdate()
                    ->max('revision_number')) + 1;

                return DailyPlanRevision::query()->create([
                    'daily_plan_id' => $dailyPlanId,
                    'team_id' => $after['team_id'] ?? $before['team_id'] ?? null,
                    'created_by_user_id' => $metadata['created_by_user_id'] ?? null,
                    'revision_number' => $revisionNumber,
                    'source' => $metadata['source'] ?? 'system',
                    'change_type' => $metadata['change_type'] ?? 'edited',
                    'title_before' => $before['name'] ?? null,
                    'title_after' => $after['name'] ?? null,
                    'status_before' => $before['status'] ?? null,
                    'status_after' => $after['status'] ?? null,
                    'plan_before' => $before,
                    'plan_after' => $after,
                    'diff_summary' => $diff,
                    'applied_suggestions' => $metadata['applied_suggestions'] ?? [],
                    'reason' => $metadata['reason'] ?? null,
                    'coach_notes' => $metadata['coach_notes'] ?? null,
                ]);
            });

            return $this->revisionPayload($revision, 'created');
        } catch (Throwable $exception) {
            return [
                'revision_status' => 'failed',
                'revision_id' => null,
                'daily_plan_id' => $dailyPlanId,
                'revision_number' => null,
                'source' => $metadata['source'] ?? 'system',
                'change_type' => $metadata['change_type'] ?? 'edited',
                'reason' => $metadata['reason'] ?? null,
                'diff_summary' => [
                    ...$diff,
                    'warnings' => [
                        ...($diff['warnings'] ?? []),
                        'Revision could not be saved: '.$exception->getMessage(),
                    ],
                ],
                'applied_suggestions' => $metadata['applied_suggestions'] ?? [],
                'created_at' => now()->toIso8601String(),
            ];
        }
    }

    public function snapshotPlan(string $dailyPlanId): array
    {
        $plan = DailyPlan::query()
            ->with(['assignments', 'progress'])
            ->find($dailyPlanId);

        if (! $plan) {
            return [];
        }

        $payload = $plan->toArray();
        $payload['snapshot_at'] = now()->toIso8601String();
        $payload['progress_summary'] = [
            'progress_row_count' => $plan->progress->count(),
            'completed_progress_count' => $plan->progress->filter(fn ($row): bool => ! empty($row->completed_at))->count(),
            'completed_item_ids' => $this->completedItemIds($plan->progress->toArray()),
        ];

        return $payload;
    }

    public function buildPlanDiff(array $before, array $after): array
    {
        $warnings = [];

        try {
            $beforeBlocks = $this->planBlocks($before);
            $afterBlocks = $this->planBlocks($after);
            $beforeMap = $this->blockMap($beforeBlocks);
            $afterMap = $this->blockMap($afterBlocks);

            $beforeKeys = array_keys($beforeMap);
            $afterKeys = array_keys($afterMap);
            $addedKeys = array_values(array_diff($afterKeys, $beforeKeys));
            $removedKeys = array_values(array_diff($beforeKeys, $afterKeys));
            $sharedKeys = array_values(array_intersect($beforeKeys, $afterKeys));

            $blocksUpdated = [];
            foreach ($sharedKeys as $key) {
                $blockChanges = $this->blockChanges($beforeMap[$key], $afterMap[$key]);
                if (! empty($blockChanges)) {
                    $blocksUpdated[] = [
                        'key' => $key,
                        'title' => $afterMap[$key]['title'] ?? $beforeMap[$key]['title'] ?? 'Practice Block',
                        'changes' => $blockChanges,
                    ];
                }
            }

            $metricsBefore = $this->metricsFromBlocks($beforeBlocks);
            $metricsAfter = $this->metricsFromBlocks($afterBlocks);
            $playersBefore = $this->assignedPlayers($before);
            $playersAfter = $this->assignedPlayers($after);
            $orderChanged = $this->relativeOrderChanged($beforeKeys, $afterKeys, $sharedKeys);

            return [
                'title_changed' => ($before['name'] ?? null) !== ($after['name'] ?? null),
                'status_changed' => ($before['status'] ?? null) !== ($after['status'] ?? null),
                'blocks_added' => array_values(array_map(fn (string $key): array => $afterMap[$key], $addedKeys)),
                'blocks_removed' => array_values(array_map(fn (string $key): array => $beforeMap[$key], $removedKeys)),
                'blocks_updated' => $blocksUpdated,
                'blocks_reordered' => $orderChanged ? [
                    'before_order' => $beforeKeys,
                    'after_order' => $afterKeys,
                ] : [],
                'duration_before' => $this->duration($before),
                'duration_after' => $this->duration($after),
                'duration_delta' => $this->duration($after) !== null && $this->duration($before) !== null
                    ? $this->duration($after) - $this->duration($before)
                    : null,
                'metrics_added' => array_values(array_diff($metricsAfter, $metricsBefore)),
                'metrics_removed' => array_values(array_diff($metricsBefore, $metricsAfter)),
                'players_affected' => array_values(array_unique([
                    ...array_diff($playersAfter, $playersBefore),
                    ...array_diff($playersBefore, $playersAfter),
                    ...$playersAfter,
                ])),
                'warnings' => $warnings,
            ];
        } catch (Throwable $exception) {
            return [
                'title_changed' => false,
                'status_changed' => false,
                'blocks_added' => [],
                'blocks_removed' => [],
                'blocks_updated' => [],
                'blocks_reordered' => [],
                'duration_before' => $this->duration($before),
                'duration_after' => $this->duration($after),
                'duration_delta' => null,
                'metrics_added' => [],
                'metrics_removed' => [],
                'players_affected' => [],
                'warnings' => ['Diff could not be fully computed: '.$exception->getMessage()],
            ];
        }
    }

    public function listRevisions(string $dailyPlanId): array
    {
        $revisions = DailyPlanRevision::query()
            ->where('daily_plan_id', $dailyPlanId)
            ->orderByDesc('revision_number')
            ->get();

        return [
            'daily_plan_id' => $dailyPlanId,
            'revision_count' => $revisions->count(),
            'revisions' => $revisions
                ->map(fn (DailyPlanRevision $revision): array => $this->revisionPayload($revision))
                ->values()
                ->all(),
        ];
    }

    public function latestRevision(string $dailyPlanId): ?array
    {
        $revision = DailyPlanRevision::query()
            ->where('daily_plan_id', $dailyPlanId)
            ->orderByDesc('revision_number')
            ->first();

        return $revision ? $this->revisionPayload($revision) : null;
    }

    public function compareRevisions(string $dailyPlanId, int $fromRevision, int $toRevision): array
    {
        $from = DailyPlanRevision::query()
            ->where('daily_plan_id', $dailyPlanId)
            ->where('revision_number', $fromRevision)
            ->first();

        $to = DailyPlanRevision::query()
            ->where('daily_plan_id', $dailyPlanId)
            ->where('revision_number', $toRevision)
            ->first();

        if (! $from || ! $to) {
            return [
                'daily_plan_id' => $dailyPlanId,
                'from_revision' => $fromRevision,
                'to_revision' => $toRevision,
                'compare_status' => 'not_found',
                'diff_summary' => [],
                'warnings' => ['One or both requested revisions were not found.'],
            ];
        }

        $before = is_array($from->plan_after) ? $from->plan_after : [];
        $after = is_array($to->plan_after) ? $to->plan_after : [];

        return [
            'daily_plan_id' => $dailyPlanId,
            'from_revision' => $this->revisionPayload($from),
            'to_revision' => $this->revisionPayload($to),
            'compare_status' => 'completed',
            'diff_summary' => $this->buildPlanDiff($before, $after),
            'warnings' => [],
        ];
    }

    public function revisionById(string $dailyPlanId, string $revisionId): ?array
    {
        $revision = DailyPlanRevision::query()
            ->where('daily_plan_id', $dailyPlanId)
            ->where(function ($query) use ($revisionId): void {
                $query->where('id', $revisionId);
                if (ctype_digit($revisionId)) {
                    $query->orWhere('revision_number', (int) $revisionId);
                }
            })
            ->first();

        return $revision ? $this->revisionPayload($revision, 'loaded', true) : null;
    }

    private function revisionPayload(DailyPlanRevision $revision, string $status = 'loaded', bool $includeSnapshots = false): array
    {
        $payload = [
            'revision_status' => $status,
            'revision_id' => (string) $revision->id,
            'daily_plan_id' => (string) $revision->daily_plan_id,
            'team_id' => $revision->team_id,
            'revision_number' => (int) $revision->revision_number,
            'source' => $revision->source,
            'change_type' => $revision->change_type,
            'reason' => $revision->reason,
            'coach_notes' => $revision->coach_notes,
            'title_before' => $revision->title_before,
            'title_after' => $revision->title_after,
            'status_before' => $revision->status_before,
            'status_after' => $revision->status_after,
            'diff_summary' => $revision->diff_summary ?? [],
            'applied_suggestions' => $revision->applied_suggestions ?? [],
            'created_at' => optional($revision->created_at)->toIso8601String(),
        ];

        if ($includeSnapshots) {
            $payload['plan_before'] = $revision->plan_before ?? [];
            $payload['plan_after'] = $revision->plan_after ?? [];
        }

        return $payload;
    }

    private function diffHasChanges(array $diff): bool
    {
        return (bool) ($diff['title_changed'] ?? false)
            || (bool) ($diff['status_changed'] ?? false)
            || ! empty($diff['blocks_added'] ?? [])
            || ! empty($diff['blocks_removed'] ?? [])
            || ! empty($diff['blocks_updated'] ?? [])
            || ! empty($diff['blocks_reordered'] ?? [])
            || ! empty($diff['metrics_added'] ?? [])
            || ! empty($diff['metrics_removed'] ?? [])
            || (($diff['duration_delta'] ?? null) !== null && (int) $diff['duration_delta'] !== 0);
    }

    private function planBlocks(array $plan): array
    {
        $blocks = [];
        foreach (($plan['buckets'] ?? []) as $bucketIndex => $bucket) {
            if (! is_array($bucket)) {
                continue;
            }

            foreach (($bucket['items'] ?? []) as $itemIndex => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $blocks[] = [
                    'key' => $this->blockKey($item),
                    'id' => $item['id'] ?? null,
                    'title' => $item['name'] ?? $item['title'] ?? 'Practice Block',
                    'bucket_type' => $bucket['type'] ?? $item['bucket'] ?? null,
                    'bucket_title' => $bucket['title'] ?? null,
                    'duration_minutes' => $this->itemDurationMinutes($item),
                    'metrics' => $this->metricKeys($item['relatedMetrics'] ?? []),
                    'source' => $item['source'] ?? null,
                    'temporary_key' => $item['benchmark_task_temporary_key'] ?? null,
                    'order' => $bucketIndex.'.'.$itemIndex,
                    'fingerprint' => $this->fingerprint($item),
                ];
            }
        }

        return $blocks;
    }

    private function blockMap(array $blocks): array
    {
        $map = [];
        foreach ($blocks as $block) {
            $key = (string) ($block['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $map[$key] = $block;
        }

        return $map;
    }

    private function blockChanges(array $before, array $after): array
    {
        $changes = [];
        foreach (['title', 'bucket_type', 'duration_minutes', 'source'] as $field) {
            if (($before[$field] ?? null) !== ($after[$field] ?? null)) {
                $changes[$field] = [
                    'before' => $before[$field] ?? null,
                    'after' => $after[$field] ?? null,
                ];
            }
        }

        $beforeMetrics = $before['metrics'] ?? [];
        $afterMetrics = $after['metrics'] ?? [];
        $addedMetrics = array_values(array_diff($afterMetrics, $beforeMetrics));
        $removedMetrics = array_values(array_diff($beforeMetrics, $afterMetrics));
        if (! empty($addedMetrics) || ! empty($removedMetrics)) {
            $changes['metrics'] = [
                'added' => $addedMetrics,
                'removed' => $removedMetrics,
            ];
        }

        if (($before['fingerprint'] ?? null) !== ($after['fingerprint'] ?? null) && empty($changes)) {
            $changes['payload'] = 'Plan item payload changed.';
        }

        return $changes;
    }

    private function relativeOrderChanged(array $beforeKeys, array $afterKeys, array $sharedKeys): bool
    {
        if (count($sharedKeys) < 2) {
            return false;
        }

        $beforeShared = array_values(array_filter($beforeKeys, fn (string $key): bool => in_array($key, $sharedKeys, true)));
        $afterShared = array_values(array_filter($afterKeys, fn (string $key): bool => in_array($key, $sharedKeys, true)));

        return $beforeShared !== $afterShared;
    }

    private function metricsFromBlocks(array $blocks): array
    {
        return collect($blocks)
            ->flatMap(fn (array $block): array => $block['metrics'] ?? [])
            ->unique()
            ->values()
            ->all();
    }

    private function assignedPlayers(array $plan): array
    {
        return collect($plan['assigned_player_ids'] ?? $plan['assignments'] ?? [])
            ->map(function ($player): ?string {
                if (is_array($player)) {
                    return $player['user_id'] ?? $player['player_id'] ?? $player['id'] ?? null;
                }

                return $player ? (string) $player : null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function completedItemIds(array $progressRows): array
    {
        $ids = [];
        foreach ($progressRows as $row) {
            $items = is_array($row['items'] ?? null) ? $row['items'] : [];
            $rowComplete = ! empty($row['completed_at'] ?? null);
            foreach ($items as $key => $item) {
                if (! is_array($item)) {
                    continue;
                }

                if ($rowComplete || filter_var($item['done'] ?? $item['completed'] ?? false, FILTER_VALIDATE_BOOL)) {
                    $ids[] = (string) ($item['id'] ?? $key);
                }
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    private function duration(array $plan): ?int
    {
        if (is_numeric($plan['estimated_minutes'] ?? null)) {
            return (int) $plan['estimated_minutes'];
        }

        $blocks = $this->planBlocks($plan);
        if (empty($blocks)) {
            return null;
        }

        return array_sum(array_map(fn (array $block): int => (int) ($block['duration_minutes'] ?? 0), $blocks));
    }

    private function itemDurationMinutes(array $item): int
    {
        if (is_numeric($item['duration_minutes'] ?? null)) {
            return (int) $item['duration_minutes'];
        }

        if (is_numeric($item['durationSec'] ?? null)) {
            return (int) ceil(((int) $item['durationSec']) / 60);
        }

        return 0;
    }

    private function metricKeys(mixed $metrics): array
    {
        return collect(is_array($metrics) ? $metrics : [])
            ->map(function ($metric): ?string {
                if (is_array($metric)) {
                    $metric = $metric['metric_key'] ?? $metric['key'] ?? $metric['display_name'] ?? null;
                }

                $metric = trim((string) ($metric ?? ''));

                return $metric !== '' ? Str::of($metric)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString() : null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function blockKey(array $item): string
    {
        foreach ([
            $item['benchmark_task_temporary_key'] ?? null,
            $item['temporary_key'] ?? null,
            $item['id'] ?? null,
            $item['name'] ?? $item['title'] ?? null,
        ] as $candidate) {
            $key = $this->token($candidate);
            if ($key !== '') {
                return $key;
            }
        }

        return '';
    }

    private function fingerprint(array $item): string
    {
        ksort($item);

        return sha1(json_encode($item, JSON_UNESCAPED_SLASHES) ?: '');
    }

    private function token(mixed $value): string
    {
        return Str::of((string) ($value ?? ''))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();
    }
}
