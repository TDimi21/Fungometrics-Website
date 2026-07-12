<?php

declare(strict_types=1);

namespace App\Services\Planner;

use App\Models\DailyPlan;
use App\Models\DailyPlanAssignment;
use App\Models\DailyPlanProgress;
use App\Models\DailyPlanRevision;
use App\Models\Profile;
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
        $latestRevisionId = (string) $revision->id;
        $seenState = $this->seenState($reflection, $latestRevisionId);
        $acknowledgementState = $this->acknowledgementState($reflection, $latestRevisionId);
        $seen = (bool) $seenState['seen'];
        $acknowledged = (bool) $acknowledgementState['acknowledged'];

        $diff = is_array($revision->diff_summary) ? $revision->diff_summary : [];
        $addedBlocks = $this->blockSummaries(Arr::wrap($diff['blocks_added'] ?? []), 'added');
        $updatedBlocks = $this->updatedBlockSummaries(Arr::wrap($diff['blocks_updated'] ?? []));
        $removedOrMovedBlocks = [
            ...$this->blockSummaries(Arr::wrap($diff['blocks_removed'] ?? []), 'removed'),
            ...$this->reorderedBlockSummaries($diff['blocks_reordered'] ?? []),
        ];
        $changeSummary = $this->changeSummary($diff, $addedBlocks, $updatedBlocks, $removedOrMovedBlocks);
        $hasMaterialChange = $this->hasMaterialChange($diff, $addedBlocks, $updatedBlocks, $removedOrMovedBlocks);
        $hasUpdate = $hasMaterialChange && ! $acknowledged;

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
            'latest_revision_seen_at' => $seenState['latest_revision_seen_at'],
            'acknowledged' => $acknowledged,
            'acknowledged_at' => $acknowledgementState['acknowledged_at'],
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    public function acknowledgeUpdate(string $dailyPlanId, string $playerId, ?string $revisionId = null, array $payload = []): array
    {
        $assigned = DailyPlanAssignment::query()
            ->where('plan_id', $dailyPlanId)
            ->where('user_id', $playerId)
            ->exists();

        if (! $assigned) {
            return [
                'daily_plan_id' => $dailyPlanId,
                'player_id' => $playerId,
                'revision_id' => null,
                'acknowledged' => false,
                'acknowledged_at' => null,
                'message' => 'Player is not assigned to this Daily Plan.',
                'warnings' => ['Player is not assigned to this Daily Plan.'],
            ];
        }

        $revision = $this->revisionForAcknowledgement($dailyPlanId, $revisionId);
        if (! $revision) {
            return [
                'daily_plan_id' => $dailyPlanId,
                'player_id' => $playerId,
                'revision_id' => null,
                'acknowledged' => false,
                'acknowledged_at' => null,
                'message' => 'No Daily Plan update is available to acknowledge.',
                'warnings' => ['Daily Plan revision could not be found.'],
            ];
        }

        $now = now()->toIso8601String();
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

        $revisionId = (string) $revision->id;
        $reflection = is_array($progress->reflection) ? $progress->reflection : [];
        $reflection['seen_revision_ids'] = $this->appendUniqueString($reflection['seen_revision_ids'] ?? [], $revisionId);
        $reflection['acknowledged_revision_ids'] = $this->appendUniqueString($reflection['acknowledged_revision_ids'] ?? [], $revisionId);
        $reflection['latest_revision_seen_id'] = $revisionId;
        $reflection['latest_update_seen_at'] = $now;
        $reflection['acknowledged_revision_id'] = $revisionId;
        $reflection['acknowledged_at'] = $now;
        $reflection['acknowledgement_payload'] = $payload;
        $reflection['acknowledgement_history'] = $this->acknowledgementHistory($reflection, $revision, $payload, $now);

        $progress->reflection = $reflection;
        $progress->save();

        return [
            'daily_plan_id' => $dailyPlanId,
            'player_id' => $playerId,
            'revision_id' => $revisionId,
            'latest_revision_number' => (int) $revision->revision_number,
            'acknowledged' => true,
            'acknowledged_at' => $now,
            'message' => 'Plan update acknowledged.',
            'update_status' => $this->buildPlayerPlanUpdateStatus($dailyPlanId, $playerId),
            'warnings' => [],
        ];
    }

    public function buildTeamAcknowledgementStatus(string $dailyPlanId): array
    {
        $plan = DailyPlan::query()
            ->with(['assignments.user.profile'])
            ->where('id', $dailyPlanId)
            ->first();

        if (! $plan) {
            return [
                'daily_plan_id' => $dailyPlanId,
                'team_id' => null,
                'latest_revision_id' => null,
                'latest_revision_number' => null,
                'assigned_player_count' => 0,
                'acknowledged_count' => 0,
                'not_acknowledged_count' => 0,
                'acknowledgement_percentage' => 0.0,
                'players_acknowledged' => [],
                'players_not_acknowledged' => [],
                'warnings' => ['Daily Plan could not be found.'],
            ];
        }

        $revision = $this->revisionForAcknowledgement($dailyPlanId);
        $needsAcknowledgement = $revision ? $this->revisionNeedsAcknowledgement($revision) : false;
        $rows = $plan->assignments
            ->map(fn (DailyPlanAssignment $assignment): array => $this->buildPlayerAcknowledgementStatus($dailyPlanId, (string) $assignment->user_id))
            ->values()
            ->all();

        $acknowledged = array_values(array_filter($rows, fn (array $row): bool => (bool) ($row['acknowledged'] ?? false)));
        $pending = $needsAcknowledgement
            ? array_values(array_filter($rows, fn (array $row): bool => ! (bool) ($row['acknowledged'] ?? false)))
            : [];
        $assignedCount = count($rows);
        $acknowledgedCount = $needsAcknowledgement ? count($acknowledged) : 0;
        $pendingCount = count($pending);

        return [
            'daily_plan_id' => $dailyPlanId,
            'team_id' => $plan->team_id ? (string) $plan->team_id : null,
            'latest_revision_id' => $revision ? (string) $revision->id : null,
            'latest_revision_number' => $revision ? (int) $revision->revision_number : null,
            'latest_revision_at' => $revision ? optional($revision->created_at)->toIso8601String() : null,
            'assigned_player_count' => $assignedCount,
            'acknowledged_count' => $acknowledgedCount,
            'not_acknowledged_count' => $pendingCount,
            'acknowledgement_percentage' => $assignedCount > 0 && $needsAcknowledgement
                ? round(($acknowledgedCount / $assignedCount) * 100, 1)
                : 0.0,
            'players_acknowledged' => $needsAcknowledgement ? $acknowledged : [],
            'players_not_acknowledged' => $pending,
            'warnings' => array_values(array_filter([
                $revision ? null : 'No Daily Plan revision exists yet.',
                $revision && ! $needsAcknowledgement ? 'Latest revision does not require player acknowledgement.' : null,
                $assignedCount === 0 ? 'No players are assigned to this Daily Plan.' : null,
            ])),
        ];
    }

    public function buildPlayerAcknowledgementStatus(string $dailyPlanId, string $playerId): array
    {
        $status = $this->buildPlayerPlanUpdateStatus($dailyPlanId, $playerId);
        $assigned = DailyPlanAssignment::query()
            ->where('plan_id', $dailyPlanId)
            ->where('user_id', $playerId)
            ->exists();

        return [
            'player_id' => $playerId,
            'player_name' => $this->playerName($playerId),
            'assigned' => $assigned,
            'has_update' => (bool) ($status['has_update'] ?? false),
            'acknowledged' => (bool) ($status['acknowledged'] ?? false),
            'acknowledged_at' => $status['acknowledged_at'] ?? null,
            'latest_revision_seen_at' => $status['latest_revision_seen_at'] ?? null,
            'latest_revision_number' => $status['latest_revision_number'] ?? null,
            'warnings' => $status['warnings'] ?? [],
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
        $reflection['seen_revision_ids'] = $this->appendUniqueString($reflection['seen_revision_ids'] ?? [], (string) $revision->id);
        $reflection['latest_revision_seen_id'] = (string) $revision->id;
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
            'latest_revision_seen_at' => null,
            'acknowledged' => false,
            'acknowledged_at' => null,
            'warnings' => $warnings,
        ];
    }

    private function revisionForAcknowledgement(string $dailyPlanId, ?string $revisionId = null): ?DailyPlanRevision
    {
        return DailyPlanRevision::query()
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
    }

    private function revisionNeedsAcknowledgement(DailyPlanRevision $revision): bool
    {
        $diff = is_array($revision->diff_summary) ? $revision->diff_summary : [];
        $addedBlocks = $this->blockSummaries(Arr::wrap($diff['blocks_added'] ?? []), 'added');
        $updatedBlocks = $this->updatedBlockSummaries(Arr::wrap($diff['blocks_updated'] ?? []));
        $removedOrMovedBlocks = [
            ...$this->blockSummaries(Arr::wrap($diff['blocks_removed'] ?? []), 'removed'),
            ...$this->reorderedBlockSummaries($diff['blocks_reordered'] ?? []),
        ];

        return $this->hasMaterialChange($diff, $addedBlocks, $updatedBlocks, $removedOrMovedBlocks);
    }

    private function seenState(array $reflection, string $latestRevisionId): array
    {
        $seenRevisionIds = $this->stringList($reflection['seen_revision_ids'] ?? []);
        $latestSeenId = (string) ($reflection['latest_revision_seen_id'] ?? '');
        $seen = $latestSeenId === $latestRevisionId || in_array($latestRevisionId, $seenRevisionIds, true);

        return [
            'seen' => $seen,
            'latest_revision_seen_at' => $seen ? ($reflection['latest_update_seen_at'] ?? null) : null,
        ];
    }

    private function acknowledgementState(array $reflection, string $latestRevisionId): array
    {
        $acknowledgedRevisionIds = $this->stringList($reflection['acknowledged_revision_ids'] ?? []);
        $latestAcknowledgedId = (string) ($reflection['acknowledged_revision_id'] ?? '');
        $acknowledged = $latestAcknowledgedId === $latestRevisionId || in_array($latestRevisionId, $acknowledgedRevisionIds, true);

        return [
            'acknowledged' => $acknowledged,
            'acknowledged_at' => $acknowledged ? ($reflection['acknowledged_at'] ?? null) : null,
        ];
    }

    private function acknowledgementHistory(array $reflection, DailyPlanRevision $revision, array $payload, string $acknowledgedAt): array
    {
        $revisionId = (string) $revision->id;
        $history = array_values(array_filter(
            Arr::wrap($reflection['acknowledgement_history'] ?? []),
            fn ($row): bool => is_array($row) && (string) ($row['revision_id'] ?? '') !== $revisionId
        ));
        $history[] = [
            'revision_id' => $revisionId,
            'revision_number' => (int) $revision->revision_number,
            'acknowledged_at' => $acknowledgedAt,
            'payload' => $payload,
        ];

        return array_slice($history, -20);
    }

    private function appendUniqueString(mixed $values, string $value): array
    {
        return array_values(array_unique(array_filter([
            ...$this->stringList($values),
            $value,
        ])));
    }

    private function stringList(mixed $values): array
    {
        return array_values(array_unique(array_filter(array_map('strval', Arr::wrap($values)))));
    }

    private function playerName(string $playerId): string
    {
        $profile = Profile::query()->where('user_id', $playerId)->first();
        $name = trim(implode(' ', array_filter([
            $profile?->first_name,
            $profile?->last_name,
        ])));

        return $name !== '' ? $name : 'Player';
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
