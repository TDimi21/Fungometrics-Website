<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use App\Models\BenchmarkCollectionTask;
use App\Models\BullpenPracticeResult;
use App\Models\ExitVelocityPractice;
use App\Models\LongTossPractice;
use App\Models\Player;
use App\Models\PlayerAssessment;
use App\Models\PlayerFitness;
use App\Models\PlayerPosition;
use App\Models\User;
use App\Models\WeightBallPractice;
use Illuminate\Support\Facades\DB;
use Throwable;

class BenchmarkTaskCompletionService
{
    public function __construct(
        private readonly BenchmarkTaskPersistenceService $taskPersistence,
    ) {
    }

    public function getCompletionWorkflow(string $taskId, ?string $userId = null): array
    {
        $task = BenchmarkCollectionTask::query()
            ->with(['assignedPlayer.profile', 'assignedPlayer.player', 'assignedPlayer.positions'])
            ->find($taskId);

        if (! $task) {
            return $this->result(false, [
                'task_id' => $taskId,
                'error' => 'task_not_found',
                'workflow' => null,
            ]);
        }

        if ($userId && $task->assigned_to_player_id && (string) $task->assigned_to_player_id !== $userId) {
            return $this->result(false, [
                'task_id' => $taskId,
                'error' => 'task_not_assigned_to_user',
                'workflow' => null,
            ]);
        }

        return $this->result(true, [
            'task_id' => $taskId,
            'workflow' => $this->workflowForTask($task),
        ]);
    }

    public function completeTaskWithPayload(string $taskId, array $payload, ?string $userId = null): array
    {
        try {
            $task = BenchmarkCollectionTask::query()
                ->with(['assignedPlayer.profile', 'assignedPlayer.player', 'assignedPlayer.positions'])
                ->find($taskId);

            if (! $task) {
                return $this->result(false, [
                    'task_id' => $taskId,
                    'error' => 'task_not_found',
                    'workflow' => null,
                ]);
            }

            if ($userId && $task->assigned_to_player_id && (string) $task->assigned_to_player_id !== $userId) {
                return $this->result(false, [
                    'task_id' => $taskId,
                    'error' => 'task_not_assigned_to_user',
                    'workflow' => $this->workflowForTask($task),
                ]);
            }

            if ($task->status === BenchmarkCollectionTask::STATUS_DRAFT) {
                return $this->result(false, [
                    'task_id' => $taskId,
                    'error' => 'cannot_complete_draft_task',
                    'workflow' => $this->workflowForTask($task),
                ]);
            }

            if ($task->status === BenchmarkCollectionTask::STATUS_DISMISSED) {
                return $this->result(false, [
                    'task_id' => $taskId,
                    'error' => 'cannot_complete_dismissed_task',
                    'workflow' => $this->workflowForTask($task),
                ]);
            }

            if ($task->status === BenchmarkCollectionTask::STATUS_COMPLETED) {
                return $this->result(false, [
                    'task_id' => $taskId,
                    'error' => 'task_already_completed',
                    'workflow' => $this->workflowForTask($task),
                ]);
            }

            $workflow = $this->workflowForTask($task);
            $values = $this->completionValues($payload);
            $savedData = [];

            if (($workflow['completion_mode'] ?? null) === 'inline_form') {
                $missing = $this->missingRequiredFields($workflow['required_fields'] ?? [], $values);
                if (! empty($missing)) {
                    return $this->result(false, [
                        'task_id' => $taskId,
                        'error' => 'missing_required_fields',
                        'missing_fields' => $missing,
                        'workflow' => $workflow,
                    ]);
                }

                $savedData = DB::transaction(fn () => $this->saveInlineData($task, $values, $payload, $userId));
                $task = $task->fresh(['assignedPlayer.profile', 'assignedPlayer.player', 'assignedPlayer.positions']);
            } elseif (! ($workflow['existing_data_found'] ?? false) && ! $this->manualConfirmRequested($payload)) {
                return $this->result(false, [
                    'task_id' => $taskId,
                    'error' => 'manual_confirm_required',
                    'workflow' => $workflow,
                ]);
            }

            $completion = $this->taskPersistence->markTaskComplete($taskId, [
                'completed_by_user_id' => $userId,
                'source' => 'benchmark_task_completion',
                'completion_mode' => $workflow['completion_mode'] ?? null,
                'submitted_values' => $values,
                'manual_confirm' => $this->manualConfirmRequested($payload),
                'note' => $payload['note'] ?? $payload['coach_notes'] ?? null,
                'saved_data' => $savedData,
            ]);

            return $this->result((bool) ($completion['ok'] ?? false), [
                'task_id' => $taskId,
                'workflow' => $this->workflowForTask($task?->fresh(['assignedPlayer.profile', 'assignedPlayer.player', 'assignedPlayer.positions']) ?? $task),
                'completion' => $completion,
            ]);
        } catch (Throwable $exception) {
            return $this->result(false, [
                'task_id' => $taskId,
                'error' => class_basename($exception),
                'message' => $exception->getMessage(),
                'workflow' => null,
            ]);
        }
    }

