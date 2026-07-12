<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use App\Models\DailyPlan;
use App\Models\DailyPlanProgress;
use App\Services\Planner\DailyPlanRevisionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class PracticePlanUpdateSuggestionService
{
    private const BUCKET_TITLES = [
        'daily_readiness' => 'Daily Readiness',
        'movement_prep' => 'Movement Preparation',
        'arm_care' => 'Arm Care',
        'throwing' => 'Throwing',
        'pitching' => 'Pitching Development',
        'hitting' => 'Hitting',
        'speed_agility' => 'Speed and Agility',
        'strength_primary' => 'Primary Strength',
        'conditioning' => 'Conditioning',
        'recovery' => 'Recovery',
        'education' => 'Education',
        'coach_notes' => 'Coach Notes',
    ];

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

    public function __construct(
        private readonly CoachActionPracticePlanner $coachActionPracticePlanner,
        private readonly DailyPlanRevisionService $dailyPlanRevisionService,
    ) {
    }

    public function suggestUpdatesForDailyPlan(string $dailyPlanId, array $options = []): array
    {
        $days = $this->days($options['days'] ?? 365);

        try {
            $plan = DailyPlan::query()->with(['assignments', 'progress'])->find($dailyPlanId);
            if (! $plan) {
                return $this->emptyResult(null, $dailyPlanId, 'none', 'No saved daily plan found to compare.');
            }

            $suggestedPlan = is_array($options['latest_suggested_plan'] ?? null)
                ? $options['latest_suggested_plan']
                : $this->coachActionPracticePlanner->buildPracticePlanFromCoachActions((string) $plan->team_id, $days, [
                    'max_minutes' => $options['max_minutes'] ?? 90,
                ]);

            return $this->compareDailyPlanToSuggestedPlan($plan->toArray(), $suggestedPlan, [
                ...$options,
                'team_id' => (string) $plan->team_id,
                'daily_plan_id' => (string) $plan->id,
                'days' => $days,
                'progress_rows' => $plan->progress?->toArray() ?? [],
            ]);
        } catch (Throwable $exception) {
            return [
                ...$this->emptyResult(null, $dailyPlanId, 'failed', 'Practice plan suggestions could not be generated.'),
                'warnings' => [$exception->getMessage()],
                'evidence' => [
                    'exception' => class_basename($exception),
                    'days' => $days,
                ],
            ];
        }
    }

    public function suggestUpdatesForTeam(string $teamId, int $days = 365, array $options = []): array
    {
        $days = $this->days($days);
        $plan = $this->latestDailyPlanForTeam($teamId);

        if (! $plan) {
            return $this->emptyResult($teamId, null, 'none', 'No saved daily plan found to compare.');
        }

        return $this->suggestUpdatesForDailyPlan((string) $plan->id, [
            ...$options,
            'days' => $days,
        ]);
    }

    public function compareDailyPlanToSuggestedPlan(array $dailyPlan, array $suggestedPlan, array $options = []): array
    {
        $teamId = $this->nullableString($options['team_id'] ?? $dailyPlan['team_id'] ?? null);
        $dailyPlanId = $this->nullableString($options['daily_plan_id'] ?? $dailyPlan['id'] ?? null);
        $warnings = [];

        $currentItems = $this->currentPlanItems($dailyPlan);
        $completedItemIds = $this->completedItemIds($options['progress_rows'] ?? $dailyPlan['progress'] ?? []);
        $suggestedBlocks = $this->suggestedBlocks($suggestedPlan);
        $currentFocus = $this->nullableString($dailyPlan['primary_goal'] ?? null);
        $latestFocus = $this->nullableString($suggestedPlan['priority_focus'] ?? $suggestedPlan['primary_focus'] ?? null);
        $focusChange = [
            'changed' => $currentFocus !== null && $latestFocus !== null && $this->token($currentFocus) !== $this->token($latestFocus),
            'current_focus' => $currentFocus,
            'latest_focus' => $latestFocus,
            'reason' => '',
        ];

        if ($focusChange['changed']) {
            $focusChange['reason'] = 'The saved plan focus no longer matches the latest re-ranked coach action focus.';
        } else {
            $focusChange['reason'] = 'The saved plan focus still matches the latest coach action focus.';
        }

        $suggestions = [];
        $matchedCurrentKeys = [];

        foreach ($suggestedBlocks as $block) {
            $match = $this->bestCurrentMatch($block, $currentItems);
            if (! $match) {
                $suggestions[] = $this->suggestion('add_block', $block, null, [
                    'priority' => $block['priority'] ?? 'medium',
                    'title' => 'Add '.$this->blockTitle($block),
                    'description' => 'Add this latest FMTRX practice block to the saved Daily Plan.',
                    'why' => $block['why'] ?? 'The latest coach action ranking recommends this block.',
                    'estimated_minutes_delta' => (int) ($block['duration_minutes'] ?? 0),
                    'requires_republish' => ($dailyPlan['status'] ?? 'draft') === 'published',
                    'source' => 'coach_action_rerank',
                ]);

                continue;
            }

            $matchedCurrentKeys[$match['match_key']] = true;
            $current = $match['item'];
            $currentCompleted = in_array((string) ($current['id'] ?? ''), $completedItemIds, true);
            $durationDelta = $this->durationMinutes($block) - $this->itemDurationMinutes($current);
            if (abs($durationDelta) >= 5) {
                $suggestions[] = $this->suggestion('update_duration', $block, $current, [
                    'priority' => 'low',
                    'title' => 'Update '.$this->blockTitle($block).' Duration',
                    'description' => 'Adjust the block length to match the latest practice recommendation.',
                    'why' => 'The latest suggested block length differs from the saved plan by at least five minutes.',
                    'estimated_minutes_delta' => $durationDelta,
                    'requires_republish' => ($dailyPlan['status'] ?? 'draft') === 'published',
                    'source' => 'practice_plan',
                ]);
            }

            $metricChanges = $this->metricDiff($current['relatedMetrics'] ?? [], $block['metrics_to_collect'] ?? []);
            if (! empty($metricChanges['added']) || ! empty($metricChanges['removed'])) {
                $suggestions[] = $this->suggestion('update_metrics', $block, $current, [
                    'priority' => 'medium',
                    'title' => 'Update Metrics for '.$this->blockTitle($block),
                    'description' => 'Update the metric collection list based on trusted data already approved.',
                    'why' => 'The latest benchmark gaps changed after data quality improved.',
                    'requires_republish' => ($dailyPlan['status'] ?? 'draft') === 'published',
                    'source' => 'collection_plan',
                    'evidence' => $metricChanges,
                ]);
            }

            if ($this->noteChanged($current, $block)) {
                $suggestions[] = $this->suggestion('update_note', $block, $current, [
                    'priority' => $currentCompleted ? 'low' : 'medium',
                    'title' => 'Update Coach Notes for '.$this->blockTitle($block),
                    'description' => 'Refresh the why/action language on the saved plan item.',
                    'why' => 'The coach-facing explanation changed with the latest action ranking.',
                    'requires_republish' => ($dailyPlan['status'] ?? 'draft') === 'published',
                    'source' => 'decision_brief',
                ]);
            }
        }

        foreach ($currentItems as $current) {
            if (! $this->isBenchmarkItem($current)) {
                continue;
            }

            if (isset($matchedCurrentKeys[$current['match_key']])) {
                continue;
            }

            $completed = in_array((string) ($current['id'] ?? ''), $completedItemIds, true);
            $type = $completed ? 'move_to_next_session' : 'remove_block';
            $suggestions[] = $this->suggestion($type, [], $current, [
                'priority' => $completed ? 'low' : 'medium',
                'title' => ($completed ? 'Move Future Work for ' : 'Remove ').($current['name'] ?? $current['title'] ?? 'Benchmark Block'),
                'description' => $completed
                    ? 'This block has completion history, so FMTRX will not delete it. Move future work forward instead.'
                    : 'This benchmark block no longer appears in the latest coach action plan.',
                'why' => $completed
                    ? 'Completed player progress must stay attached to the saved plan.'
                    : 'The latest trusted data no longer requires this saved benchmark block.',
                'estimated_minutes_delta' => -1 * $this->itemDurationMinutes($current),
                'requires_republish' => ($dailyPlan['status'] ?? 'draft') === 'published',
                'source' => 'coach_action_rerank',
            ]);
        }

        $suggestions = $this->uniqueSuggestions($suggestions);
        $totalMinutes = (int) ($suggestedPlan['estimated_total_minutes'] ?? array_sum(array_map(fn (array $block): int => $this->durationMinutes($block), $suggestedBlocks)));
        if ($totalMinutes > (int) ($options['max_minutes'] ?? 90)) {
            $suggestions[] = [
                'suggestion_id' => 'move_overflow_to_next_session',
                'type' => 'move_to_next_session',
                'priority' => 'medium',
                'title' => 'Move Lower-Priority Work to Next Session',
                'description' => 'The latest recommended plan is longer than the target practice window.',
                'why' => 'Keeping today under the time cap protects practice quality.',
                'current_block' => [],
                'suggested_block' => [],
                'affected_players' => [],
                'affected_metrics' => [],
                'estimated_minutes_delta' => 0,
                'requires_republish' => ($dailyPlan['status'] ?? 'draft') === 'published',
                'safe_to_auto_apply' => false,
                'source' => 'practice_plan',
            ];
        }

        $status = count($suggestions) > 0 ? 'available' : 'none';

        return [
            'generated_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'daily_plan_id' => $dailyPlanId,
            'suggestion_status' => $status,
            'current_plan' => $this->currentPlanSummary($dailyPlan, $currentItems, $completedItemIds),
            'latest_suggested_plan' => $this->suggestedPlanSummary($suggestedPlan, $suggestedBlocks),
            'focus_change' => $focusChange,
            'suggestions' => $suggestions,
            'summary' => $this->summary($suggestions, $focusChange),
            'requires_coach_review' => true,
            'warnings' => $warnings,
            'evidence' => [
                'days' => $this->days($options['days'] ?? 365),
                'current_item_count' => count($currentItems),
                'suggested_block_count' => count($suggestedBlocks),
                'completed_item_count' => count($completedItemIds),
                'matching_strategy' => ['temporary_key', 'block_id', 'normalized_title', 'category', 'related_metrics', 'source_tags'],
                'apply_mode' => 'coach_approved_only',
            ],
        ];
    }

    public function applyApprovedSuggestions(string $dailyPlanId, array $suggestionIds, ?string $approvedByUserId = null, array $options = []): array
    {
        $suggestionIds = array_values(array_unique(array_filter(array_map('strval', $suggestionIds))));
        $preview = $this->suggestUpdatesForDailyPlan($dailyPlanId, $options);
        $plan = DailyPlan::query()->with('progress')->find($dailyPlanId);

        if (! $plan) {
            return [
                'ok' => false,
                'apply_status' => 'failed',
                'message' => 'Daily plan not found.',
                'daily_plan_id' => $dailyPlanId,
                'warnings' => ['No saved daily plan found to update.'],
                'preview' => $preview,
            ];
        }

        if (empty($suggestionIds)) {
            return [
                'ok' => false,
                'apply_status' => 'no_selection',
                'message' => 'No suggestions were selected.',
                'daily_plan_id' => $dailyPlanId,
                'warnings' => ['Select one or more suggestions before applying updates.'],
                'preview' => $preview,
            ];
        }

        if ($plan->status === 'published' && ! (bool) ($options['republish'] ?? false)) {
            return [
                'ok' => false,
                'apply_status' => 'preview_only',
                'message' => 'Published daily plans require republish approval before updates are applied.',
                'daily_plan_id' => $dailyPlanId,
                'warnings' => ['Apply suggestions is preview-only until republish is confirmed.'],
                'preview' => $preview,
            ];
        }

        $suggestions = collect($preview['suggestions'] ?? [])
            ->filter(fn (array $suggestion): bool => in_array((string) ($suggestion['suggestion_id'] ?? ''), $suggestionIds, true))
            ->values()
            ->all();

        if (empty($suggestions)) {
            return [
                'ok' => false,
                'apply_status' => 'no_matching_suggestions',
                'message' => 'Selected suggestions were not found in the current preview.',
                'daily_plan_id' => $dailyPlanId,
                'warnings' => ['Refresh suggestions and try again.'],
                'preview' => $preview,
            ];
        }

        $beforeSnapshot = $this->dailyPlanRevisionService->snapshotPlan($dailyPlanId);
        $completedItemIds = $this->completedItemIds($plan->progress?->toArray() ?? []);
        $warnings = [];
        $applied = [];
        $skipped = [];

        $updatedBuckets = DB::transaction(function () use ($plan, $suggestions, $completedItemIds, $approvedByUserId, &$warnings, &$applied, &$skipped): array {
            $buckets = is_array($plan->buckets) ? $plan->buckets : [];

            foreach ($suggestions as $suggestion) {
                $type = (string) ($suggestion['type'] ?? '');
                $current = is_array($suggestion['current_block'] ?? null) ? $suggestion['current_block'] : [];
                $block = is_array($suggestion['suggested_block'] ?? null) ? $suggestion['suggested_block'] : [];
                $itemId = (string) ($current['id'] ?? '');

                if ($itemId !== '' && in_array($itemId, $completedItemIds, true) && in_array($type, ['remove_block', 'replace_block'], true)) {
                    $skipped[] = $suggestion;
                    $warnings[] = 'Skipped '.$this->suggestionTitle($suggestion).' because player progress already exists.';
                    continue;
                }

                $changed = match ($type) {
                    'add_block' => $this->addBlock($buckets, $block),
                    'remove_block' => $this->removeItem($buckets, $itemId),
                    'replace_block' => $this->replaceItem($buckets, $itemId, $block),
                    'update_duration' => $this->updateDuration($buckets, $itemId, $block),
                    'update_metrics' => $this->updateMetrics($buckets, $itemId, $block),
                    'update_note' => $this->updateNote($buckets, $itemId, $block),
                    'reorder_block' => $this->moveItemToBucketFront($buckets, $itemId),
                    default => false,
                };

                if (! $changed) {
                    $skipped[] = $suggestion;
                    $warnings[] = 'Could not safely apply '.$this->suggestionTitle($suggestion).'.';
                    continue;
                }

                $applied[] = $suggestion;
            }

            if (! empty($applied)) {
                $plan->buckets = $buckets;
                $plan->updated_at = now();
                if ($plan->status === 'published') {
                    $plan->published_at = now();
                }
                $plan->save();
            }

            return $buckets;
        });

        $plan->refresh()->load(['assignments', 'progress']);
        $afterSnapshot = $this->dailyPlanRevisionService->snapshotPlan((string) $plan->id);
        $revision = [];
        if (! empty($applied)) {
            $revision = $this->dailyPlanRevisionService->createRevision((string) $plan->id, $beforeSnapshot, $afterSnapshot, [
                'source' => 'practice_plan_update_suggestion',
                'change_type' => $plan->status === 'published' ? 'republished' : 'suggestions_applied',
                'created_by_user_id' => $approvedByUserId,
                'applied_suggestions' => $applied,
                'reason' => 'Coach approved FMTRX practice plan update suggestions.',
                'coach_notes' => $options['coach_notes'] ?? null,
            ]);

            if (($revision['revision_status'] ?? null) === 'failed') {
                $warnings[] = 'Daily plan was updated, but revision history could not be saved.';
            }
        }

        return [
            'ok' => ! empty($applied),
            'apply_status' => ! empty($applied) ? (empty($skipped) ? 'applied' : 'partial') : 'skipped',
            'generated_at' => now()->toIso8601String(),
            'team_id' => (string) $plan->team_id,
            'daily_plan_id' => (string) $plan->id,
            'approved_by_user_id' => $approvedByUserId,
            'republished' => $plan->status === 'published',
            'applied_suggestions' => $applied,
            'skipped_suggestions' => $skipped,
            'revision' => $revision,
            'diff_summary' => $revision['diff_summary'] ?? [],
            'warnings' => $warnings,
            'daily_plan' => $plan->toArray(),
            'post_apply_preview' => $this->suggestUpdatesForDailyPlan((string) $plan->id, $options),
            'evidence' => [
                'selected_count' => count($suggestionIds),
                'applied_count' => count($applied),
                'skipped_count' => count($skipped),
                'completed_item_count' => count($completedItemIds),
                'assignments_preserved' => true,
                'progress_preserved' => true,
                'database_records_created' => ($revision['revision_status'] ?? null) === 'created',
                'revision_record_created' => ($revision['revision_status'] ?? null) === 'created',
            ],
        ];
    }

    private function latestDailyPlanForTeam(string $teamId): ?DailyPlan
    {
        return DailyPlan::query()
            ->where('team_id', $teamId)
            ->whereIn('status', ['draft', 'published'])
            ->orderByRaw("case when status = 'draft' then 0 when status = 'published' then 1 else 2 end")
            ->orderByDesc('updated_at')
            ->with(['assignments', 'progress'])
            ->first();
    }

    private function currentPlanItems(array $dailyPlan): array
    {
        $items = [];
        foreach (($dailyPlan['buckets'] ?? []) as $bucketIndex => $bucket) {
            if (! is_array($bucket)) {
                continue;
            }

            foreach (($bucket['items'] ?? []) as $itemIndex => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $metrics = $this->metricKeys($item['relatedMetrics'] ?? []);
                $normalized = [
                    ...$item,
                    'bucket_type' => $bucket['type'] ?? null,
                    'bucket_title' => $bucket['title'] ?? null,
                    'bucket_index' => $bucketIndex,
                    'item_index' => $itemIndex,
                    'relatedMetrics' => $metrics,
                ];
                $normalized['match_key'] = $this->itemMatchKey($normalized);
                $items[] = $normalized;
            }
        }

        return $items;
    }

    private function suggestedBlocks(array $suggestedPlan): array
    {
        return collect($suggestedPlan['practice_blocks'] ?? [])
            ->filter(fn ($block): bool => is_array($block))
            ->map(function (array $block): array {
                $metrics = $this->metricKeys($block['metrics_to_collect'] ?? []);

                return [
                    ...$block,
                    'metrics_to_collect' => $metrics,
                    'match_key' => $this->blockMatchKey($block),
                ];
            })
            ->values()
            ->all();
    }

    private function bestCurrentMatch(array $block, array $currentItems): ?array
    {
        $blockKey = $this->blockMatchKey($block);
        $blockTitle = $this->token($this->blockTitle($block));
        $blockMetrics = $this->metricKeys($block['metrics_to_collect'] ?? []);
        $blockCategory = $this->token($block['category'] ?? null);
        $blockTemp = $this->token($block['temporary_key'] ?? null);

        $best = null;
        $bestScore = 0;
        foreach ($currentItems as $item) {
            $score = 0;
            if ($blockTemp !== '' && $blockTemp === $this->token($item['benchmark_task_temporary_key'] ?? null)) {
                $score += 100;
            }
            if ($blockKey !== '' && $blockKey === ($item['match_key'] ?? '')) {
                $score += 80;
            }
            if ($blockTitle !== '' && $blockTitle === $this->token($item['name'] ?? $item['title'] ?? null)) {
                $score += 40;
            }
            if ($blockCategory !== '' && $blockCategory === $this->token($item['baseballCorrelation'] ?? $item['category'] ?? null)) {
                $score += 10;
            }
            if (! empty(array_intersect($blockMetrics, $item['relatedMetrics'] ?? []))) {
                $score += 25;
            }
            $tags = is_array($item['tags'] ?? null) ? $item['tags'] : [];
            if (in_array('coach_action_practice_plan', array_map([$this, 'token'], $tags), true)) {
                $score += 10;
            }
            if (($item['source'] ?? null) === 'coach_action_practice_plan') {
                $score += 10;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = [
                    'item' => $item,
                    'match_key' => $item['match_key'] ?? $this->itemMatchKey($item),
                    'score' => $score,
                ];
            }
        }

        return $bestScore >= 40 ? $best : null;
    }

    private function suggestion(string $type, array $block, ?array $current, array $overrides = []): array
    {
        $suggestedBlock = ! empty($block) ? $this->blockForPayload($block) : [];
        $currentBlock = is_array($current) ? $this->currentForPayload($current) : [];
        $idBase = implode('|', array_filter([
            $type,
            $suggestedBlock['temporary_key'] ?? null,
            $currentBlock['id'] ?? null,
            $suggestedBlock['title'] ?? $currentBlock['name'] ?? null,
        ]));

        return [
            'suggestion_id' => Str::slug($idBase !== '' ? $idBase : $type.'_'.Str::uuid(), '_'),
            'type' => $type,
            'priority' => $this->normalizePriority((string) ($overrides['priority'] ?? 'medium')),
            'title' => (string) ($overrides['title'] ?? $this->defaultSuggestionTitle($type, $suggestedBlock, $currentBlock)),
            'description' => (string) ($overrides['description'] ?? 'Review this suggested Daily Plan update.'),
            'why' => (string) ($overrides['why'] ?? 'FMTRX detected a change in the latest benchmark action plan.'),
            'current_block' => $currentBlock,
            'suggested_block' => $suggestedBlock,
            'affected_players' => $this->players($block['players'] ?? $current['players'] ?? []),
            'affected_metrics' => $this->metricKeys($block['metrics_to_collect'] ?? $current['relatedMetrics'] ?? []),
            'estimated_minutes_delta' => (int) ($overrides['estimated_minutes_delta'] ?? 0),
            'requires_republish' => (bool) ($overrides['requires_republish'] ?? false),
            'safe_to_auto_apply' => false,
            'source' => (string) ($overrides['source'] ?? 'coach_action_rerank'),
            'evidence' => $overrides['evidence'] ?? [],
        ];
    }

    private function uniqueSuggestions(array $suggestions): array
    {
        $seen = [];
        $unique = [];
        foreach ($suggestions as $suggestion) {
            $id = (string) ($suggestion['suggestion_id'] ?? '');
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $unique[] = $suggestion;
        }

        usort($unique, fn (array $a, array $b): int => $this->priorityRank($b['priority'] ?? 'low') <=> $this->priorityRank($a['priority'] ?? 'low'));

        return array_values($unique);
    }

    private function addBlock(array &$buckets, array $block): bool
    {
        if (empty($block)) {
            return false;
        }

        $item = $this->blockToItem($block);
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
            if (! is_array($bucket)) {
                continue;
            }

            $items = $bucket['items'] ?? [];
            $filtered = array_values(array_filter($items, fn ($item): bool => ! is_array($item) || (string) ($item['id'] ?? '') !== $itemId));
            if (count($filtered) !== count($items)) {
                $bucket['items'] = $filtered;

                return true;
            }
        }

        return false;
    }

    private function replaceItem(array &$buckets, string $itemId, array $block): bool
    {
        if ($itemId === '' || empty($block)) {
            return false;
        }

        foreach ($buckets as &$bucket) {
            foreach (($bucket['items'] ?? []) as $index => $item) {
                if (! is_array($item) || (string) ($item['id'] ?? '') !== $itemId) {
                    continue;
                }

                $bucket['items'][$index] = [
                    ...$this->blockToItem($block),
                    'id' => $itemId,
                    'benchmark_update_evidence' => [
                        'previous_item' => $item,
                        'updated_at' => now()->toIso8601String(),
                    ],
                ];

                return true;
            }
        }

        return false;
    }

    private function updateDuration(array &$buckets, string $itemId, array $block): bool
    {
        return $this->updateItem($buckets, $itemId, function (array $item) use ($block): array {
            $item['durationSec'] = max(60, $this->durationMinutes($block) * 60);
            $item['benchmark_update_evidence']['duration_updated_at'] = now()->toIso8601String();

            return $item;
        });
    }

    private function updateMetrics(array &$buckets, string $itemId, array $block): bool
    {
        return $this->updateItem($buckets, $itemId, function (array $item) use ($block): array {
            $item['relatedMetrics'] = $this->metricKeys($block['metrics_to_collect'] ?? []);
            $item['note'] = $this->blockNote($block);
            $item['benchmark_update_evidence']['metrics_updated_at'] = now()->toIso8601String();

            return $item;
        });
    }

    private function updateNote(array &$buckets, string $itemId, array $block): bool
    {
        return $this->updateItem($buckets, $itemId, function (array $item) use ($block): array {
            $item['instructions'] = (string) ($block['description'] ?? $item['instructions'] ?? '');
            $item['coachCue'] = (string) ($block['why'] ?? $item['coachCue'] ?? '');
            $item['note'] = $this->blockNote($block);
            $item['benchmark_update_evidence']['note_updated_at'] = now()->toIso8601String();

            return $item;
        });
    }

    private function moveItemToBucketFront(array &$buckets, string $itemId): bool
    {
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

    private function blockToItem(array $block): array
    {
        $bucketType = $this->bucketTypeForBlock($block);
        $duration = max(1, $this->durationMinutes($block));
        $throwing = in_array($bucketType, ['throwing', 'pitching'], true);

        return [
            'id' => 'item_benchmark_'.Str::slug((string) ($block['temporary_key'] ?? $block['title'] ?? Str::uuid()), '_'),
            'drillId' => null,
            'name' => $this->blockTitle($block),
            'instructions' => (string) ($block['description'] ?? ''),
            'coachCue' => (string) ($block['why'] ?? ''),
            'durationSec' => $duration * 60,
            'throws' => $throwing ? max(8, min(30, $duration)) : null,
            'required' => true,
            'workloadType' => $throwing ? 'throwing' : 'time',
            'bucket' => $bucketType,
            'subcategory' => 'Benchmark Intelligence',
            'categoryGroup' => 'FMTRX Benchmark',
            'baseballCorrelation' => (string) ($block['category'] ?? ''),
            'baseballCorrelations' => array_values(array_filter([$block['category'] ?? null])),
            'relatedMetrics' => $this->metricKeys($block['metrics_to_collect'] ?? []),
            'benchmark_task_type' => $this->taskTypeForBlock($block),
            'benchmark_task_temporary_key' => $block['temporary_key'] ?? null,
            'tags' => ['benchmark-generated', (string) ($block['source'] ?? 'coach_action')],
            'note' => $this->blockNote($block),
            'source' => 'coach_action_practice_plan',
        ];
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
            'title' => self::BUCKET_TITLES[$bucketType] ?? $this->human($bucketType),
            'kind' => 'content',
            'items' => [],
            'note' => '',
            'source' => 'coach_action_practice_plan',
        ];

        return array_key_last($buckets);
    }

    private function itemExists(array $buckets, array $newItem): bool
    {
        $newKey = $this->itemMatchKey($newItem);
        foreach ($buckets as $bucket) {
            foreach (($bucket['items'] ?? []) as $item) {
                if (! is_array($item)) {
                    continue;
                }

                if ((string) ($item['id'] ?? '') === (string) ($newItem['id'] ?? '')) {
                    return true;
                }

                if ($newKey !== '' && $newKey === $this->itemMatchKey($item)) {
                    return true;
                }
            }
        }

        return false;
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

                $id = (string) ($item['id'] ?? $key);
                if ($id === '') {
                    continue;
                }

                if ($rowComplete || filter_var($item['done'] ?? $item['completed'] ?? false, FILTER_VALIDATE_BOOL)) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    private function currentPlanSummary(array $dailyPlan, array $items, array $completedItemIds): array
    {
        return [
            'id' => $dailyPlan['id'] ?? null,
            'team_id' => $dailyPlan['team_id'] ?? null,
            'name' => $dailyPlan['name'] ?? null,
            'status' => $dailyPlan['status'] ?? null,
            'primary_goal' => $dailyPlan['primary_goal'] ?? null,
            'estimated_minutes' => $dailyPlan['estimated_minutes'] ?? null,
            'item_count' => count($items),
            'completed_item_count' => count($completedItemIds),
            'published_at' => $dailyPlan['published_at'] ?? null,
        ];
    }

    private function suggestedPlanSummary(array $suggestedPlan, array $blocks): array
    {
        return [
            'plan_title' => $suggestedPlan['plan_title'] ?? null,
            'priority_focus' => $suggestedPlan['priority_focus'] ?? null,
            'estimated_total_minutes' => $suggestedPlan['estimated_total_minutes'] ?? array_sum(array_map(fn (array $block): int => $this->durationMinutes($block), $blocks)),
            'block_count' => count($blocks),
            'practice_blocks' => array_map(fn (array $block): array => $this->blockForPayload($block), $blocks),
            'next_session_blocks' => $suggestedPlan['next_session_blocks'] ?? [],
        ];
    }

    private function summary(array $suggestions, array $focusChange): string
    {
        if (empty($suggestions)) {
            return 'Your daily plan is up to date.';
        }

        if ($focusChange['changed'] ?? false) {
            return count($suggestions).' practice plan update suggestion(s) are available because the latest coach action focus changed.';
        }

        return count($suggestions).' practice plan update suggestion(s) are available from the latest benchmark intelligence.';
    }

    private function emptyResult(?string $teamId, ?string $dailyPlanId, string $status, string $summary): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'daily_plan_id' => $dailyPlanId,
            'suggestion_status' => $status,
            'current_plan' => [],
            'latest_suggested_plan' => [],
            'focus_change' => [
                'changed' => false,
                'current_focus' => null,
                'latest_focus' => null,
                'reason' => $summary,
            ],
            'suggestions' => [],
            'summary' => $summary,
            'requires_coach_review' => true,
            'warnings' => $status === 'failed' ? [$summary] : [],
            'evidence' => [],
        ];
    }

    private function blockForPayload(array $block): array
    {
        return [
            'temporary_key' => $block['temporary_key'] ?? null,
            'title' => $this->blockTitle($block),
            'category' => $block['category'] ?? null,
            'priority' => $block['priority'] ?? null,
            'description' => $block['description'] ?? null,
            'why' => $block['why'] ?? null,
            'duration_minutes' => $this->durationMinutes($block),
            'metrics_to_collect' => $this->metricKeys($block['metrics_to_collect'] ?? []),
            'players' => $this->players($block['players'] ?? []),
            'source' => $block['source'] ?? 'coach_action_practice_plan',
        ];
    }

    private function currentForPayload(array $item): array
    {
        return [
            'id' => $item['id'] ?? null,
            'name' => $item['name'] ?? $item['title'] ?? null,
            'bucket_type' => $item['bucket_type'] ?? $item['bucket'] ?? null,
            'duration_minutes' => $this->itemDurationMinutes($item),
            'relatedMetrics' => $this->metricKeys($item['relatedMetrics'] ?? []),
            'benchmark_task_temporary_key' => $item['benchmark_task_temporary_key'] ?? null,
            'source' => $item['source'] ?? null,
        ];
    }

    private function metricDiff(array $current, array $suggested): array
    {
        $current = $this->metricKeys($current);
        $suggested = $this->metricKeys($suggested);

        return [
            'added' => array_values(array_diff($suggested, $current)),
            'removed' => array_values(array_diff($current, $suggested)),
        ];
    }

    private function noteChanged(array $current, array $block): bool
    {
        $currentNote = $this->token(($current['coachCue'] ?? '').' '.($current['instructions'] ?? '').' '.($current['note'] ?? ''));
        $latestNote = $this->token(($block['why'] ?? '').' '.($block['description'] ?? '').' '.$this->blockNote($block));

        return $currentNote !== '' && $latestNote !== '' && $currentNote !== $latestNote;
    }

    private function isBenchmarkItem(array $item): bool
    {
        $source = $this->token($item['source'] ?? null);
        $tags = array_map([$this, 'token'], is_array($item['tags'] ?? null) ? $item['tags'] : []);

        return $source === 'coach_action_practice_plan'
            || in_array('benchmark-generated', $tags, true)
            || in_array('benchmark_generated', $tags, true)
            || ! empty($item['benchmark_task_temporary_key'])
            || ! empty($item['benchmark_task_type'])
            || ! empty($this->metricKeys($item['relatedMetrics'] ?? []));
    }

    private function itemMatchKey(array $item): string
    {
        $temporaryKey = $this->token($item['benchmark_task_temporary_key'] ?? null);
        if ($temporaryKey !== '') {
            return 'temporary_key:'.$temporaryKey;
        }

        $name = $this->token($item['name'] ?? $item['title'] ?? null);
        if ($name !== '') {
            return 'title:'.$name;
        }

        return '';
    }

    private function blockMatchKey(array $block): string
    {
        $temporaryKey = $this->token($block['temporary_key'] ?? null);
        if ($temporaryKey !== '') {
            return 'temporary_key:'.$temporaryKey;
        }

        $title = $this->token($this->blockTitle($block));
        if ($title !== '') {
            return 'title:'.$title;
        }

        return '';
    }

    private function bucketTypeForBlock(array $block): string
    {
        $key = (string) ($block['temporary_key'] ?? '');
        if (isset(self::BUCKET_MAP[$key])) {
            return self::BUCKET_MAP[$key];
        }

        return match ($this->token($block['category'] ?? null)) {
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

    private function blockNote(array $block): string
    {
        $metrics = implode(', ', array_map([$this, 'human'], $this->metricKeys($block['metrics_to_collect'] ?? [])));
        $instructions = implode(' ', array_values(array_filter($block['instructions'] ?? [])));

        return trim(sprintf(
            '%s%s%s',
            (string) ($block['why'] ?? ''),
            $metrics !== '' ? ' Metrics: '.$metrics.'.' : '',
            $instructions !== '' ? ' Instructions: '.$instructions : '',
        ));
    }

    private function blockTitle(array $block): string
    {
        return (string) ($block['title'] ?? $block['name'] ?? 'Benchmark Practice Block');
    }

    private function defaultSuggestionTitle(string $type, array $block, array $current): string
    {
        $name = $block['title'] ?? $current['name'] ?? 'Benchmark Block';

        return match ($type) {
            'add_block' => 'Add '.$name,
            'remove_block' => 'Remove '.$name,
            'replace_block' => 'Replace '.$name,
            'reorder_block' => 'Reorder '.$name,
            'update_duration' => 'Update '.$name.' Duration',
            'update_metrics' => 'Update '.$name.' Metrics',
            'update_note' => 'Update '.$name.' Notes',
            'move_to_next_session' => 'Move '.$name.' to Next Session',
            default => 'Review '.$name,
        };
    }

    private function suggestionTitle(array $suggestion): string
    {
        return (string) ($suggestion['title'] ?? $suggestion['suggestion_id'] ?? 'suggestion');
    }

    private function durationMinutes(array $block): int
    {
        return max(0, (int) ($block['duration_minutes'] ?? $block['minutes'] ?? 0));
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

                return $metric ? BenchmarkDefinitions::normalizeMetricKey((string) $metric) : null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function players(mixed $players): array
    {
        return collect(is_array($players) ? $players : [])
            ->map(function ($player): ?array {
                if (! is_array($player)) {
                    return null;
                }

                $name = $player['player_name'] ?? $player['name'] ?? $player['assigned_to_player_name'] ?? null;
                $id = $player['player_id'] ?? $player['id'] ?? $player['assigned_to_player_id'] ?? null;
                if (! $name && ! $id) {
                    return null;
                }

                return [
                    'player_id' => $id,
                    'player_name' => $name ?? 'Unknown Player',
                    'name' => $name ?? 'Unknown Player',
                ];
            })
            ->filter()
            ->unique(fn (array $player): string => (string) ($player['player_id'] ?? $player['player_name'] ?? ''))
            ->values()
            ->all();
    }

    private function normalizePriority(string $priority): string
    {
        return match (strtolower(trim($priority))) {
            'critical' => 'critical',
            'high' => 'high',
            'medium', 'moderate' => 'medium',
            default => 'low',
        };
    }

    private function priorityRank(?string $priority): int
    {
        return [
            'low' => 1,
            'medium' => 2,
            'moderate' => 2,
            'high' => 3,
            'critical' => 4,
        ][strtolower((string) ($priority ?? 'low'))] ?? 1;
    }

    private function days(mixed $days): int
    {
        return max(7, min(365, (int) $days));
    }

    private function token(mixed $value): string
    {
        return Str::of((string) ($value ?? ''))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();
    }

    private function human(?string $value): string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? ucwords(str_replace('_', ' ', $value)) : 'Needs Data';
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }
}
