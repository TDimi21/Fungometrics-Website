<?php

declare(strict_types=1);

namespace App\Services\Planner;

use App\Models\DailyPlan;
use App\Services\Intelligence\PracticePlanUpdateSuggestionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class DailyPlanRepublishReviewService
{
    private const DESTRUCTIVE_TYPES = ['remove_block', 'replace_block'];

    public function __construct(
        private readonly PracticePlanUpdateSuggestionService $suggestionService,
        private readonly DailyPlanRevisionService $revisionService,
    ) {
    }

    public function buildReviewPackage(string $dailyPlanId, array $suggestionIds = [], array $options = []): array
    {
        try {
            $plan = $this->plan($dailyPlanId);
            if (! $plan) {
                return $this->failedPackage($dailyPlanId, 'Daily plan not found.');
            }

            $suggestionPayload = $this->suggestionService->suggestUpdatesForDailyPlan($dailyPlanId, [
                'days' => $this->days($options['days'] ?? 365),
            ]);

            $suggestions = $this->selectedSuggestions($suggestionPayload['suggestions'] ?? [], $suggestionIds);
            $completedMap = $this->completedItemMap($plan->progress?->toArray() ?? []);
            $editableChanges = $this->editableChanges($suggestions, $completedMap);
            $lockedBlocks = $this->lockedBlocks($editableChanges);
            $progressWarnings = $this->progressWarnings($lockedBlocks);
            $minutesBefore = $this->planMinutes($plan->toArray());
            $minutesAfter = $this->estimatedMinutesAfter($minutesBefore, $editableChanges);
            $hasApplicableChange = collect($editableChanges)->contains(fn (array $change): bool => empty($change['blocked_reason']));
            $requiresRepublish = $plan->status === 'published'
                || collect($editableChanges)->contains(fn (array $change): bool => (bool) ($change['requires_republish'] ?? false));

            return [
                'daily_plan_id' => (string) $plan->id,
                'team_id' => $plan->team_id ? (string) $plan->team_id : null,
                'review_status' => $this->reviewStatus($editableChanges, $hasApplicableChange),
                'current_plan' => $suggestionPayload['current_plan'] ?? $this->currentPlanSummary($plan),
                'suggested_plan' => $suggestionPayload['latest_suggested_plan'] ?? [],
                'editable_changes' => $editableChanges,
                'locked_blocks' => $lockedBlocks,
                'progress_warnings' => $progressWarnings,
                'estimated_minutes_before' => $minutesBefore,
                'estimated_minutes_after' => $minutesAfter,
                'minutes_delta' => $minutesBefore !== null && $minutesAfter !== null ? $minutesAfter - $minutesBefore : null,
                'requires_republish' => $requiresRepublish,
                'can_apply' => $hasApplicableChange,
                'can_republish' => $requiresRepublish && $hasApplicableChange,
                'warnings' => array_values(array_filter([
                    ...($suggestionPayload['warnings'] ?? []),
                    ...$progressWarnings,
                ])),
                'evidence' => [
                    'days' => $this->days($options['days'] ?? 365),
                    'suggestion_count' => count($suggestionPayload['suggestions'] ?? []),
                    'selected_suggestion_count' => count($suggestions),
                    'editable_change_count' => count($editableChanges),
                    'locked_block_count' => count($lockedBlocks),
                    'assignment_count' => $plan->assignments?->count() ?? 0,
                    'progress_row_count' => $plan->progress?->count() ?? 0,
                    'completed_item_count' => count($completedMap),
                    'apply_mode' => 'coach_review_required',
                ],
            ];
        } catch (Throwable $exception) {
            return [
                ...$this->failedPackage($dailyPlanId, 'Republish review package could not be built.'),
                'warnings' => [$exception->getMessage()],
            ];
        }
    }

    public function previewEditedPlan(string $dailyPlanId, array $edits = [], array $options = []): array
    {
        $suggestionIds = $this->suggestionIdsFromOptions($options, $edits);
        $package = $this->buildReviewPackage($dailyPlanId, $suggestionIds, $options);
        if (($package['review_status'] ?? null) === 'failed') {
            return $package;
        }

        $plan = $this->plan($dailyPlanId);
        if (! $plan) {
            return $this->failedPackage($dailyPlanId, 'Daily plan not found.');
        }

        $changes = $this->mergeCoachEdits($package['editable_changes'] ?? [], $edits);
        $result = $this->applyChangesToBuckets(is_array($plan->buckets) ? $plan->buckets : [], $changes, false);
        $previewPlan = [
            ...$plan->toArray(),
            'buckets' => $result['buckets'],
            'estimated_minutes' => $this->bucketMinutes($result['buckets']),
        ];

        return [
            ...$package,
            'review_status' => empty($result['blocked']) ? ($package['review_status'] ?? 'needs_review') : 'needs_review',
            'editable_changes' => $changes,
            'preview_status' => 'preview_ready',
            'preview_plan' => $previewPlan,
            'applied_preview_changes' => $result['applied'],
            'skipped_preview_changes' => $result['skipped'],
            'diff_summary' => $this->revisionService->buildPlanDiff($plan->toArray(), $previewPlan),
            'warnings' => array_values(array_unique([
                ...($package['warnings'] ?? []),
                ...($result['warnings'] ?? []),
            ])),
        ];
    }

    public function applyCoachApprovedEdits(string $dailyPlanId, array $approvedEdits, ?string $approvedByUserId = null, array $options = []): array
    {
        $plan = $this->plan($dailyPlanId);
        if (! $plan) {
            return [
                ...$this->failedPackage($dailyPlanId, 'Daily plan not found.'),
                'ok' => false,
                'apply_status' => 'failed',
            ];
        }

        $republish = (bool) ($options['republish'] ?? false);
        $review = $this->previewEditedPlan($dailyPlanId, $approvedEdits, $options);
        if (($review['review_status'] ?? null) === 'failed') {
            return [
                ...$review,
                'ok' => false,
                'apply_status' => 'failed',
            ];
        }

        if ($plan->status === 'published' && ! $republish) {
            return [
                ...$review,
                'ok' => false,
                'apply_status' => 'blocked',
                'review_status' => 'blocked',
                'message' => 'Republish is required before players see the updated plan.',
                'warnings' => array_values(array_unique([
                    ...($review['warnings'] ?? []),
                    'Published daily plans cannot be silently changed. Use Republish Plan after review.',
                ])),
            ];
        }

        $changes = $this->mergeCoachEdits($review['editable_changes'] ?? [], $approvedEdits);
        $beforeSnapshot = $this->revisionService->snapshotPlan($dailyPlanId);
        $assignmentCountBefore = $plan->assignments?->count() ?? 0;
        $progressCountBefore = $plan->progress?->count() ?? 0;
        $result = ['applied' => [], 'skipped' => [], 'blocked' => [], 'warnings' => [], 'buckets' => is_array($plan->buckets) ? $plan->buckets : []];

        DB::transaction(function () use ($plan, $changes, $republish, &$result): void {
            $result = $this->applyChangesToBuckets(is_array($plan->buckets) ? $plan->buckets : [], $changes, true);
            if (empty($result['applied'])) {
                return;
            }

            $plan->buckets = $result['buckets'];
            $plan->estimated_minutes = $this->bucketMinutes($result['buckets']);
            if ($republish) {
                $plan->status = 'published';
                $plan->published_at = now();
            }
            $plan->updated_at = now();
            $plan->save();
        });

        $plan->refresh()->load(['assignments', 'progress.user']);
        $afterSnapshot = $this->revisionService->snapshotPlan((string) $plan->id);
        $revision = [];
        if (! empty($result['applied'])) {
            $revision = $this->revisionService->createRevision((string) $plan->id, $beforeSnapshot, $afterSnapshot, [
                'source' => 'practice_plan_update_suggestion',
                'change_type' => $republish ? 'republished' : 'suggestions_applied',
                'created_by_user_id' => $approvedByUserId,
                'applied_suggestions' => $result['applied'],
                'reason' => 'Coach approved FMTRX suggested plan updates.',
                'coach_notes' => $options['coach_note'] ?? $options['coach_notes'] ?? null,
            ]);
        }

        return [
            ...$review,
            'ok' => ! empty($result['applied']),
            'apply_status' => ! empty($result['applied']) ? (empty($result['skipped']) && empty($result['blocked']) ? 'applied' : 'partial') : 'skipped',
            'message' => $republish
                ? 'Plan republished. Existing player progress was preserved.'
                : 'Changes saved as a new revision.',
            'daily_plan' => $plan->toArray(),
            'applied_edits' => $result['applied'],
            'skipped_edits' => $result['skipped'],
            'blocked_edits' => $result['blocked'],
            'revision' => $revision,
            'diff_summary' => $revision['diff_summary'] ?? $this->revisionService->buildPlanDiff($beforeSnapshot, $afterSnapshot),
            'warnings' => array_values(array_unique([
                ...($review['warnings'] ?? []),
                ...($result['warnings'] ?? []),
            ])),
            'evidence' => [
                ...($review['evidence'] ?? []),
                'approved_edit_count' => count($approvedEdits),
                'applied_count' => count($result['applied']),
                'skipped_count' => count($result['skipped']),
                'blocked_count' => count($result['blocked']),
                'assignment_count_before' => $assignmentCountBefore,
                'assignment_count_after' => $plan->assignments?->count() ?? 0,
                'progress_row_count_before' => $progressCountBefore,
                'progress_row_count_after' => $plan->progress?->count() ?? 0,
                'assignments_preserved' => $assignmentCountBefore === ($plan->assignments?->count() ?? 0),
                'progress_preserved' => $progressCountBefore === ($plan->progress?->count() ?? 0),
                'revision_record_created' => ($revision['revision_status'] ?? null) === 'created',
            ],
        ];
    }

    public function republishPlan(string $dailyPlanId, ?string $republishedByUserId = null, array $options = []): array
    {
        $approvedEdits = is_array($options['approved_edits'] ?? null) ? $options['approved_edits'] : [];
        if (! empty($approvedEdits)) {
            return $this->applyCoachApprovedEdits($dailyPlanId, $approvedEdits, $republishedByUserId, [
                ...$options,
                'republish' => true,
            ]);
        }

        $plan = $this->plan($dailyPlanId);
        if (! $plan) {
            return [
                ...$this->failedPackage($dailyPlanId, 'Daily plan not found.'),
                'ok' => false,
                'republish_status' => 'failed',
            ];
        }

        $beforeSnapshot = $this->revisionService->snapshotPlan($dailyPlanId);
        $assignmentCountBefore = $plan->assignments?->count() ?? 0;
        $progressCountBefore = $plan->progress?->count() ?? 0;

        $plan->status = 'published';
        $plan->published_at = now();
        $plan->updated_at = now();
        $plan->save();
        $plan->refresh()->load(['assignments', 'progress.user']);

        $afterSnapshot = $this->revisionService->snapshotPlan((string) $plan->id);
        $revision = $this->revisionService->createRevision((string) $plan->id, $beforeSnapshot, $afterSnapshot, [
            'source' => 'coach_republish',
            'change_type' => 'republished',
            'created_by_user_id' => $republishedByUserId,
            'applied_suggestions' => [],
            'reason' => $options['coach_note'] ?? 'Coach republished the reviewed Daily Plan.',
        ]);

        return [
            'ok' => true,
            'daily_plan_id' => (string) $plan->id,
            'team_id' => $plan->team_id ? (string) $plan->team_id : null,
            'republish_status' => 'republished',
            'message' => 'Plan republished. Existing player progress was preserved.',
            'daily_plan' => $plan->toArray(),
            'revision' => $revision,
            'warnings' => [],
            'evidence' => [
                'notify_players' => (bool) ($options['notify_players'] ?? false),
                'assignment_count_before' => $assignmentCountBefore,
                'assignment_count_after' => $plan->assignments?->count() ?? 0,
                'progress_row_count_before' => $progressCountBefore,
                'progress_row_count_after' => $plan->progress?->count() ?? 0,
                'assignments_preserved' => $assignmentCountBefore === ($plan->assignments?->count() ?? 0),
                'progress_preserved' => $progressCountBefore === ($plan->progress?->count() ?? 0),
            ],
        ];
    }

    private function plan(string $dailyPlanId): ?DailyPlan
    {
        return DailyPlan::query()
            ->with(['assignments', 'progress.user'])
            ->find($dailyPlanId);
    }

    private function selectedSuggestions(array $suggestions, array $suggestionIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $suggestionIds))));
        if (empty($ids)) {
            return array_values(array_filter($suggestions, fn ($suggestion): bool => is_array($suggestion)));
        }

        return collect($suggestions)
            ->filter(fn ($suggestion): bool => is_array($suggestion) && in_array((string) ($suggestion['suggestion_id'] ?? ''), $ids, true))
            ->values()
            ->all();
    }

    private function editableChanges(array $suggestions, array $completedMap): array
    {
        return collect($suggestions)
            ->map(fn (array $suggestion): array => $this->editableChange($suggestion, $completedMap))
            ->values()
            ->all();
    }

    private function editableChange(array $suggestion, array $completedMap): array
    {
        $type = (string) ($suggestion['type'] ?? 'update_note');
        $current = is_array($suggestion['current_block'] ?? null) ? $suggestion['current_block'] : [];
        $suggested = is_array($suggestion['suggested_block'] ?? null) ? $suggestion['suggested_block'] : [];
        $blockId = (string) ($current['id'] ?? '');
        $completedPlayers = $blockId !== '' ? ($completedMap[$blockId] ?? []) : [];
        $blocked = ! empty($completedPlayers) && in_array($type, self::DESTRUCTIVE_TYPES, true);

        return [
            'change_id' => (string) ($suggestion['suggestion_id'] ?? Str::slug($type.'_'.($suggestion['title'] ?? Str::uuid()), '_')),
            'suggestion_id' => (string) ($suggestion['suggestion_id'] ?? ''),
            'type' => $type,
            'priority' => (string) ($suggestion['priority'] ?? 'low'),
            'title' => (string) ($suggestion['title'] ?? 'Daily Plan Update'),
            'why' => (string) ($suggestion['why'] ?? $suggestion['description'] ?? 'FMTRX suggested this plan update.'),
            'current_block' => $current,
            'suggested_block' => $suggested,
            'coach_editable_fields' => $this->editableFields($type, $blocked),
            'edited_block' => $this->defaultEditedBlock($suggestion),
            'requires_republish' => (bool) ($suggestion['requires_republish'] ?? false),
            'blocked_reason' => $blocked ? 'Player progress already exists for this block.' : null,
            'completed_players' => $completedPlayers,
            'allowed_actions' => $blocked ? ['keep', 'add_note', 'move_future_only'] : ['apply', 'edit', 'skip'],
        ];
    }

    private function defaultEditedBlock(array $suggestion): array
    {
        $suggested = is_array($suggestion['suggested_block'] ?? null) ? $suggestion['suggested_block'] : [];
        $current = is_array($suggestion['current_block'] ?? null) ? $suggestion['current_block'] : [];

        return ! empty($suggested) ? $suggested : $current;
    }

    private function editableFields(string $type, bool $blocked): array
    {
        if ($blocked) {
            return ['coach_notes'];
        }

        return match ($type) {
            'remove_block' => ['coach_notes'],
            'update_duration' => ['duration_minutes', 'coach_notes'],
            'update_metrics' => ['metrics_to_collect', 'coach_notes'],
            'update_note' => ['description', 'instructions', 'coach_notes'],
            default => ['title', 'duration_minutes', 'description', 'instructions', 'players', 'metrics_to_collect', 'coach_notes'],
        };
    }

    private function mergeCoachEdits(array $changes, array $edits): array
    {
        $editMap = [];
        foreach ($edits as $edit) {
            if (! is_array($edit)) {
                continue;
            }
            $key = (string) ($edit['change_id'] ?? $edit['suggestion_id'] ?? '');
            if ($key !== '') {
                $editMap[$key] = $edit;
            }
        }

        return array_map(function (array $change) use ($editMap): array {
            $edit = $editMap[$change['change_id']] ?? $editMap[$change['suggestion_id']] ?? null;
            if (! is_array($edit)) {
                return $change;
            }

            $editedBlock = is_array($edit['edited_block'] ?? null)
                ? $edit['edited_block']
                : array_filter($edit, fn ($value, $key): bool => in_array((string) $key, [
                    'title',
                    'duration_minutes',
                    'description',
                    'instructions',
                    'players',
                    'metrics_to_collect',
                    'coach_notes',
                ], true), ARRAY_FILTER_USE_BOTH);

            $change['edited_block'] = [
                ...($change['edited_block'] ?? []),
                ...$editedBlock,
            ];
            $change['coach_edited_fields'] = array_values(array_keys($editedBlock));

            return $change;
        }, $changes);
    }

    private function applyChangesToBuckets(array $buckets, array $changes, bool $persisting): array
    {
        $applied = [];
        $skipped = [];
        $blocked = [];
        $warnings = [];

        foreach ($changes as $change) {
            if (! is_array($change)) {
                continue;
            }

            if (! empty($change['blocked_reason'])) {
                $blocked[] = $change;
                $warnings[] = ($change['title'] ?? 'This block').' cannot be changed because player progress already exists.';
                continue;
            }

            $changed = $this->applyChange($buckets, $change, $persisting);
            if ($changed) {
                $applied[] = $change;
            } else {
                $skipped[] = $change;
                $warnings[] = 'Could not safely apply '.($change['title'] ?? 'Daily Plan update').'.';
            }
        }

        return compact('buckets', 'applied', 'skipped', 'blocked', 'warnings');
    }

    private function applyChange(array &$buckets, array $change, bool $persisting): bool
    {
        $type = (string) ($change['type'] ?? '');
        $current = is_array($change['current_block'] ?? null) ? $change['current_block'] : [];
        $edited = is_array($change['edited_block'] ?? null) ? $change['edited_block'] : [];
        $itemId = (string) ($current['id'] ?? '');

        return match ($type) {
            'add_block' => $this->addBlock($buckets, $edited, $change, $persisting),
            'remove_block' => $this->removeItem($buckets, $itemId),
            'replace_block' => $this->replaceItem($buckets, $itemId, $edited, $change, $persisting),
            'update_duration' => $this->updateItem($buckets, $itemId, fn (array $item): array => [
                ...$item,
                'durationSec' => max(60, $this->durationMinutes($edited) * 60),
                'benchmark_update_evidence' => $this->updateEvidence($item, $change, $persisting),
            ]),
            'update_metrics' => $this->updateItem($buckets, $itemId, fn (array $item): array => [
                ...$item,
                'relatedMetrics' => $this->metricKeys($edited['metrics_to_collect'] ?? $edited['relatedMetrics'] ?? []),
                'benchmark_update_evidence' => $this->updateEvidence($item, $change, $persisting),
            ]),
            'update_note' => $this->updateItem($buckets, $itemId, fn (array $item): array => [
                ...$item,
                'instructions' => (string) ($edited['instructions'] ?? $edited['description'] ?? $item['instructions'] ?? ''),
                'coachCue' => (string) ($edited['coach_notes'] ?? $edited['why'] ?? $item['coachCue'] ?? ''),
                'note' => (string) ($edited['coach_notes'] ?? $edited['description'] ?? $item['note'] ?? ''),
                'benchmark_update_evidence' => $this->updateEvidence($item, $change, $persisting),
            ]),
            'reorder_block' => $this->moveItemToBucketFront($buckets, $itemId),
            'move_to_next_session' => true,
            default => false,
        };
    }

    private function addBlock(array &$buckets, array $block, array $change, bool $persisting): bool
    {
        if (empty($block)) {
            return false;
        }

        $item = $this->blockToItem($block, $change, $persisting);
        if ($this->itemExists($buckets, $item)) {
            return false;
        }

        $bucketType = $this->bucketTypeForBlock($block);
        $index = $this->ensureBucket($buckets, $bucketType);
        $buckets[$index]['items'][] = $item;

        return true;
    }

    private function removeItem(array &$buckets, string $itemId): bool
    {
        if ($itemId === '') {
            return false;
        }

        foreach ($buckets as &$bucket) {
            $items = is_array($bucket['items'] ?? null) ? $bucket['items'] : [];
            $filtered = array_values(array_filter($items, fn ($item): bool => ! is_array($item) || (string) ($item['id'] ?? '') !== $itemId));
            if (count($filtered) !== count($items)) {
                $bucket['items'] = $filtered;

                return true;
            }
        }

        return false;
    }

    private function replaceItem(array &$buckets, string $itemId, array $block, array $change, bool $persisting): bool
    {
        if ($itemId === '' || empty($block)) {
            return false;
        }

        foreach ($buckets as &$bucket) {
            foreach (($bucket['items'] ?? []) as $index => $item) {
                if (! is_array($item) || (string) ($item['id'] ?? '') !== $itemId) {
                    continue;
                }

                $bucketsItem = [
                    ...$this->blockToItem($block, $change, $persisting),
                    'id' => $itemId,
                    'benchmark_update_evidence' => $this->updateEvidence($item, $change, $persisting),
                ];
                $bucket['items'][$index] = $bucketsItem;

                return true;
            }
        }

        return false;
    }

    private function updateItem(array &$buckets, string $itemId, callable $updater): bool
    {
        if ($itemId === '') {
            return false;
        }

        foreach ($buckets as &$bucket) {
            foreach (($bucket['items'] ?? []) as $index => $item) {
                if (! is_array($item) || (string) ($item['id'] ?? '') !== $itemId) {
                    continue;
                }

                $bucket['items'][$index] = $updater($item);

                return true;
            }
        }

        return false;
    }

    private function moveItemToBucketFront(array &$buckets, string $itemId): bool
    {
        if ($itemId === '') {
            return false;
        }

        foreach ($buckets as &$bucket) {
            foreach (($bucket['items'] ?? []) as $index => $item) {
                if (! is_array($item) || (string) ($item['id'] ?? '') !== $itemId) {
                    continue;
                }

                unset($bucket['items'][$index]);
                array_unshift($bucket['items'], $item);
                $bucket['items'] = array_values($bucket['items']);

                return true;
            }
        }

        return false;
    }

    private function blockToItem(array $block, array $change, bool $persisting): array
    {
        $duration = max(1, $this->durationMinutes($block));
        $bucketType = $this->bucketTypeForBlock($block);
        $throwing = in_array($bucketType, ['throwing', 'pitching'], true);

        return [
            'id' => 'item_benchmark_'.Str::slug((string) ($block['temporary_key'] ?? $change['change_id'] ?? $block['title'] ?? Str::uuid()), '_'),
            'drillId' => null,
            'name' => (string) ($block['title'] ?? $block['name'] ?? $change['title'] ?? 'FMTRX Practice Block'),
            'instructions' => (string) ($block['instructions'] ?? $block['description'] ?? ''),
            'coachCue' => (string) ($block['coach_notes'] ?? $block['why'] ?? $change['why'] ?? ''),
            'durationSec' => $duration * 60,
            'throws' => $throwing ? max(8, min(30, $duration)) : null,
            'required' => true,
            'workloadType' => $throwing ? 'throwing' : 'time',
            'bucket' => $bucketType,
            'subcategory' => 'Benchmark Intelligence',
            'categoryGroup' => 'FMTRX Benchmark',
            'baseballCorrelation' => (string) ($block['category'] ?? ''),
            'baseballCorrelations' => array_values(array_filter([$block['category'] ?? null])),
            'relatedMetrics' => $this->metricKeys($block['metrics_to_collect'] ?? $block['relatedMetrics'] ?? []),
            'players' => is_array($block['players'] ?? null) ? $block['players'] : [],
            'benchmark_task_type' => $this->taskTypeForBlock($block),
            'benchmark_task_temporary_key' => $block['temporary_key'] ?? $change['change_id'] ?? null,
            'tags' => ['benchmark-generated', 'coach-reviewed'],
            'note' => (string) ($block['coach_notes'] ?? $block['description'] ?? ''),
            'source' => 'coach_reviewed_practice_plan_update',
            'benchmark_update_evidence' => [
                'change_id' => $change['change_id'] ?? null,
                'suggestion_id' => $change['suggestion_id'] ?? null,
                'persisted_at' => $persisting ? now()->toIso8601String() : null,
            ],
        ];
    }

    private function updateEvidence(array $item, array $change, bool $persisting): array
    {
        return [
            ...(is_array($item['benchmark_update_evidence'] ?? null) ? $item['benchmark_update_evidence'] : []),
            'change_id' => $change['change_id'] ?? null,
            'suggestion_id' => $change['suggestion_id'] ?? null,
            'coach_edited_fields' => $change['coach_edited_fields'] ?? [],
            'updated_at' => $persisting ? now()->toIso8601String() : null,
        ];
    }

    private function itemExists(array $buckets, array $newItem): bool
    {
        $newKey = $this->token($newItem['benchmark_task_temporary_key'] ?? $newItem['name'] ?? null);
        foreach ($buckets as $bucket) {
            foreach (($bucket['items'] ?? []) as $item) {
                if (! is_array($item)) {
                    continue;
                }

                if ((string) ($item['id'] ?? '') === (string) ($newItem['id'] ?? '')) {
                    return true;
                }

                $itemKey = $this->token($item['benchmark_task_temporary_key'] ?? $item['name'] ?? $item['title'] ?? null);
                if ($newKey !== '' && $newKey === $itemKey) {
                    return true;
                }
            }
        }

        return false;
    }

    private function ensureBucket(array &$buckets, string $bucketType): int
    {
        foreach ($buckets as $index => $bucket) {
            if (is_array($bucket) && ($bucket['type'] ?? null) === $bucketType) {
                $buckets[$index]['items'] = is_array($buckets[$index]['items'] ?? null) ? $buckets[$index]['items'] : [];

                return $index;
            }
        }

        $buckets[] = [
            'type' => $bucketType,
            'title' => Str::of($bucketType)->replace('_', ' ')->title()->toString(),
            'kind' => 'content',
            'items' => [],
            'note' => '',
            'source' => 'coach_reviewed_practice_plan_update',
        ];

        return array_key_last($buckets);
    }

    private function lockedBlocks(array $changes): array
    {
        return collect($changes)
            ->filter(fn (array $change): bool => ! empty($change['blocked_reason']))
            ->map(function (array $change): array {
                $current = $change['current_block'] ?? [];

                return [
                    'block_id' => $current['id'] ?? null,
                    'title' => $current['name'] ?? $current['title'] ?? $change['title'] ?? 'Completed Block',
                    'reason' => $change['blocked_reason'],
                    'completed_players' => $change['completed_players'] ?? [],
                    'allowed_actions' => ['keep', 'add_note', 'move_future_only'],
                    'change_id' => $change['change_id'],
                ];
            })
            ->values()
            ->all();
    }

    private function progressWarnings(array $lockedBlocks): array
    {
        return collect($lockedBlocks)
            ->map(fn (array $block): string => ($block['title'] ?? 'This block').' is locked because player progress already exists.')
            ->values()
            ->all();
    }

    private function completedItemMap(array $progressRows): array
    {
        $map = [];
        foreach ($progressRows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $items = is_array($row['items'] ?? null) ? $row['items'] : [];
            $rowComplete = ! empty($row['completed_at'] ?? null);
            $player = [
                'player_id' => $row['user_id'] ?? null,
                'player_name' => $row['user']['name'] ?? $row['user']['profile']['name'] ?? $row['user_id'] ?? 'Player',
            ];

            foreach ($items as $key => $item) {
                if (! is_array($item)) {
                    continue;
                }

                if (! $rowComplete && ! filter_var($item['done'] ?? $item['completed'] ?? false, FILTER_VALIDATE_BOOL)) {
                    continue;
                }

                $id = (string) ($item['id'] ?? $key);
                if ($id === '') {
                    continue;
                }

                $map[$id] ??= [];
                $map[$id][$player['player_id'] ?? count($map[$id])] = $player;
            }
        }

        return array_map(fn (array $players): array => array_values($players), $map);
    }

    private function suggestionIdsFromOptions(array $options, array $edits): array
    {
        $ids = is_array($options['suggestion_ids'] ?? null) ? $options['suggestion_ids'] : [];
        foreach ($edits as $edit) {
            if (is_array($edit) && ! empty($edit['suggestion_id'])) {
                $ids[] = $edit['suggestion_id'];
            }
        }

        return array_values(array_unique(array_filter(array_map('strval', $ids))));
    }

    private function reviewStatus(array $changes, bool $hasApplicableChange): string
    {
        if (empty($changes)) {
            return 'ready';
        }

        return $hasApplicableChange ? 'needs_review' : 'blocked';
    }

    private function currentPlanSummary(DailyPlan $plan): array
    {
        return [
            'id' => (string) $plan->id,
            'team_id' => $plan->team_id,
            'name' => $plan->name,
            'status' => $plan->status,
            'primary_goal' => $plan->primary_goal,
            'estimated_minutes' => $plan->estimated_minutes,
            'published_at' => optional($plan->published_at)->toIso8601String(),
        ];
    }

    private function failedPackage(string $dailyPlanId, string $message): array
    {
        return [
            'daily_plan_id' => $dailyPlanId,
            'team_id' => null,
            'review_status' => 'failed',
            'current_plan' => [],
            'suggested_plan' => [],
            'editable_changes' => [],
            'locked_blocks' => [],
            'progress_warnings' => [],
            'estimated_minutes_before' => null,
            'estimated_minutes_after' => null,
            'minutes_delta' => null,
            'requires_republish' => false,
            'can_apply' => false,
            'can_republish' => false,
            'warnings' => [$message],
            'evidence' => [],
        ];
    }

    private function estimatedMinutesAfter(?int $minutesBefore, array $changes): ?int
    {
        if ($minutesBefore === null) {
            return null;
        }

        $delta = collect($changes)
            ->filter(fn (array $change): bool => empty($change['blocked_reason']))
            ->sum(fn (array $change): int => (int) ($change['estimated_minutes_delta'] ?? $this->changeMinutesDelta($change)));

        return max(0, $minutesBefore + (int) $delta);
    }

    private function changeMinutesDelta(array $change): int
    {
        $type = (string) ($change['type'] ?? '');
        $current = is_array($change['current_block'] ?? null) ? $change['current_block'] : [];
        $edited = is_array($change['edited_block'] ?? null) ? $change['edited_block'] : [];

        return match ($type) {
            'add_block' => $this->durationMinutes($edited),
            'remove_block' => -1 * $this->durationMinutes($current),
            'replace_block', 'update_duration' => $this->durationMinutes($edited) - $this->durationMinutes($current),
            default => 0,
        };
    }

    private function planMinutes(array $plan): ?int
    {
        if (is_numeric($plan['estimated_minutes'] ?? null)) {
            return (int) $plan['estimated_minutes'];
        }

        $buckets = is_array($plan['buckets'] ?? null) ? $plan['buckets'] : [];
        if (empty($buckets)) {
            return null;
        }

        return $this->bucketMinutes($buckets);
    }

    private function bucketMinutes(array $buckets): int
    {
        $minutes = 0;
        foreach ($buckets as $bucket) {
            foreach (($bucket['items'] ?? []) as $item) {
                if (is_array($item)) {
                    $minutes += $this->durationMinutes($item);
                }
            }
        }

        return $minutes;
    }

    private function durationMinutes(array $block): int
    {
        if (is_numeric($block['duration_minutes'] ?? null)) {
            return (int) $block['duration_minutes'];
        }

        if (is_numeric($block['durationSec'] ?? null)) {
            return (int) ceil(((int) $block['durationSec']) / 60);
        }

        return 0;
    }

    private function bucketTypeForBlock(array $block): string
    {
        $category = $this->token($block['category'] ?? $block['bucket'] ?? $block['bucket_type'] ?? null);
        if (str_contains($category, 'pitch')) {
            return 'pitching';
        }
        if (str_contains($category, 'throw')) {
            return 'throwing';
        }
        if (str_contains($category, 'hit') || str_contains($category, 'barrel') || str_contains($category, 'exit')) {
            return 'hitting';
        }
        if (str_contains($category, 'strength')) {
            return 'strength_primary';
        }

        return $category !== '' ? $category : 'education';
    }

    private function taskTypeForBlock(array $block): ?string
    {
        $metrics = $this->metricKeys($block['metrics_to_collect'] ?? $block['relatedMetrics'] ?? []);
        if (empty($metrics)) {
            return null;
        }

        return $metrics[0];
    }

    private function metricKeys(mixed $metrics): array
    {
        return collect(is_array($metrics) ? $metrics : [])
            ->map(function ($metric): ?string {
                if (is_array($metric)) {
                    $metric = $metric['metric_key'] ?? $metric['key'] ?? $metric['display_name'] ?? null;
                }

                $metric = trim((string) ($metric ?? ''));

                return $metric !== '' ? $this->token($metric) : null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function token(mixed $value): string
    {
        return Str::of((string) ($value ?? ''))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();
    }

    private function days(mixed $days): int
    {
        return max(7, min(365, (int) $days));
    }
}