    private function workflowForTask(BenchmarkCollectionTask $task): array
    {
        $taskType = (string) $task->task_type;
        $config = $this->workflowConfig($taskType, $task);
        $summary = $this->existingDataSummary($task);
        $existing = $this->hasExistingData($taskType, $summary);

        return [
            'task_id' => (string) $task->id,
            'task_type' => $taskType,
            'title' => (string) $task->title,
            'status' => (string) $task->status,
            'completion_mode' => $config['completion_mode'],
            'target_route' => $config['target_route'],
            'target_screen' => $config['target_screen'],
            'required_fields' => $config['required_fields'],
            'optional_fields' => $config['optional_fields'],
            'instructions' => $task->instructions ?: $config['instructions'],
            'can_complete_inline' => $config['completion_mode'] === 'inline_form',
            'existing_data_found' => $existing,
            'existing_data_summary' => $summary,
            'completion_rules' => $this->completionRules($task, $config['completion_mode'], $existing),
        ];
    }

    private function workflowConfig(string $taskType, BenchmarkCollectionTask $task): array
    {
        $playerId = (string) ($task->assigned_to_player_id ?? '');

        return match ($taskType) {
            'roster_cleanup' => [
                'completion_mode' => 'inline_form',
                'target_route' => '/profile-player',
                'target_screen' => 'Player Profile',
                'required_fields' => [
                    $this->field('born_date', 'Date of Birth', 'date'),
                    $this->field('position', 'Position', 'text'),
                    $this->field('height_ft', 'Height Feet', 'number', null, 0, 8),
                    $this->field('height_in', 'Height Inches', 'number', null, 0, 11),
                    $this->field('body_weight', 'Weight', 'number', 'lb', 40, 400),
                    $this->field('throw_side', 'Throws', 'select', null, null, null, ['R', 'L']),
                    $this->field('hit_side', 'Bats', 'select', null, null, null, ['R', 'L', 'S']),
                ],
                'optional_fields' => [
                    $this->field('level', 'Level', 'text'),
                    $this->field('grad_year', 'Grad Year', 'number', null, 1900, 2200),
                ],
                'instructions' => [
                    'Confirm player profile basics.',
                    'Save date of birth, position, body size, throws, bats, and level when available.',
                ],
            ],
            'exit_velocity_baseline' => [
                'completion_mode' => 'navigate',
                'target_route' => '/training-player/exitvelocity',
                'target_screen' => 'Exit Velocity Sessions',
                'required_fields' => [
                    $this->field('average_exit_velocity', 'Average Exit Velocity', 'number', 'mph', 20, 115),
                    $this->field('max_exit_velocity', 'Max Exit Velocity', 'number', 'mph', 30, 125),
                ],
                'optional_fields' => [
                    $this->field('hard_hit_percentage', 'Hard Hit %', 'number', '%', 0, 100),
                    $this->field('line_drive_percentage', 'Line Drive %', 'number', '%', 0, 100),
                    $this->field('hitter_swing_miss_percentage', 'Swing Miss %', 'number', '%', 0, 100),
                ],
                'instructions' => ['Open or collect an exit velocity session, then mark this task collected.'],
            ],
            'bullpen_baseline' => [
                'completion_mode' => 'navigate',
                'target_route' => '/training-player/bullpen',
                'target_screen' => 'Bullpen Sessions',
                'required_fields' => [
                    $this->field('average_fastball_velocity', 'Average Fastball Velocity', 'number', 'mph', 30, 110),
                    $this->field('max_fastball_velocity', 'Max Fastball Velocity', 'number', 'mph', 35, 115),
                    $this->field('strike_percentage', 'Strike %', 'number', '%', 0, 100),
                ],
                'optional_fields' => [],
                'instructions' => ['Open or collect a bullpen session, then mark this task collected.'],
            ],
            'long_toss_weighted_ball' => [
                'completion_mode' => 'navigate',
                'target_route' => '/training-player/longtoss',
                'target_screen' => 'Long Toss / Weighted Ball Sessions',
                'required_fields' => [
                    $this->field('long_toss_max_distance', 'Long Toss Max Distance', 'number', 'ft', 30, 450),
                    $this->field('weighted_ball_5oz_velocity', '5 oz Weighted Ball Velocity', 'number', 'mph', 30, 115),
                ],
                'optional_fields' => [],
                'instructions' => ['Collect long toss and weighted ball baselines in the existing session tools, then mark this task collected.'],
            ],
            'strength_baseline' => [
                'completion_mode' => 'inline_form',
                'target_route' => '/player-dashboard',
                'target_screen' => 'Strength Baseline Form',
                'required_fields' => [
                    $this->field('bench_press', 'Bench Press', 'number', 'lb', 0, 700),
                    $this->field('squat', 'Squat', 'number', 'lb', 0, 900),
                    $this->field('deadlift', 'Deadlift', 'number', 'lb', 0, 900),
                    $this->field('pull_ups', 'Pull Ups', 'number', 'reps', 0, 60),
                    $this->field('pushups', 'Pushups', 'number', 'reps', 0, 200),
                ],
                'optional_fields' => [],
                'instructions' => ['Enter safe strength baseline values. Do not force max testing if the player is not ready.'],
            ],
            'athletic_testing' => [
                'completion_mode' => 'inline_form',
                'target_route' => '/player-dashboard',
                'target_screen' => 'Athletic Testing Form',
                'required_fields' => [
                    $this->field('forty_yard_dash', '40 Yard Dash', 'number', 'sec', 4, 10, null, 0.01),
                    $this->field('sixty_yard_dash', '60 Yard Dash', 'number', 'sec', 5, 12, null, 0.01),
                    $this->field('broad_jump', 'Broad Jump', 'number', 'in', 24, 140),
                    $this->field('vertical_jump', 'Vertical Jump', 'number', 'in', 5, 45),
                ],
                'optional_fields' => [],
                'instructions' => ['Enter best valid athletic testing values after a full warm-up.'],
            ],
            'mobility_screen' => [
                'completion_mode' => 'inline_form',
                'target_route' => '/player-dashboard',
                'target_screen' => 'Mobility Screen Form',
                'required_fields' => [
                    $this->field('mobility_score', 'Overall Mobility Score', 'number', '/100', 0, 100),
                    $this->field('shoulder_mobility_score', 'Shoulder Mobility Score', 'number', '/100', 0, 100),
                    $this->field('hip_mobility_score', 'Hip Mobility Score', 'number', '/100', 0, 100),
                    $this->field('t_spine_mobility_score', 'T-Spine Mobility Score', 'number', '/100', 0, 100),
                ],
                'optional_fields' => [],
                'instructions' => ['Enter mobility scores. FMTRX stores overall mobility in fitness and area scores in assessment.'],
            ],
            default => [
                'completion_mode' => 'manual_confirm',
                'target_route' => null,
                'target_screen' => null,
                'required_fields' => [],
                'optional_fields' => [],
                'instructions' => ['Confirm this benchmark task was collected outside FMTRX.'],
            ],
        };
    }

