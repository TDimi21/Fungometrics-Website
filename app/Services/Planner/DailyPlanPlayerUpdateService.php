<?php

declare(strict_types=1);

namespace App\Services\Planner;

use App\Models\DailyPlan;
use App\Models\DailyPlanAssignment;
use App\Models\DailyPlanProgress;
use App\Models\DailyPlanRevision;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class DailyPlanPlayerUpdateService
{
    public function buildPlayerPlanUpdateStatus(string $dailyPlanId, string $playerId): array
    {
        $warnings = [];
        $assigned = DailyPlanAssignment::query()
            ->where('plan_id', $dailyPlanId)
            ->where('user_id', $playerId)
            ->exists();

        if (! $assigned) {
            return $this->emptyStatus($dailyPlanId, $playerId, [
                'Player is not assigned to this Daily Plan.',
            ]);
        }

        $plan = DailyPlan::query()
            ->where('id', $dailyPlanId)
            ->where('status', 'published')
            ->first();

        if (! $plan) {
            return $this->emptyStatus($dailyPlanId, $playerId, [
                'Daily Plan is not published or could not be found.',
            ]);
        }

        $revision = DailyPlanRevision::query()
            ->where('daily_plan_id', $dailyPlanId)
            ->orderByDesc('revision_number')
            ->first();

        if (! $revision) {
            return $this->emptyStatus($dailyPlanId, $playerId);
        }

        $progress = DailyPlanProgress::query()
            ->where('plan_id', $dailyPlanId)
            ->where('user_id', $playerId)
            ->first();

        $reflection = is_array($progress?->reflection) ? $progress->reflection : [];
        $seenRevisionIds = array_values(array_unique(array_filter(array_map(
            'strval',
            Arr::wrap($reflection['seen_revision_ids'] ?? [])
        ))));
        $latestRevisionId = (string) $revision->id;
        $seen = in_array($latestRevisionId, $seenRevisionIds, true);

        $diff = is_array($revision->diff_summary) ? $revision->diff_summary : [];
        $addedBlocks = $this->blockSummaries(Arr::wrap($diff['blocks_added'] ?? []), 'added');
        $updatedBlocks = $this->updatedBlockSummaries(Arr::wrap($diff['blocks_updated'] ?? []));
        $removedOrMovedBlocks = [
            ...$this->blockSummaries(Arr::wrap($diff['blocks_removed'] ?? []), 'removed'),
            ...$this->reorderedBlockSummaries($diff['blocks_reordered'] ?? []),
        ];
        $changeSummary = $this->changeSummary($diff, $addedBlocks, $updatedBlocks, $removedOrMovedBlocks);
        $hasMaterialChange = $this->hasMaterialChange($diff, $addedBlocks, $updatedBlocks, $removedOrMovedBlocks);
        $hasUpdate = $hasMaterialChange && ! $seen;

        if (! $hasMaterialChange) {
            $warnings[] = 'Latest revision did not include player-visible plan changes.';
        }

        return [
            'daily_plan_id' => $dailyPlanId,
            'player_id' => $playerId,
            'has_update' => $hasUpdate,
            'latest_revision_id' => $latestRevisionId,
            'latest_revision_number' => (int) $revision->revision_number,
            'updated_at' => optional($revision->created_at)->toIso8601String(),
            'update_title' => $this->updateTitle((string) ($revision->change_type ?? 'updated')),
            'update_message' => $this->updateMessage($addedBlocks, $updatedBlocks, $removedOrMovedBlocks),
            'change_summary' => $changeSummary,
            'added_blocks' => $addedBlocks,
            'updated_blocks' => $updatedBlocks,
            'removed_or_moved_blocks' => $removedOrMovedBlocks,
            'progress_preserved' => true,
            'requires_attention' => $hasUpdate && (! empty($addedBlocks) || ! empty($updatedBlocks) || ! empty($removedOrMovedBlocks)),
            'seen' => $seen,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    public function listPlayerPlanUpdates(string $playerId, array $filters = []): array
    {
        $query = DailyPlanAssignment::query()
            ->where('user_id', $playerId)
            ->whereHas('plan', fn ($planQuery) => $planQuery->where('status', 'published'));

        if (! empty($filters['daily_plan_id'])) {
            $query->where('plan_id', (string) $filters['daily_plan_id']);
        }

        $planIds = $query
            ->orderByDesc('updated_at')
            ->pluck('plan_id')
            ->unique()
            ->values()
            ->all();

        $statuses = collect($planIds)
            ->map(fn (string $planId): array => $this->buildPlayerPlanUpdateStatus($planId, $playerId))
            ->filter(fn (array $status): bool => ! empty($filters['include_seen']) || (bool) ($status['has_update'] ?? false))
            ->values()
            ->all();

        return [
            'player_id' => $playerId,
            'plan_count' => count($planIds),
            'update_count' => count($statuses),
            'updates' => $statuses,
            'warnings' => [],
        ];
    }

    public function markUpdateSeen(string $dailyPlanId, string $playerId, ?string $revisionId = null): array
    {
        $assigned = DailyPlanAssignment::query()
            ->where('plan_id', $dailyPlanId)
            ->where('user_id', $playerId)
            ->exists();

        if (! $assigned) {
            return $this->emptyStatus($dailyPlanId, $playerId, [
                'Player is not assigned to this Daily Plan.',
            ]);
        }

        $revision = DailyPlanRevision::query()
            ->where('daily_plan_id', $dailyPlanId)
            ->when($revisionId, function ($query) use ($revisionId): void {
                $query->where(function ($revisionQuery) use ($revisionId): void {
                    $revisionQuery->where('id', $revisionId);
                    if (ctype_digit($revisionId)) {
                        $revisionQuery->orWhere('revision_number', (int) $revisionId);
                    }
                });
            }, fn ($query) => $query->orderByDesc('revision_number'))
            ->first();

        if (! $revision) {
            return $this->emptyStatus($dailyPlanId, $playerId, [
                'Daily Plan revision could not be found.',
            ]);
        }

        $progress = DailyPlanProgress::query()->firstOrCreate(
            [
                'plan_id' => $dailyPlanId,
                'user_id' => $playerId,
            ],
            [
                'readiness' => [],
                'items' => [],
                'reflection' => [],
            ]
        );

        $reflection = is_array($progress->reflection) ? $progress->reflection : [];
        $seenRevisionIds = Arr::wrap($reflection['seen_revision_ids'] ?? []);
        $seenRevisionIds[] = (string) $revision->id;

        $reflection['seen_revision_ids'] = array_values(array_unique(array_filter(array_map('strval', $seenRevisionIds))));
        $reflection['latest_update_seen_at'] = now()->toIso8601String();

        $progress->reflection = $reflection;
        $progress->save();

        return $this->buildPlayerPlanUpdateStatus($dailyPlanId, $playerId);
    }

    private function emptyStatus(string $dailyPlanId, string $playerId, array $warnings = []): array
    {
        return [
            'daily_plan_id' => $dailyPlanId,
            'player_id' => $playerId,
            'has_update' => false,
            'latest_revision_id' => null,
            'latest_revision_number' => null,
            'updated_at' => null,
            'update_title' => null,
            'update_message' => null,
            'change_summary' => [],
            'added_blocks' => [],
            'updated_blocks' => [],
            'removed_or_moved_blocks' => [],
            'progress_preserved' => true,
            'requires_attention' => false,
            'seen' => true,
            'warnings' => $warnings,
        ];
    }

    private function blockSummaries(array $blocks, string $type): array
    {
        return collect($blocks)
            ->filter(fn ($block): bool => is_array($block))
            ->map(function (array $block) use ($type): array {
                return [
                    'type' => $type,
                    'title' => $this->blockTitle($block),
                    'bucket' => $this->humanize($block['bucket_title'] ?? $block['bucket_type'] ?? null),
                    'duration_minutes' => is_numeric($block['duration_minutes'] ?? null) ? (int) $block['duration_minutes'] : null,
                    'metrics' => $this->humanizedMetrics(Arr::wrap($block['metrics'] ?? [])),
                    'message' => $type === 'added'
                        ? 'New work was added to this plan.'
                        : 'A future block was removed or replaced.',
                ];
            })
            ->values()
            ->all();
    }

    private function updatedBlockSummaries(array $blocks): array
    {
        return collect($blocks)
            ->filter(fn ($block): bool => is_array($block))
            ->map(function (array $block): array {
                $changes = is_array($block['changes'] ?? null) ? $block['changes'] : [];

                return [
                    'type' => 'updated',
                    'title' => $this->blockTitle($block),
                    'bucket' => $this->humanize($block['bucket_title'] ?? $block['bucket_type'] ?? null),
                    'duration_minutes' => null,
                    'metrics' => $this->humanizedMetrics([
                        ...Arr::wrap($changes['metrics']['added'] ?? []),
                        ...Arr::wrap($changes['metrics']['removed'] ?? []),
                    ]),
                    'changed_fields' => array_values(array_map(
                        fn (string $field): string => $this->humanize($field),
                        array_keys($changes)
                    )),
                    'message' => 'A block detail was updated.',
                ];
            })
            ->values()
            ->all();
    }

    private function reorderedBlockSummaries(mixed $reordered): array
    {
        if (empty($reordered)) {
            return [];
        }

        return [[
            'type' => 'reordered',
            'title' => 'Plan Order Updated',
            'bucket' => null,
            'duration_minutes' => null,
            'metrics' => [],
            'message' => 'Your coach changed the order of future plan blocks.',
        ]];
    }

    private function changeSummary(array $diff, array $added, array $updated, array $removedOrMoved): array
    {
        return [
            'added_count' => count($added),
            'updated_count' => count($updated),
            'removed_or_moved_count' => count($removedOrMoved),
            'duration_delta' => is_numeric($diff['duration_delta'] ?? null) ? (int) $diff['duration_delta'] : null,
            'title_changed' => (bool) ($diff['title_changed'] ?? false),
            'metrics_added' => $this->humanizedMetrics(Arr::wrap($diff['metrics_added'] ?? [])),
            'metrics_removed' => $this->humanizedMetrics(Arr::wrap($diff['metrics_removed'] ?? [])),
        ];
    }

    private function hasMaterialChange(array $diff, array $added, array $updated, array $removedOrMoved): bool
    {
        return (bool) ($diff['title_changed'] ?? false)
            || (bool) ($diff['status_changed'] ?? false)
            || ! empty($added)
            || ! empty($updated)
            || ! empty($removedOrMoved)
            || ! empty($diff['metrics_added'] ?? [])
            || ! empty($diff['metrics_removed'] ?? [])
            || (($diff['duration_delta'] ?? null) !== null && (int) $diff['duration_delta'] !== 0);
    }

    private function updateTitle(string $changeType): string
    {
        return match ($changeType) {
            'republished' => 'Plan Republished',
            'suggestions_applied' => 'Plan Updated',
            default => 'Plan Updated',
        };
    }

    private function updateMessage(array $added, array $updated, array $removedOrMoved): string
    {
        if (! empty($added)) {
            return 'Your coach updated this plan and added new work. Your completed progress was preserved.';
        }

        if (! empty($updated)) {
            return 'Your coach updated details in this plan. Your completed progress was preserved.';
        }

        if (! empty($removedOrMoved)) {
            return 'Your coach changed the plan order or removed future work. Your completed progress was preserved.';
        }

        return 'Your coach updated this plan. Your completed progress was preserved.';
    }

    private function blockTitle(array $block): string
    {
        return trim((string) ($block['title'] ?? $block['name'] ?? 'Practice Block'));
    }

    private function humanizedMetrics(array $metrics): array
    {
        return collect($metrics)
            ->map(fn ($metric) => $this->humanize($metric))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function humanize(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }

        return Str::of($text)
            ->replace(['_', '-'], ' ')
            ->title()
            ->toString();
    }
}
