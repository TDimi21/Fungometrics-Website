<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use App\Models\BenchmarkCollectionTask;
use App\Models\Player;
use App\Models\PlayerAssessment;
use App\Models\PlayerFitness;
use App\Models\PlayerPosition;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class BenchmarkTrustedDataPromotionService
{
    private const TRUSTED_PAYLOAD_TASK_TYPES = [
        'exit_velocity_baseline',
        'bullpen_baseline',
        'long_toss_weighted_ball',
    ];

    public function __construct(
        private readonly BenchmarkTaskPersistenceService $taskPersistence,
        private readonly BenchmarkRefreshService $benchmarkRefreshService,
        private readonly PopulationValueGuardrail $guardrail,
        private readonly BenchmarkDataQualityRescoreService $benchmarkDataQualityRescoreService,
    ) {
    }

    public function previewPromotion(string $taskId): array
    {
        $task = $this->task($taskId);

        if (! $task) {
            return $this->baseResult($taskId, null, BenchmarkCollectionTask::PROMOTION_FAILED, BenchmarkCollectionTask::MODE_MANUAL_REVIEW, [
                'warnings' => ['Benchmark collection task was not found.'],
                'evidence' => ['preview' => true],
            ]);
        }

        return $this->buildPromotionResult($task, [
            'preview' => true,
            'write' => false,
        ]);
    }

    public function promoteApprovedTask(string $taskId, ?string $reviewedByUserId = null, array $options = []): array
    {
        $task = $this->task($taskId);

        if (! $task) {
            return $this->baseResult($taskId, null, BenchmarkCollectionTask::PROMOTION_FAILED, BenchmarkCollectionTask::MODE_MANUAL_REVIEW, [
                'warnings' => ['Benchmark collection task was not found.'],
            ]);
        }

        if ($this->alreadyPromoted($task) && ! (bool) ($options['force'] ?? false)) {
            $payloadPromotion = ($task->payload ?? [])['promotion'] ?? [];

            return $this->baseResult($taskId, $task, BenchmarkCollectionTask::PROMOTION_SKIPPED, (string) ($task->promotion_mode ?: ($payloadPromotion['promotion_mode'] ?? BenchmarkCollectionTask::MODE_TRUSTED_PAYLOAD_ONLY)), [
                'target_model' => $task->promotion_result['target_model'] ?? $payloadPromotion['target_model'] ?? null,
                'target_table' => $task->promotion_result['target_table'] ?? $payloadPromotion['target_table'] ?? null,
                'target_record_id' => $task->promotion_result['target_record_id'] ?? $payloadPromotion['target_record_id'] ?? null,
                'trusted_payload' => $task->promotion_result['trusted_payload'] ?? $payloadPromotion['trusted_payload'] ?? [],
                'warnings' => ['Task has already been promoted. No duplicate records were created.'],
                'evidence' => [
                    'idempotent' => true,
                    'existing_promotion_status' => $task->promotion_status ?? $payloadPromotion['promotion_status'] ?? null,
                    'existing_promoted_at' => $task->promoted_at?->toIso8601String() ?? $payloadPromotion['promoted_at'] ?? null,
                ],
                'refresh' => $task->promotion_result['refresh'] ?? $payloadPromotion['refresh'] ?? [],
                'rescore' => $task->promotion_result['rescore'] ?? $payloadPromotion['rescore'] ?? [],
            ]);
        }

        $eligibilityWarnings = $this->eligibilityWarnings($task);
        if (! empty($eligibilityWarnings)) {
            return $this->baseResult($taskId, $task, BenchmarkCollectionTask::PROMOTION_SKIPPED, BenchmarkCollectionTask::MODE_MANUAL_REVIEW, [
                'warnings' => $eligibilityWarnings,
                'evidence' => [
                    'review_status' => $task->review_status,
                    'status' => $task->status,
                    'approved_only' => true,
                    'metadata_written' => false,
                ],
            ]);
        }

        $beforeRescoreState = $this->captureRescoreBeforeState($task, $options);

        $result = $this->buildPromotionResult($task, [
            ...$options,
            'write' => true,
            'preview' => false,
            'promoted_by_user_id' => $reviewedByUserId,
        ]);

        if (in_array($result['promotion_status'] ?? null, [
            BenchmarkCollectionTask::PROMOTION_PROMOTED,
            BenchmarkCollectionTask::PROMOTION_PARTIAL,
        ], true)) {
            $this->storePromotionResult($task->fresh() ?? $task, $result, $reviewedByUserId);
            $result = $this->attachRefresh($taskId, $result, $options);
            $result = $this->attachRescore($taskId, $task->fresh() ?? $task, $result, $beforeRescoreState, $options);
        }

        $this->storePromotionResult($task->fresh() ?? $task, $result, $reviewedByUserId);

        return $result;
    }

    public function promoteTeamApprovedTasks(string $teamId, array $options = []): array
    {
        $query = BenchmarkCollectionTask::query()
            ->with(['assignedPlayer.profile', 'submittedBy.profile', 'reviewedBy.profile', 'promotedBy.profile'])
            ->where('team_id', $teamId)
            ->where('status', BenchmarkCollectionTask::STATUS_COMPLETED)
            ->where('review_status', BenchmarkCollectionTask::REVIEW_APPROVED)
            ->orderByDesc('reviewed_at')
            ->orderByDesc('updated_at');

        if ($this->taskHasColumn('promotion_status') && empty($options['include_already_promoted'])) {
            $query->where(function ($scope): void {
                $scope->whereNull('promotion_status')
                    ->orWhereNotIn('promotion_status', [
                        BenchmarkCollectionTask::PROMOTION_PROMOTED,
                        BenchmarkCollectionTask::PROMOTION_PARTIAL,
                    ]);
            });
        }

        $results = $query->get()
            ->map(fn (BenchmarkCollectionTask $task): array => $this->promoteApprovedTask((string) $task->id, $options['promoted_by_user_id'] ?? null, $options))
            ->values()
            ->all();

        return [
            'generated_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'promotion_count' => count($results),
            'promoted_count' => $this->countStatus($results, BenchmarkCollectionTask::PROMOTION_PROMOTED),
            'partial_count' => $this->countStatus($results, BenchmarkCollectionTask::PROMOTION_PARTIAL),
            'skipped_count' => $this->countStatus($results, BenchmarkCollectionTask::PROMOTION_SKIPPED),
            'failed_count' => $this->countStatus($results, BenchmarkCollectionTask::PROMOTION_FAILED),
            'results' => $results,
        ];
    }

    public function buildPromotionStatus(string $teamId): array
    {
        $tasks = BenchmarkCollectionTask::query()
            ->with(['assignedPlayer.profile', 'submittedBy.profile', 'reviewedBy.profile', 'promotedBy.profile'])
            ->where('team_id', $teamId)
            ->where('status', BenchmarkCollectionTask::STATUS_COMPLETED)
            ->where('review_status', BenchmarkCollectionTask::REVIEW_APPROVED)
            ->orderByDesc('reviewed_at')
            ->orderByDesc('updated_at')
            ->get();

        $serialized = $tasks->map(fn (BenchmarkCollectionTask $task): array => $this->taskPersistence->serializeTask($task) ?? [])->values();

        $awaiting = $serialized
            ->filter(fn (array $task): bool => ! in_array((string) ($task['promotion_status'] ?? ''), [
                BenchmarkCollectionTask::PROMOTION_PROMOTED,
                BenchmarkCollectionTask::PROMOTION_PARTIAL,
            ], true))
            ->values()
            ->all();

        $promoted = $serialized
            ->filter(fn (array $task): bool => in_array((string) ($task['promotion_status'] ?? ''), [
                BenchmarkCollectionTask::PROMOTION_PROMOTED,
                BenchmarkCollectionTask::PROMOTION_PARTIAL,
            ], true))
            ->values()
            ->all();

        $manualReview = $serialized
            ->filter(fn (array $task): bool => ($task['promotion_mode'] ?? null) === BenchmarkCollectionTask::MODE_MANUAL_REVIEW)
            ->values()
            ->all();

        $skipped = $serialized
            ->filter(fn (array $task): bool => ($task['promotion_status'] ?? null) === BenchmarkCollectionTask::PROMOTION_SKIPPED)
            ->values()
            ->all();

        return [
            'generated_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'approved_count' => $serialized->count(),
            'awaiting_promotion_count' => count($awaiting),
            'promoted_count' => count($promoted),
            'manual_review_count' => count($manualReview),
            'skipped_count' => count($skipped),
            'approved_awaiting_promotion' => $awaiting,
            'promoted_tasks' => $promoted,
            'manual_review_tasks' => $manualReview,
            'skipped_tasks' => $skipped,
            'evidence' => [
                'promotion_metadata_columns_present' => $this->promotionColumnsPresent(),
                'promotion_modes' => [
                    BenchmarkCollectionTask::MODE_PROFILE_UPDATE,
                    BenchmarkCollectionTask::MODE_EXISTING_TABLE_INSERT,
                    BenchmarkCollectionTask::MODE_TRUSTED_PAYLOAD_ONLY,
                    BenchmarkCollectionTask::MODE_MANUAL_REVIEW,
                ],
            ],
        ];
    }

    private function buildPromotionResult(BenchmarkCollectionTask $task, array $options): array
    {
        $eligibility = $this->eligibilityWarnings($task);
        if (! empty($eligibility)) {
            return $this->baseResult((string) $task->id, $task, BenchmarkCollectionTask::PROMOTION_SKIPPED, BenchmarkCollectionTask::MODE_MANUAL_REVIEW, [
                'warnings' => $eligibility,
                'evidence' => [
                    'review_status' => $task->review_status,
                    'status' => $task->status,
                    'approved_only' => true,
                ],
            ]);
        }

        $values = $this->approvedValues($task);
        $trustedPayload = $this->trustedPayload($task, $values);
        $preview = (bool) ($options['preview'] ?? false);
        $write = (bool) ($options['write'] ?? false);

        try {
            return match ((string) $task->task_type) {
                'roster_cleanup' => $this->promoteRosterCleanup($task, $values, $trustedPayload, $options, $preview, $write),
                'strength_baseline' => $this->promoteFitnessAndAssessment($task, $values, $trustedPayload, $options, $preview, $write, 'strength'),
                'athletic_testing' => $this->promoteFitnessAndAssessment($task, $values, $trustedPayload, $options, $preview, $write, 'athletic'),
                'mobility_screen' => $this->promoteFitnessAndAssessment($task, $values, $trustedPayload, $options, $preview, $write, 'mobility'),
                default => $this->promoteTrustedPayloadOnly($task, $values, $trustedPayload, $preview),
            };
        } catch (Throwable $exception) {
            return $this->baseResult((string) $task->id, $task, BenchmarkCollectionTask::PROMOTION_FAILED, BenchmarkCollectionTask::MODE_MANUAL_REVIEW, [
                'trusted_payload' => $trustedPayload,
                'warnings' => [$exception->getMessage()],
                'evidence' => [
                    'exception' => class_basename($exception),
                    'preview' => $preview,
                ],
            ]);
        }
    }

    private function promoteRosterCleanup(BenchmarkCollectionTask $task, array $values, array $trustedPayload, array $options, bool $preview, bool $write): array
    {
        $playerId = (string) $task->assigned_to_player_id;
        $overwrite = (bool) ($options['overwrite'] ?? false);
        $promotedFields = [];
        $skippedFields = [];
        $targetRecordId = null;
        $warnings = [];

        $playerFields = [
            'born_date' => 'born_date',
            'height_ft' => 'height_in_ft',
            'height_in' => 'height_in_inch',
            'grad_year' => 'grad_year',
            'throw_side' => 'throw_side',
            'hit_side' => 'hit_side',
        ];
        $profileFields = ['level' => 'level'];

        if (! $playerId) {
            return $this->baseResult((string) $task->id, $task, BenchmarkCollectionTask::PROMOTION_SKIPPED, BenchmarkCollectionTask::MODE_MANUAL_REVIEW, [
                'trusted_payload' => $trustedPayload,
                'warnings' => ['Roster cleanup task is missing an assigned player.'],
            ]);
        }

        DB::transaction(function () use ($write, $preview, $playerId, $values, $playerFields, $profileFields, $overwrite, &$promotedFields, &$skippedFields, &$targetRecordId): void {
            $user = User::query()->with(['profile', 'player'])->find($playerId);
            if (! $user) {
                $skippedFields[] = $this->skippedField('player', null, null, 'assigned_player_not_found');

                return;
            }

            $player = $user->player ?: new Player(['user_id' => $playerId]);
            foreach ($playerFields as $payloadKey => $column) {
                $value = $this->fieldValue($values, $payloadKey);
                $this->maybePromoteField($player, $column, $value, $overwrite, $preview, $promotedFields, $skippedFields);
            }

            if ($write && $player->isDirty()) {
                $player->save();
            }
            $targetRecordId = $player->exists ? (string) $player->getKey() : $playerId;

            $profile = $user->profile ?: new Profile(['user_id' => $playerId]);
            foreach ($profileFields as $payloadKey => $column) {
                $value = $this->fieldValue($values, $payloadKey);
                $this->maybePromoteField($profile, $column, $value, $overwrite, $preview, $promotedFields, $skippedFields);
            }
            if ($write && $profile->isDirty()) {
                $profile->save();
            }

            if ($this->filled($values['position'] ?? null)) {
                foreach ($this->positionList($values['position']) as $position) {
                    $exists = PlayerPosition::query()
                        ->where('player_id', $playerId)
                        ->where('position', $position)
                        ->exists();
                    if (! $exists && $write && ! $preview) {
                        PlayerPosition::query()->create([
                            'player_id' => $playerId,
                            'position' => $position,
                        ]);
                    }
                    $promotedFields[] = [
                        'field' => 'position',
                        'target' => 'player_positions.position',
                        'value' => $position,
                        'status' => $exists ? 'trusted_existing' : ($preview ? 'would_create' : 'created'),
                    ];
                }
            }
        });

        if ($this->numberOrNull($values['body_weight'] ?? null) !== null) {
            $fitness = $this->saveFitnessSnapshot($task, [
                'body_weight' => $this->numberOrNull($values['body_weight'] ?? null),
            ], $options, $preview, $write, $promotedFields, $skippedFields);
            $targetRecordId ??= $fitness['target_record_id'] ?? null;
        }

        return $this->baseResult((string) $task->id, $task, $this->promotionStatus($promotedFields, $skippedFields), BenchmarkCollectionTask::MODE_PROFILE_UPDATE, [
            'promoted_fields' => $promotedFields,
            'skipped_fields' => $skippedFields,
            'target_model' => Player::class,
            'target_table' => 'players',
            'target_record_id' => $targetRecordId,
            'trusted_payload' => $trustedPayload,
            'warnings' => $warnings,
            'evidence' => [
                'preview' => $preview,
                'overwrite' => $overwrite,
                'profile_update_fields' => array_keys($playerFields),
                'position_source' => $values['position'] ?? null,
            ],
        ]);
    }

    private function promoteFitnessAndAssessment(BenchmarkCollectionTask $task, array $values, array $trustedPayload, array $options, bool $preview, bool $write, string $type): array
    {
        $promotedFields = [];
        $skippedFields = [];
        $fitnessData = match ($type) {
            'strength' => [
                'bench_press' => $this->numberOrNull($values['bench_press'] ?? null),
                'back_squat' => $this->numberOrNull($values['squat'] ?? null),
                'dead_lift' => $this->numberOrNull($values['deadlift'] ?? null),
                'pull_ups' => $this->numberOrNull($values['pull_ups'] ?? null),
                'push_ups' => $this->numberOrNull($values['pushups'] ?? null),
            ],
            'athletic' => [
                'yd_40_dash' => $this->numberOrNull($values['forty_yard_dash'] ?? null),
                'yd_60_dash' => $this->numberOrNull($values['sixty_yard_dash'] ?? null),
                'broad_jump' => $this->numberOrNull($values['broad_jump'] ?? null),
                'vertical_jump' => $this->numberOrNull($values['vertical_jump'] ?? null),
            ],
            'mobility' => [
                'mobility_score' => $this->numberOrNull($values['mobility_score'] ?? null),
            ],
            default => [],
        };
        $assessmentData = match ($type) {
            'strength' => [
                'type' => 'strength',
                'bench_lbs' => $this->numberOrNull($values['bench_press'] ?? null),
                'squat_lbs' => $this->numberOrNull($values['squat'] ?? null),
                'deadlift_lbs' => $this->numberOrNull($values['deadlift'] ?? null),
            ],
            'athletic' => [
                'type' => 'full',
                'broad_jump_in' => $this->numberOrNull($values['broad_jump'] ?? null),
                'vertical_jump_in' => $this->numberOrNull($values['vertical_jump'] ?? null),
            ],
            'mobility' => [
                'type' => 'mobility',
                'mobility_overall_score' => $this->numberOrNull($values['mobility_score'] ?? null),
                'shoulder_mobility' => $this->mobilityTenPoint($values['shoulder_mobility_score'] ?? null),
                'hip_mobility' => $this->mobilityTenPoint($values['hip_mobility_score'] ?? null),
                'rotational_mobility' => $this->mobilityTenPoint($values['t_spine_mobility_score'] ?? null),
            ],
            default => [],
        };

        $fitness = $this->saveFitnessSnapshot($task, $fitnessData, $options, $preview, $write, $promotedFields, $skippedFields);
        $assessment = $this->saveAssessmentSnapshot($task, $assessmentData, $options, $preview, $write, $promotedFields, $skippedFields);

        return $this->baseResult((string) $task->id, $task, $this->promotionStatus($promotedFields, $skippedFields), BenchmarkCollectionTask::MODE_EXISTING_TABLE_INSERT, [
            'promoted_fields' => $promotedFields,
            'skipped_fields' => $skippedFields,
            'target_model' => $assessment['target_model'] ?? $fitness['target_model'] ?? PlayerFitness::class,
            'target_table' => $assessment['target_table'] ?? $fitness['target_table'] ?? 'player_fitnesses',
            'target_record_id' => $assessment['target_record_id'] ?? $fitness['target_record_id'] ?? null,
            'trusted_payload' => $trustedPayload,
            'warnings' => [],
            'evidence' => [
                'preview' => $preview,
                'overwrite' => (bool) ($options['overwrite'] ?? false),
                'fitness_date' => $this->taskDate($task),
                'assessment_type' => $assessmentData['type'] ?? $type,
            ],
        ]);
    }

    private function promoteTrustedPayloadOnly(BenchmarkCollectionTask $task, array $values, array $trustedPayload, bool $preview): array
    {
        if (empty($values)) {
            return $this->baseResult((string) $task->id, $task, BenchmarkCollectionTask::PROMOTION_SKIPPED, BenchmarkCollectionTask::MODE_MANUAL_REVIEW, [
                'trusted_payload' => $trustedPayload,
                'warnings' => ['No approved metric values were found to promote.'],
                'evidence' => [
                    'preview' => $preview,
                    'task_type' => $task->task_type,
                    'reason' => 'empty_approved_values',
                ],
            ]);
        }

        if (! in_array((string) $task->task_type, self::TRUSTED_PAYLOAD_TASK_TYPES, true)) {
            return $this->baseResult((string) $task->id, $task, BenchmarkCollectionTask::PROMOTION_SKIPPED, BenchmarkCollectionTask::MODE_MANUAL_REVIEW, [
                'trusted_payload' => $trustedPayload,
                'warnings' => ['Promotion mapping is ambiguous. Task remains approved and requires manual review.'],
                'evidence' => [
                    'preview' => $preview,
                    'task_type' => $task->task_type,
                    'reason' => 'unknown_task_mapping',
                ],
            ]);
        }

        return $this->baseResult((string) $task->id, $task, BenchmarkCollectionTask::PROMOTION_PROMOTED, BenchmarkCollectionTask::MODE_TRUSTED_PAYLOAD_ONLY, [
            'promoted_fields' => collect($values)
                ->map(fn ($value, $key): array => [
                    'field' => (string) $key,
                    'target' => 'benchmark_collection_tasks.approved_payload',
                    'value' => $value,
                    'status' => $preview ? 'would_trust_payload' : 'trusted_payload',
                ])
                ->values()
                ->all(),
            'trusted_payload' => $trustedPayload,
            'warnings' => ['No safe session table insert was attempted for this task type. Approved data is retained as trusted benchmark evidence.'],
            'evidence' => [
                'preview' => $preview,
                'task_type' => $task->task_type,
                'session_tables_require_full_practice_context' => true,
            ],
        ]);
    }

    private function saveFitnessSnapshot(BenchmarkCollectionTask $task, array $data, array $options, bool $preview, bool $write, array &$promotedFields, array &$skippedFields): array
    {
        $playerId = (string) $task->assigned_to_player_id;
        $date = $this->taskDate($task);
        $overwrite = (bool) ($options['overwrite'] ?? false);

        if (! $playerId) {
            $skippedFields[] = $this->skippedField('player_fitnesses', null, null, 'missing_player_id');

            return [];
        }

        $fitness = PlayerFitness::query()->firstOrNew([
            'user_id' => $playerId,
            'fitness_date' => $date,
        ]);

        foreach ($data as $field => $value) {
            $this->maybePromoteField($fitness, $field, $value, $overwrite, $preview, $promotedFields, $skippedFields, 'player_fitnesses');
        }

        if ($write && $fitness->isDirty()) {
            $fitness->save();
        }

        return [
            'target_model' => PlayerFitness::class,
            'target_table' => 'player_fitnesses',
            'target_record_id' => $fitness->exists ? (string) $fitness->getKey() : null,
        ];
    }

    private function saveAssessmentSnapshot(BenchmarkCollectionTask $task, array $data, array $options, bool $preview, bool $write, array &$promotedFields, array &$skippedFields): array
    {
        $playerId = (string) $task->assigned_to_player_id;
        $teamId = (string) $task->team_id;
        $date = $this->taskDate($task);
        $type = (string) ($data['type'] ?? 'benchmark');
        $overwrite = (bool) ($options['overwrite'] ?? false);

        if (! $playerId) {
            $skippedFields[] = $this->skippedField('player_assessments', null, null, 'missing_player_id');

            return [];
        }

        $assessment = PlayerAssessment::query()->firstOrNew([
            'user_id' => $playerId,
            'team_id' => $teamId ?: null,
            'assessment_date' => $date,
            'type' => $type,
        ]);
        $assessment->assessed_by ??= $task->reviewed_by_user_id ?? $task->submitted_by_user_id ?? $task->created_by_user_id;

        foreach ($data as $field => $value) {
            if ($field === 'type') {
                if (! $assessment->type) {
                    $assessment->type = $type;
                }
                continue;
            }

            $this->maybePromoteField($assessment, $field, $value, $overwrite, $preview, $promotedFields, $skippedFields, 'player_assessments');
        }

        if ($write && $assessment->isDirty()) {
            $assessment->save();
        }

        return [
            'target_model' => PlayerAssessment::class,
            'target_table' => 'player_assessments',
            'target_record_id' => $assessment->exists ? (string) $assessment->getKey() : null,
        ];
    }

    private function maybePromoteField(mixed $model, string $field, mixed $value, bool $overwrite, bool $preview, array &$promotedFields, array &$skippedFields, ?string $table = null): void
    {
        if (! $this->filled($value)) {
            $skippedFields[] = $this->skippedField($field, null, $value, 'empty_value');

            return;
        }

        $current = $model->{$field} ?? null;
        $target = ($table ?: $model->getTable()).'.'.$field;
        if ($this->filled($current) && ! $overwrite && ! $this->sameValue($current, $value)) {
            $skippedFields[] = $this->skippedField($field, $target, $value, 'existing_value_not_overwritten');

            return;
        }

        if (! $preview && ($overwrite || ! $this->filled($current) || $this->sameValue($current, $value))) {
            $model->{$field} = $value;
        }

        $promotedFields[] = [
            'field' => $field,
            'target' => $target,
            'value' => $value,
            'status' => $this->sameValue($current, $value) ? 'trusted_existing' : ($preview ? 'would_update' : 'updated'),
        ];
    }

    private function attachRefresh(string $taskId, array $result, array $options): array
    {
        try {
            $refresh = $this->benchmarkRefreshService->refreshAfterTaskCompletion($taskId, [
                'days' => $this->days($options['days'] ?? 365),
            ]);
            $result['refresh'] = $refresh;
            if (($refresh['refresh_status'] ?? null) === 'failed') {
                $result['warnings'][] = 'Benchmark refresh failed after promotion.';
                $result['promotion_status'] = BenchmarkCollectionTask::PROMOTION_PARTIAL;
            }
        } catch (Throwable $exception) {
            $result['warnings'][] = 'Benchmark refresh failed after promotion: '.$exception->getMessage();
            $result['promotion_status'] = BenchmarkCollectionTask::PROMOTION_PARTIAL;
            $result['refresh'] = [
                'task_id' => $taskId,
                'refresh_status' => 'failed',
                'warnings' => [$exception->getMessage()],
            ];
        }

        return $result;
    }

    private function captureRescoreBeforeState(BenchmarkCollectionTask $task, array $options): array
    {
        $teamId = $this->nullableString($task->team_id);
        $playerId = $this->nullableString($task->assigned_to_player_id);

        if (! $teamId) {
            return [
                'warnings' => ['Before data quality state was skipped because the task is missing a team_id.'],
            ];
        }

        try {
            return $this->benchmarkDataQualityRescoreService->buildCurrentState(
                $teamId,
                $playerId,
                $this->days($options['days'] ?? 365),
            );
        } catch (Throwable $exception) {
            return [
                'warnings' => ['Before data quality state unavailable: '.$exception->getMessage()],
            ];
        }
    }

    private function attachRescore(string $taskId, BenchmarkCollectionTask $task, array $result, array $before, array $options): array
    {
        $teamId = $this->nullableString($task->team_id);
        $playerId = $this->nullableString($task->assigned_to_player_id);

        if (! $teamId) {
            $result['rescore'] = [
                'task_id' => $taskId,
                'team_id' => null,
                'player_id' => $playerId,
                'rescore_status' => 'skipped',
                'warnings' => ['Benchmark data quality re-score skipped because the task is missing a team_id.'],
            ];

            return $result;
        }

        try {
            $result['rescore'] = $this->benchmarkDataQualityRescoreService->rescoreAfterPromotion($teamId, $playerId, [
                'days' => $this->days($options['days'] ?? 365),
                'before' => $before,
                'promotion' => $result,
                'trusted_payload' => $result['trusted_payload'] ?? [],
            ]);
        } catch (Throwable $exception) {
            $result['warnings'][] = 'Trusted data was promoted, but benchmark re-score will update on next dashboard load.';
            $result['rescore'] = [
                'task_id' => $taskId,
                'team_id' => $teamId,
                'player_id' => $playerId,
                'rescore_status' => 'failed',
                'warnings' => [$exception->getMessage()],
            ];
            $result['promotion_status'] = BenchmarkCollectionTask::PROMOTION_PARTIAL;
        }

        return $result;
    }

    private function storePromotionResult(BenchmarkCollectionTask $task, array $result, ?string $promotedByUserId): void
    {
        $storedResult = $this->persistablePromotionResult($result);
        $wasPromoted = in_array($result['promotion_status'] ?? null, [
            BenchmarkCollectionTask::PROMOTION_PROMOTED,
            BenchmarkCollectionTask::PROMOTION_PARTIAL,
        ], true);
        $payload = $task->payload ?? [];
        $payload['promotion'] = [
            'promoted_at' => $wasPromoted ? now()->toIso8601String() : null,
            'promoted_by_user_id' => $promotedByUserId,
            'promotion_status' => $result['promotion_status'] ?? null,
            'promotion_mode' => $result['promotion_mode'] ?? null,
            'target_model' => $result['target_model'] ?? null,
            'target_table' => $result['target_table'] ?? null,
            'target_record_id' => $result['target_record_id'] ?? null,
            'trusted_payload' => $result['trusted_payload'] ?? [],
            'warnings' => $result['warnings'] ?? [],
            'refresh' => $storedResult['refresh'] ?? [],
            'rescore' => $storedResult['rescore'] ?? [],
        ];
        $task->payload = $payload;

        if ($this->taskHasColumn('promoted_at') && $wasPromoted) {
            $task->promoted_at = now();
        }
        if ($this->taskHasColumn('promoted_by_user_id')) {
            $task->promoted_by_user_id = $promotedByUserId;
        }
        if ($this->taskHasColumn('promotion_status')) {
            $task->promotion_status = $result['promotion_status'] ?? null;
        }
        if ($this->taskHasColumn('promotion_mode')) {
            $task->promotion_mode = $result['promotion_mode'] ?? null;
        }
        if ($this->taskHasColumn('promotion_result')) {
            $task->promotion_result = $storedResult;
        }

        $task->save();
    }

    private function persistablePromotionResult(array $result): array
    {
        $stored = $result;

        if (is_array($stored['rescore'] ?? null)) {
            $stored['rescore'] = $this->compactRescore($stored['rescore']);
        }

        return $stored;
    }

    private function compactRescore(array $rescore): array
    {
        return [
            'generated_at' => $rescore['generated_at'] ?? now()->toIso8601String(),
            'team_id' => $rescore['team_id'] ?? null,
            'player_id' => $rescore['player_id'] ?? null,
            'rescore_status' => $rescore['rescore_status'] ?? null,
            'improvement_summary' => $rescore['improvement_summary'] ?? [],
            'changes' => array_slice(is_array($rescore['changes'] ?? null) ? $rescore['changes'] : [], 0, 12),
            'remaining_gaps' => array_slice(is_array($rescore['remaining_gaps'] ?? null) ? $rescore['remaining_gaps'] : [], 0, 12),
            'next_recommended_actions' => array_slice(is_array($rescore['next_recommended_actions'] ?? null) ? $rescore['next_recommended_actions'] : [], 0, 6),
            'action_rerank' => $this->compactActionRerank(is_array($rescore['action_rerank'] ?? null) ? $rescore['action_rerank'] : []),
            'warnings' => $rescore['warnings'] ?? [],
            'evidence' => $rescore['evidence'] ?? [],
        ];
    }

    private function compactActionRerank(array $rerank): array
    {
        if (empty($rerank)) {
            return [];
        }

        return [
            'generated_at' => $rerank['generated_at'] ?? now()->toIso8601String(),
            'team_id' => $rerank['team_id'] ?? null,
            'rerank_status' => $rerank['rerank_status'] ?? null,
            'primary_focus_before' => $rerank['primary_focus_before'] ?? null,
            'primary_focus_after' => $rerank['primary_focus_after'] ?? null,
            'data_collection_priority_before' => $rerank['data_collection_priority_before'] ?? null,
            'data_collection_priority_after' => $rerank['data_collection_priority_after'] ?? null,
            'top_actions_before' => array_slice(is_array($rerank['top_actions_before'] ?? null) ? $rerank['top_actions_before'] : [], 0, 5),
            'top_actions_after' => array_slice(is_array($rerank['top_actions_after'] ?? null) ? $rerank['top_actions_after'] : [], 0, 5),
            'action_changes' => array_slice(is_array($rerank['action_changes'] ?? null) ? $rerank['action_changes'] : [], 0, 12),
            'removed_actions' => array_slice(is_array($rerank['removed_actions'] ?? null) ? $rerank['removed_actions'] : [], 0, 8),
            'new_actions' => array_slice(is_array($rerank['new_actions'] ?? null) ? $rerank['new_actions'] : [], 0, 8),
            'updated_practice_plan' => is_array($rerank['updated_practice_plan'] ?? null) ? [
                'plan_title' => $rerank['updated_practice_plan']['plan_title'] ?? null,
                'priority_focus' => $rerank['updated_practice_plan']['priority_focus'] ?? null,
                'estimated_total_minutes' => $rerank['updated_practice_plan']['estimated_total_minutes'] ?? null,
                'practice_blocks' => array_slice(is_array($rerank['updated_practice_plan']['practice_blocks'] ?? null) ? $rerank['updated_practice_plan']['practice_blocks'] : [], 0, 6),
            ] : [],
            'practice_plan_update_suggestions' => $this->compactPracticePlanUpdateSuggestions(
                is_array($rerank['practice_plan_update_suggestions'] ?? null) ? $rerank['practice_plan_update_suggestions'] : []
            ),
            'coach_summary' => $rerank['coach_summary'] ?? null,
            'warnings' => $rerank['warnings'] ?? [],
            'evidence' => $rerank['evidence'] ?? [],
        ];
    }

    private function compactPracticePlanUpdateSuggestions(array $suggestions): array
    {
        if (empty($suggestions)) {
            return [];
        }

        return [
            'generated_at' => $suggestions['generated_at'] ?? now()->toIso8601String(),
            'team_id' => $suggestions['team_id'] ?? null,
            'daily_plan_id' => $suggestions['daily_plan_id'] ?? null,
            'suggestion_status' => $suggestions['suggestion_status'] ?? null,
            'current_plan' => $suggestions['current_plan'] ?? [],
            'latest_suggested_plan' => is_array($suggestions['latest_suggested_plan'] ?? null) ? [
                'plan_title' => $suggestions['latest_suggested_plan']['plan_title'] ?? null,
                'priority_focus' => $suggestions['latest_suggested_plan']['priority_focus'] ?? null,
                'estimated_total_minutes' => $suggestions['latest_suggested_plan']['estimated_total_minutes'] ?? null,
                'block_count' => $suggestions['latest_suggested_plan']['block_count'] ?? null,
            ] : [],
            'focus_change' => $suggestions['focus_change'] ?? [],
            'suggestions' => array_slice(is_array($suggestions['suggestions'] ?? null) ? $suggestions['suggestions'] : [], 0, 8),
            'summary' => $suggestions['summary'] ?? null,
            'requires_coach_review' => $suggestions['requires_coach_review'] ?? true,
            'warnings' => $suggestions['warnings'] ?? [],
            'evidence' => $suggestions['evidence'] ?? [],
        ];
    }

    private function eligibilityWarnings(BenchmarkCollectionTask $task): array
    {
        $warnings = [];
        if ($task->review_status !== BenchmarkCollectionTask::REVIEW_APPROVED) {
            $warnings[] = 'Only approved benchmark tasks can be promoted.';
        }
        if ($task->status !== BenchmarkCollectionTask::STATUS_COMPLETED) {
            $warnings[] = 'Only completed benchmark tasks can be promoted.';
        }
        if (in_array($task->status, [
            BenchmarkCollectionTask::STATUS_DRAFT,
            BenchmarkCollectionTask::STATUS_ASSIGNED,
            BenchmarkCollectionTask::STATUS_IN_PROGRESS,
        ], true)) {
            $warnings[] = 'Draft, assigned, and in-progress tasks cannot be promoted.';
        }

        return array_values(array_unique($warnings));
    }

    private function alreadyPromoted(BenchmarkCollectionTask $task): bool
    {
        $status = $task->promotion_status ?? (($task->payload ?? [])['promotion']['promotion_status'] ?? null);

        return in_array($status, [
            BenchmarkCollectionTask::PROMOTION_PROMOTED,
            BenchmarkCollectionTask::PROMOTION_PARTIAL,
        ], true);
    }

    private function promotionStatus(array $promotedFields, array $skippedFields): string
    {
        if (empty($promotedFields) && ! empty($skippedFields)) {
            return BenchmarkCollectionTask::PROMOTION_SKIPPED;
        }

        if (! empty($promotedFields) && ! empty($skippedFields)) {
            return BenchmarkCollectionTask::PROMOTION_PARTIAL;
        }

        return BenchmarkCollectionTask::PROMOTION_PROMOTED;
    }

    private function baseResult(string $taskId, ?BenchmarkCollectionTask $task, string $status, string $mode, array $overrides = []): array
    {
        $overrideEvidence = is_array($overrides['evidence'] ?? null) ? $overrides['evidence'] : [];
        unset($overrides['evidence']);

        return [
            'task_id' => $taskId,
            'team_id' => $task?->team_id,
            'player_id' => $task?->assigned_to_player_id,
            'task_type' => $task?->task_type,
            'promotion_status' => $status,
            'promotion_mode' => $mode,
            'promoted_fields' => [],
            'skipped_fields' => [],
            'target_model' => null,
            'target_table' => null,
            'target_record_id' => null,
            'trusted_payload' => [],
            'warnings' => [],
            'evidence' => [
                'generated_at' => now()->toIso8601String(),
                ...$this->dailyPlanEvidence($task),
                ...$overrideEvidence,
            ],
            'refresh' => [],
            'rescore' => [],
            ...$overrides,
        ];
    }

    private function approvedValues(BenchmarkCollectionTask $task): array
    {
        $payload = $task->approved_payload ?? $task->submitted_payload ?? (($task->payload ?? [])['completion'] ?? []);
        if (! is_array($payload)) {
            return [];
        }

        foreach ([
            ['metric_values'],
            ['actuals'],
            ['results'],
            ['submitted_values'],
            ['values'],
            ['payload', 'values'],
            ['payload', 'submitted_values'],
        ] as $path) {
            $value = $this->arrayPath($payload, $path);
            if (is_array($value)) {
                return $this->cleanValues($value);
            }
        }

        return $this->cleanValues($payload);
    }

    private function trustedPayload(BenchmarkCollectionTask $task, array $values): array
    {
        return [
            'task_id' => (string) $task->id,
            'team_id' => $task->team_id,
            'player_id' => $task->assigned_to_player_id,
            'task_type' => $task->task_type,
            'review_status' => $task->review_status,
            'reviewed_at' => $task->reviewed_at?->toIso8601String(),
            'reviewed_by_user_id' => $task->reviewed_by_user_id,
            'values' => $values,
            'approved_payload' => $task->approved_payload ?? [],
            'source' => 'approved_benchmark_collection_task',
            'submitted_source' => $task->approved_payload['source'] ?? $task->submitted_payload['source'] ?? null,
            'daily_plan_id' => $task->approved_payload['daily_plan_id'] ?? $task->submitted_payload['daily_plan_id'] ?? null,
            'daily_plan_item_key' => $task->approved_payload['daily_plan_item_key'] ?? $task->submitted_payload['daily_plan_item_key'] ?? null,
            'daily_plan_item_title' => $task->approved_payload['daily_plan_item_title'] ?? $task->submitted_payload['daily_plan_item_title'] ?? null,
            'guardrail_results' => $this->guardrailResults($values),
        ];
    }

    private function cleanValues(array $values): array
    {
        unset(
            $values['manual_confirm'],
            $values['source'],
            $values['payload'],
            $values['daily_plan_id'],
            $values['daily_plan_item_id'],
            $values['daily_plan_item_key'],
            $values['daily_plan_item_name'],
            $values['daily_plan_item_title'],
            $values['submitted_by_user_id'],
            $values['submitted_at'],
            $values['related_metrics'],
            $values['metric_keys'],
            $values['metrics_to_collect'],
            $values['completed_by_user_id'],
            $values['completion_mode'],
            $values['saved_data'],
            $values['note'],
            $values['coach_notes']
        );

        return $values;
    }

    private function dailyPlanEvidence(?BenchmarkCollectionTask $task): array
    {
        if (! $task) {
            return [];
        }

        $payload = is_array($task->approved_payload ?? null)
            ? $task->approved_payload
            : (is_array($task->submitted_payload ?? null) ? $task->submitted_payload : []);

        return [
            'submitted_source' => $payload['source'] ?? null,
            'daily_plan_id' => $payload['daily_plan_id'] ?? null,
            'daily_plan_item_key' => $payload['daily_plan_item_key'] ?? $payload['daily_plan_item_id'] ?? null,
            'daily_plan_item_title' => $payload['daily_plan_item_title'] ?? $payload['daily_plan_item_name'] ?? null,
        ];
    }

    private function guardrailResults(array $values): array
    {
        $results = [];

        foreach ($values as $key => $value) {
            $metricKey = BenchmarkDefinitions::normalizeMetricKey((string) $key);
            if ($this->guardrail->rangeForMetric($metricKey) === null) {
                continue;
            }

            $results[$metricKey] = $this->guardrail->validate($metricKey, $value);
        }

        return $results;
    }

    private function task(string $taskId): ?BenchmarkCollectionTask
    {
        return BenchmarkCollectionTask::query()
            ->with(['assignedPlayer.profile', 'submittedBy.profile', 'reviewedBy.profile', 'promotedBy.profile'])
            ->find($taskId);
    }

    private function taskDate(BenchmarkCollectionTask $task): string
    {
        $payload = $task->approved_payload ?? $task->submitted_payload ?? [];

        return (string) (
            $this->arrayPath((array) $payload, ['date'])
            ?? $this->arrayPath((array) $payload, ['fitness_date'])
            ?? $task->submitted_at?->toDateString()
            ?? $task->reviewed_at?->toDateString()
            ?? now()->toDateString()
        );
    }

    private function fieldValue(array $values, string $key): mixed
    {
        $value = $values[$key] ?? null;

        return is_numeric($value) ? $this->numberOrNull($value) : $this->nullableString($value);
    }

    private function skippedField(string $field, ?string $target, mixed $value, string $reason): array
    {
        return [
            'field' => $field,
            'target' => $target,
            'value' => $value,
            'reason' => $reason,
        ];
    }

    private function mobilityTenPoint(mixed $value): ?int
    {
        $number = $this->numberOrNull($value);
        if ($number === null) {
            return null;
        }

        return (int) round($number > 10 ? $number / 10 : $number);
    }

    private function numberOrNull(mixed $value): int|float|null
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return fmod($number, 1.0) === 0.0 ? (int) $number : $number;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function filled(mixed $value): bool
    {
        if (is_array($value)) {
            return ! empty($value);
        }

        return $value !== null && trim((string) $value) !== '';
    }

    private function sameValue(mixed $left, mixed $right): bool
    {
        if (is_numeric($left) && is_numeric($right)) {
            return abs((float) $left - (float) $right) < 0.00001;
        }

        return trim((string) $left) === trim((string) $right);
    }

    private function positionList(mixed $value): array
    {
        if (is_array($value)) {
            $items = $value;
        } else {
            $items = preg_split('/[\s,\/|;]+/', (string) $value) ?: [];
        }

        return collect($items)
            ->map(fn ($item): string => strtoupper(trim((string) $item)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function arrayPath(array $payload, array $path): mixed
    {
        $value = $payload;
        foreach ($path as $key) {
            if (! is_array($value) || ! array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    private function taskHasColumn(string $column): bool
    {
        static $columns = [];

        if (! array_key_exists($column, $columns)) {
            $columns[$column] = Schema::hasColumn('benchmark_collection_tasks', $column);
        }

        return $columns[$column];
    }

    private function promotionColumnsPresent(): bool
    {
        foreach (['promoted_at', 'promoted_by_user_id', 'promotion_status', 'promotion_mode', 'promotion_result'] as $column) {
            if (! $this->taskHasColumn($column)) {
                return false;
            }
        }

        return true;
    }

    private function countStatus(array $results, string $status): int
    {
        return collect($results)->where('promotion_status', $status)->count();
    }

    private function days(mixed $days): int
    {
        return max(7, min(365, (int) $days));
    }
}