    private function saveInlineData(BenchmarkCollectionTask $task, array $values, array $payload, ?string $userId): array
    {
        $playerId = (string) $task->assigned_to_player_id;
        $teamId = (string) $task->team_id;
        $date = (string) ($payload['date'] ?? $payload['fitness_date'] ?? now()->toDateString());

        return match ((string) $task->task_type) {
            'roster_cleanup' => $this->saveRosterCleanup($playerId, $values, $date),
            'strength_baseline' => [
                'player_fitness' => $this->saveFitnessSnapshot($playerId, $date, [
                    'bench_press' => $this->numberOrNull($values['bench_press'] ?? null),
                    'back_squat' => $this->numberOrNull($values['squat'] ?? null),
                    'dead_lift' => $this->numberOrNull($values['deadlift'] ?? null),
                    'pull_ups' => $this->numberOrNull($values['pull_ups'] ?? null),
                    'push_ups' => $this->numberOrNull($values['pushups'] ?? null),
                ]),
                'player_assessment' => $this->saveAssessmentSnapshot($playerId, $teamId, $userId, $date, [
                    'type' => 'strength',
                    'bench_lbs' => $this->numberOrNull($values['bench_press'] ?? null),
                    'squat_lbs' => $this->numberOrNull($values['squat'] ?? null),
                    'deadlift_lbs' => $this->numberOrNull($values['deadlift'] ?? null),
                    'notes' => $payload['note'] ?? null,
                ]),
            ],
            'athletic_testing' => [
                'player_fitness' => $this->saveFitnessSnapshot($playerId, $date, [
                    'yd_40_dash' => $this->numberOrNull($values['forty_yard_dash'] ?? null),
                    'yd_60_dash' => $this->numberOrNull($values['sixty_yard_dash'] ?? null),
                    'broad_jump' => $this->numberOrNull($values['broad_jump'] ?? null),
                    'vertical_jump' => $this->numberOrNull($values['vertical_jump'] ?? null),
                ]),
                'player_assessment' => $this->saveAssessmentSnapshot($playerId, $teamId, $userId, $date, [
                    'type' => 'full',
                    'broad_jump_in' => $this->numberOrNull($values['broad_jump'] ?? null),
                    'vertical_jump_in' => $this->numberOrNull($values['vertical_jump'] ?? null),
                    'notes' => $payload['note'] ?? null,
                ]),
            ],
            'mobility_screen' => [
                'player_fitness' => $this->saveFitnessSnapshot($playerId, $date, [
                    'mobility_score' => $this->numberOrNull($values['mobility_score'] ?? null),
                ]),
                'player_assessment' => $this->saveAssessmentSnapshot($playerId, $teamId, $userId, $date, [
                    'type' => 'mobility',
                    'mobility_overall_score' => $this->numberOrNull($values['mobility_score'] ?? null),
                    'shoulder_mobility' => $this->mobilityTenPoint($values['shoulder_mobility_score'] ?? null),
                    'hip_mobility' => $this->mobilityTenPoint($values['hip_mobility_score'] ?? null),
                    'rotational_mobility' => $this->mobilityTenPoint($values['t_spine_mobility_score'] ?? null),
                    'notes' => $payload['note'] ?? null,
                ]),
            ],
            default => [],
        };
    }

    private function saveRosterCleanup(string $playerId, array $values, string $date): array
    {
        $user = User::query()->with(['profile', 'player'])->findOrFail($playerId);

        $user->player()->updateOrCreate(
            ['user_id' => $playerId],
            array_filter([
                'born_date' => $this->nullableString($values['born_date'] ?? null),
                'height_in_ft' => $this->numberOrNull($values['height_ft'] ?? null),
                'height_in_inch' => $this->numberOrNull($values['height_in'] ?? null),
                'grad_year' => $this->numberOrNull($values['grad_year'] ?? null),
                'throw_side' => $this->nullableString($values['throw_side'] ?? null),
                'hit_side' => $this->nullableString($values['hit_side'] ?? null),
            ], fn ($value) => $value !== null && $value !== '')
        );

        if (isset($values['level']) && $user->profile) {
            $user->profile->update(['level' => $this->nullableString($values['level'])]);
        }

        if (! empty($values['position'])) {
            PlayerPosition::query()->firstOrCreate([
                'player_id' => $playerId,
                'position' => strtoupper((string) $values['position']),
            ]);
        }

        $fitness = null;
        if ($this->numberOrNull($values['body_weight'] ?? null) !== null) {
            $fitness = $this->saveFitnessSnapshot($playerId, $date, [
                'body_weight' => $this->numberOrNull($values['body_weight'] ?? null),
            ]);
        }

        return [
            'player' => Player::query()->where('user_id', $playerId)->first()?->only([
                'user_id',
                'born_date',
                'height_in_ft',
                'height_in_inch',
                'grad_year',
                'throw_side',
                'hit_side',
            ]),
            'position' => $values['position'] ?? null,
            'player_fitness' => $fitness,
        ];
    }

    private function saveFitnessSnapshot(string $playerId, string $date, array $data): array
    {
        $payload = array_filter([
            'user_id' => $playerId,
            'fitness_date' => $date,
            ...$data,
        ], fn ($value) => $value !== null && $value !== '');

        $fitness = PlayerFitness::query()->updateOrCreate(
            ['user_id' => $playerId, 'fitness_date' => $date],
            $payload
        );

        return $fitness->fresh()?->only(array_keys($payload)) ?? $payload;
    }

    private function saveAssessmentSnapshot(string $playerId, string $teamId, ?string $assessedBy, string $date, array $data): array
    {
        $payload = array_filter([
            'user_id' => $playerId,
            'team_id' => $teamId ?: null,
            'assessed_by' => $assessedBy,
            'assessment_date' => $date,
            ...$data,
        ], fn ($value) => $value !== null && $value !== '');

        $assessment = PlayerAssessment::query()->updateOrCreate(
            [
                'user_id' => $playerId,
                'team_id' => $teamId ?: null,
                'assessment_date' => $date,
                'type' => $payload['type'] ?? null,
            ],
            $payload
        );

        return $assessment->fresh()?->only(array_keys($payload)) ?? $payload;
    }

    private function existingDataSummary(BenchmarkCollectionTask $task): array
    {
        $playerId = (string) ($task->assigned_to_player_id ?? '');
        $teamId = (string) ($task->team_id ?? '');
        $user = $task->relationLoaded('assignedPlayer') ? $task->assignedPlayer : null;

        return match ((string) $task->task_type) {
            'roster_cleanup' => $this->rosterSummary($user),
            'exit_velocity_baseline' => $this->exitVelocitySummary($playerId, $teamId),
            'bullpen_baseline' => $this->bullpenSummary($playerId, $teamId),
            'long_toss_weighted_ball' => $this->longTossWeightedSummary($playerId, $teamId),
            'strength_baseline' => $this->fitnessSummary($playerId, ['bench_press', 'back_squat', 'front_squat', 'dead_lift', 'pull_ups', 'push_ups']),
            'athletic_testing' => $this->fitnessSummary($playerId, ['yd_40_dash', 'yd_60_dash', 'broad_jump', 'vertical_jump']),
            'mobility_screen' => $this->mobilitySummary($playerId),
            default => [],
        };
    }

    private function rosterSummary(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $fitness = PlayerFitness::query()
            ->where('user_id', $user->id)
            ->orderByDesc('fitness_date')
            ->orderByDesc('created_at')
            ->first();

        return [
            'born_date' => $user->player?->born_date,
            'positions' => $user->positions?->pluck('position')->values()->all() ?? [],
            'height_ft' => $user->player?->height_in_ft,
            'height_in' => $user->player?->height_in_inch,
            'body_weight' => $this->numberOrNull($fitness?->body_weight),
            'throw_side' => $user->player?->throw_side,
            'hit_side' => $user->player?->hit_side,
            'level' => $user->profile?->level,
        ];
    }

    private function exitVelocitySummary(string $playerId, string $teamId): array
    {
        $rows = ExitVelocityPractice::query()
            ->where('user_id', $playerId)
            ->where(fn ($query) => $query->where('team_id', $teamId)->orWhereNull('team_id'))
            ->orderByDesc('created_at')
            ->get();

        $velos = $rows->pluck('velocity')->filter(fn ($value) => $this->numberOrNull($value) !== null);

        return [
            'row_count' => $rows->count(),
            'latest_date' => $rows->first()?->created_at?->toDateString(),
            'average_exit_velocity' => $velos->isNotEmpty() ? round((float) $velos->avg(), 1) : null,
            'max_exit_velocity' => $velos->isNotEmpty() ? round((float) $velos->max(), 1) : null,
        ];
    }

    private function bullpenSummary(string $playerId, string $teamId): array
    {
        $rows = BullpenPracticeResult::query()
            ->where('pitcher_id', $playerId)
            ->where('team_id', $teamId)
            ->orderByDesc('created_at')
            ->get();

        $velos = $rows->pluck('miles_per_hour')->filter(fn ($value) => $this->numberOrNull($value) !== null);
        $strikeCount = $rows->where('is_strike', true)->count();

        return [
            'row_count' => $rows->count(),
            'latest_date' => $rows->first()?->created_at?->toDateString(),
            'average_fastball_velocity' => $velos->isNotEmpty() ? round((float) $velos->avg(), 1) : null,
            'max_fastball_velocity' => $velos->isNotEmpty() ? round((float) $velos->max(), 1) : null,
            'strike_percentage' => $rows->isNotEmpty() ? round(($strikeCount / $rows->count()) * 100, 1) : null,
        ];
    }

    private function longTossWeightedSummary(string $playerId, string $teamId): array
    {
        $longToss = LongTossPractice::query()
            ->where('user_id', $playerId)
            ->where(fn ($query) => $query->where('team_id', $teamId)->orWhereNull('team_id'))
            ->orderByDesc('created_at')
            ->get();
        $weightedBall = WeightBallPractice::query()
            ->where('user_id', $playerId)
            ->where(fn ($query) => $query->where('team_id', $teamId)->orWhereNull('team_id'))
            ->orderByDesc('created_at')
            ->get();
        $fiveOz = $weightedBall->filter(fn ($row) => trim((string) $row->weight) === '5' || str_contains(strtolower((string) $row->weight), '5'));

        return [
            'long_toss_rows' => $longToss->count(),
            'weighted_ball_rows' => $weightedBall->count(),
            'latest_long_toss_date' => $longToss->first()?->created_at?->toDateString(),
            'latest_weighted_ball_date' => $weightedBall->first()?->created_at?->toDateString(),
            'long_toss_max_distance' => $longToss->isNotEmpty() ? $this->numberOrNull($longToss->max('distance')) : null,
            'weighted_ball_5oz_velocity' => $fiveOz->isNotEmpty() ? $this->numberOrNull($fiveOz->max('velocity')) : null,
        ];
    }

    private function fitnessSummary(string $playerId, array $fields): array
    {
        $fitness = PlayerFitness::query()
            ->where('user_id', $playerId)
            ->orderByDesc('fitness_date')
            ->orderByDesc('created_at')
            ->first();

        $summary = [
            'latest_fitness_date' => $fitness?->fitness_date?->toDateString(),
        ];

        foreach ($fields as $field) {
            $summary[$field] = $this->numberOrNull($fitness?->{$field});
        }

        return $summary;
    }

    private function mobilitySummary(string $playerId): array
    {
        $summary = $this->fitnessSummary($playerId, ['mobility_score']);
        $assessment = PlayerAssessment::query()
            ->where('user_id', $playerId)
            ->orderByDesc('assessment_date')
            ->orderByDesc('created_at')
            ->first();

        return [
            ...$summary,
            'latest_assessment_date' => $assessment?->assessment_date?->toDateString(),
            'shoulder_mobility_score' => $this->numberOrNull($assessment?->shoulder_mobility),
            'hip_mobility_score' => $this->numberOrNull($assessment?->hip_mobility),
            't_spine_mobility_score' => $this->numberOrNull($assessment?->rotational_mobility),
        ];
    }

    private function hasExistingData(string $taskType, array $summary): bool
    {
        return match ($taskType) {
            'roster_cleanup' => ! empty($summary['born_date'])
                && ! empty($summary['positions'])
                && $summary['height_ft'] !== null
                && $summary['body_weight'] !== null
                && ! empty($summary['throw_side'])
                && ! empty($summary['hit_side']),
            'exit_velocity_baseline' => $summary['average_exit_velocity'] !== null && $summary['max_exit_velocity'] !== null,
            'bullpen_baseline' => $summary['average_fastball_velocity'] !== null && $summary['max_fastball_velocity'] !== null && $summary['strike_percentage'] !== null,
            'long_toss_weighted_ball' => $summary['long_toss_max_distance'] !== null && $summary['weighted_ball_5oz_velocity'] !== null,
            'strength_baseline' => $summary['bench_press'] !== null && ($summary['back_squat'] !== null || $summary['front_squat'] !== null) && $summary['dead_lift'] !== null,
            'athletic_testing' => $summary['yd_40_dash'] !== null && $summary['yd_60_dash'] !== null && $summary['broad_jump'] !== null && $summary['vertical_jump'] !== null,
            'mobility_screen' => $summary['mobility_score'] !== null,
            default => false,
        };
    }

    private function completionRules(BenchmarkCollectionTask $task, string $mode, bool $existingDataFound): array
    {
        return [
            'draft_tasks_cannot_be_completed' => $task->status === BenchmarkCollectionTask::STATUS_DRAFT,
            'dismissed_tasks_cannot_be_completed' => $task->status === BenchmarkCollectionTask::STATUS_DISMISSED,
            'completed_tasks_are_final' => $task->status === BenchmarkCollectionTask::STATUS_COMPLETED,
            'requires_existing_data_or_manual_confirm' => $mode !== 'inline_form',
            'existing_data_found' => $existingDataFound,
        ];
    }

    private function field(string $key, string $label, string $type, ?string $unit = null, mixed $min = null, mixed $max = null, ?array $options = null, mixed $step = 1): array
    {
        return array_filter([
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'unit' => $unit,
            'min' => $min,
            'max' => $max,
            'step' => $step,
            'options' => $options,
        ], fn ($value) => $value !== null);
    }

    private function completionValues(array $payload): array
    {
        $values = is_array($payload['values'] ?? null) ? $payload['values'] : $payload;
        unset($values['manual_confirm'], $values['note'], $values['coach_notes'], $values['date'], $values['fitness_date']);

        return $values;
    }

    private function missingRequiredFields(array $fields, array $values): array
    {
        $missing = [];
        foreach ($fields as $field) {
            $key = is_array($field) ? (string) ($field['key'] ?? '') : (string) $field;
            if ($key === '') {
                continue;
            }
            if (! array_key_exists($key, $values) || $values[$key] === null || $values[$key] === '') {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    private function manualConfirmRequested(array $payload): bool
    {
        return filter_var($payload['manual_confirm'] ?? false, FILTER_VALIDATE_BOOL);
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

        return $value === '' ? null : $value;
    }

    private function result(bool $ok, array $payload): array
    {
        return [
            'ok' => $ok,
            'generated_at' => now()->toIso8601String(),
            ...$payload,
        ];
    }
}
