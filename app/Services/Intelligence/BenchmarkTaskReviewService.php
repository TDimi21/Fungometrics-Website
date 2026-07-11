<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use App\Models\BenchmarkCollectionTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class BenchmarkTaskReviewService
{
    public function __construct(
        private readonly BenchmarkTaskPersistenceService $taskPersistence,
        private readonly BenchmarkTrustedDataPromotionService $trustedDataPromotionService,
    ) {
    }

    public function listPendingReviewTasks(string $teamId, array $filters = []): array
    {
        try {
            $query = BenchmarkCollectionTask::query()
                ->with(['assignedPlayer.profile', 'submittedBy.profile', 'reviewedBy.profile', 'promotedBy.profile'])
                ->where('team_id', $teamId)
                ->where('review_status', BenchmarkCollectionTask::REVIEW_PENDING)
                ->orderByDesc('submitted_at')
                ->orderByDesc('updated_at');

            if (! empty($filters['player_id'])) {
                $query->where('assigned_to_player_id', (string) $filters['player_id']);
            }

            if (! empty($filters['task_type'])) {
                $query->where('task_type', (string) $filters['task_type']);
            }

            if (! empty($filters['priority'])) {
                $query->where('priority', (string) $filters['priority']);
            }

            $tasks = $query->get()
                ->map(fn (BenchmarkCollectionTask $task): array => $this->reviewTaskPayload($task))
                ->values()
                ->all();

            return $this->result(true, [
                'team_id' => $teamId,
                'pending_count' => count($tasks),
                'tasks' => $tasks,
                'filters' => $filters,
            ]);
        } catch (Throwable $exception) {
            return $this->result(false, [
                'team_id' => $teamId,
                'pending_count' => 0,
                'tasks' => [],
                'error' => class_basename($exception),
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function approveTask(string $taskId, ?string $reviewedByUserId = null, array $options = []): array
    {
        try {
            $task = BenchmarkCollectionTask::query()
                ->with(['assignedPlayer.profile', 'submittedBy.profile', 'reviewedBy.profile', 'promotedBy.profile'])
                ->find($taskId);

            if (! $task) {
                return $this->taskNotFound('approve', $taskId);
            }

            $reviewedTask = DB::transaction(function () use ($task, $reviewedByUserId, $options): BenchmarkCollectionTask {
                $approvedPayload = is_array($options['approved_payload'] ?? null)
                    ? $options['approved_payload']
                    : ($task->submitted_payload ?? $task->payload['completion'] ?? []);

                $task->status = BenchmarkCollectionTask::STATUS_COMPLETED;
                $task->review_status = BenchmarkCollectionTask::REVIEW_APPROVED;
                $task->reviewed_by_user_id = $reviewedByUserId;
                $task->reviewed_at = now();
                $task->review_notes = $this->nullableString($options['review_notes'] ?? $options['note'] ?? null);
                $task->rejection_reason = null;
                $task->correction_message = null;
                $task->approved_payload = $approvedPayload;
                $task->completed_at ??= now();
                $task->save();

                return $task->fresh(['assignedPlayer.profile', 'submittedBy.profile', 'reviewedBy.profile', 'promotedBy.profile']);
            });

            $promotion = $this->trustedDataPromotionService->promoteApprovedTask($taskId, $reviewedByUserId, [
                'days' => $this->days($options['days'] ?? 365),
                'overwrite' => (bool) ($options['overwrite'] ?? false),
            ]);
            $refresh = $promotion['refresh'] ?? null;

            return $this->result(true, [
                'action' => 'approve',
                'task_id' => $taskId,
                'team_id' => $reviewedTask->team_id,
                'player_id' => $reviewedTask->assigned_to_player_id,
                'review_status' => $reviewedTask->review_status,
                'task' => $this->reviewTaskPayload($reviewedTask->fresh(['assignedPlayer.profile', 'submittedBy.profile', 'reviewedBy.profile', 'promotedBy.profile'])),
                'promotion' => $promotion,
                'refresh' => $refresh,
                'message' => ($promotion['promotion_status'] ?? null) === BenchmarkCollectionTask::PROMOTION_FAILED
                    ? 'Task approved, but trusted data promotion failed. Review promotion warnings.'
                    : 'Task approved, trusted data promoted, and benchmark intelligence refreshed.',
            ]);
        } catch (Throwable $exception) {
            return $this->exceptionResult('approve', $taskId, $exception);
        }
    }

    public function rejectTask(string $taskId, string $reason, ?string $reviewedByUserId = null): array
    {
        try {
            $task = BenchmarkCollectionTask::query()
                ->with(['assignedPlayer.profile', 'submittedBy.profile', 'reviewedBy.profile', 'promotedBy.profile'])
                ->find($taskId);

            if (! $task) {
                return $this->taskNotFound('reject', $taskId);
            }

            $reviewedTask = DB::transaction(function () use ($task, $reason, $reviewedByUserId): BenchmarkCollectionTask {
                $task->status = BenchmarkCollectionTask::STATUS_ASSIGNED;
                $task->review_status = BenchmarkCollectionTask::REVIEW_REJECTED;
                $task->reviewed_by_user_id = $reviewedByUserId;
                $task->reviewed_at = now();
                $task->rejection_reason = trim($reason);
                $task->correction_message = null;
                $task->approved_payload = null;
                $task->save();

                return $task->fresh(['assignedPlayer.profile', 'submittedBy.profile', 'reviewedBy.profile', 'promotedBy.profile']);
            });

            return $this->result(true, [
                'action' => 'reject',
                'task_id' => $taskId,
                'team_id' => $reviewedTask->team_id,
                'player_id' => $reviewedTask->assigned_to_player_id,
                'review_status' => $reviewedTask->review_status,
                'task' => $this->reviewTaskPayload($reviewedTask),
                'refresh' => null,
            ]);
        } catch (Throwable $exception) {
            return $this->exceptionResult('reject', $taskId, $exception);
        }
    }

    public function requestCorrection(string $taskId, string $message, ?string $reviewedByUserId = null): array
    {
        try {
            $task = BenchmarkCollectionTask::query()
                ->with(['assignedPlayer.profile', 'submittedBy.profile', 'reviewedBy.profile', 'promotedBy.profile'])
                ->find($taskId);

            if (! $task) {
                return $this->taskNotFound('request_correction', $taskId);
            }

            $reviewedTask = DB::transaction(function () use ($task, $message, $reviewedByUserId): BenchmarkCollectionTask {
                $task->status = BenchmarkCollectionTask::STATUS_IN_PROGRESS;
                $task->review_status = BenchmarkCollectionTask::REVIEW_CORRECTION_REQUESTED;
                $task->reviewed_by_user_id = $reviewedByUserId;
                $task->reviewed_at = now();
                $task->correction_message = trim($message);
                $task->rejection_reason = null;
                $task->approved_payload = null;
                $task->save();

                return $task->fresh(['assignedPlayer.profile', 'submittedBy.profile', 'reviewedBy.profile', 'promotedBy.profile']);
            });

            return $this->result(true, [
                'action' => 'request_correction',
                'task_id' => $taskId,
                'team_id' => $reviewedTask->team_id,
                'player_id' => $reviewedTask->assigned_to_player_id,
                'review_status' => $reviewedTask->review_status,
                'task' => $this->reviewTaskPayload($reviewedTask),
                'refresh' => null,
            ]);
        } catch (Throwable $exception) {
            return $this->exceptionResult('request_correction', $taskId, $exception);
        }
    }

    public function buildTeamReviewSummary(string $teamId): array
    {
        try {
            $tasks = BenchmarkCollectionTask::query()
                ->with(['assignedPlayer.profile', 'submittedBy.profile', 'reviewedBy.profile', 'promotedBy.profile'])
                ->where('team_id', $teamId)
                ->whereNotNull('review_status')
                ->orderByDesc('updated_at')
                ->get();

            $pending = $tasks->where('review_status', BenchmarkCollectionTask::REVIEW_PENDING)
                ->values()
                ->map(fn (BenchmarkCollectionTask $task): array => $this->reviewTaskPayload($task))
                ->all();

            $recentReviewed = $tasks
                ->whereIn('review_status', [
                    BenchmarkCollectionTask::REVIEW_APPROVED,
                    BenchmarkCollectionTask::REVIEW_REJECTED,
                    BenchmarkCollectionTask::REVIEW_CORRECTION_REQUESTED,
                ])
                ->take(10)
                ->values()
                ->map(fn (BenchmarkCollectionTask $task): array => $this->reviewTaskPayload($task))
                ->all();

            $counts = $tasks->countBy(fn (BenchmarkCollectionTask $task): string => (string) ($task->review_status ?: 'none'))->all();

            return $this->result(true, [
                'team_id' => $teamId,
                'task_count' => $tasks->count(),
                'pending_count' => count($pending),
                'approved_count' => (int) ($counts[BenchmarkCollectionTask::REVIEW_APPROVED] ?? 0),
                'rejected_count' => (int) ($counts[BenchmarkCollectionTask::REVIEW_REJECTED] ?? 0),
                'correction_requested_count' => (int) ($counts[BenchmarkCollectionTask::REVIEW_CORRECTION_REQUESTED] ?? 0),
                'not_required_count' => (int) ($counts[BenchmarkCollectionTask::REVIEW_NOT_REQUIRED] ?? 0),
                'counts_by_review_status' => $counts,
                'pending_tasks' => $pending,
                'recent_reviewed_tasks' => $recentReviewed,
            ]);
        } catch (Throwable $exception) {
            return $this->result(false, [
                'team_id' => $teamId,
                'task_count' => 0,
                'pending_count' => 0,
                'pending_tasks' => [],
                'recent_reviewed_tasks' => [],
                'error' => class_basename($exception),
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function recordCompletionSubmission(string $taskId, ?string $submittedByUserId, array $submittedPayload, array $options = []): array
    {
        try {
            $task = BenchmarkCollectionTask::query()
                ->with(['assignedPlayer.profile', 'submittedBy.profile', 'reviewedBy.profile', 'promotedBy.profile'])
                ->find($taskId);

            if (! $task) {
                return $this->taskNotFound('record_completion_submission', $taskId);
            }

            $reviewStatus = $options['review_status'] ?? $this->reviewStatusForSubmitter($submittedByUserId, $submittedPayload);
            $reviewedByUserId = in_array($reviewStatus, [
                BenchmarkCollectionTask::REVIEW_APPROVED,
                BenchmarkCollectionTask::REVIEW_NOT_REQUIRED,
            ], true) ? $submittedByUserId : null;

            $reviewedTask = DB::transaction(function () use ($task, $submittedByUserId, $submittedPayload, $reviewStatus, $reviewedByUserId): BenchmarkCollectionTask {
                $task->review_status = $reviewStatus;
                $task->submitted_by_user_id = $submittedByUserId;
                $task->submitted_at = now();
                $task->submitted_payload = $submittedPayload;
                $task->review_notes = null;
                $task->rejection_reason = null;
                $task->correction_message = null;

                if ($reviewStatus === BenchmarkCollectionTask::REVIEW_APPROVED) {
                    $task->reviewed_by_user_id = $reviewedByUserId;
                    $task->reviewed_at = now();
                    $task->approved_payload = $submittedPayload;
                } elseif ($reviewStatus === BenchmarkCollectionTask::REVIEW_NOT_REQUIRED) {
                    $task->reviewed_by_user_id = null;
                    $task->reviewed_at = null;
                    $task->approved_payload = $submittedPayload;
                } else {
                    $task->reviewed_by_user_id = null;
                    $task->reviewed_at = null;
                    $task->approved_payload = null;
                }

                $task->save();

                return $task->fresh(['assignedPlayer.profile', 'submittedBy.profile', 'reviewedBy.profile', 'promotedBy.profile']);
            });

            return $this->result(true, [
                'action' => 'record_completion_submission',
                'task_id' => $taskId,
                'team_id' => $reviewedTask->team_id,
                'player_id' => $reviewedTask->assigned_to_player_id,
                'review_status' => $reviewedTask->review_status,
                'task' => $this->reviewTaskPayload($reviewedTask),
                'requires_review' => $reviewedTask->review_status === BenchmarkCollectionTask::REVIEW_PENDING,
            ]);
        } catch (Throwable $exception) {
            return $this->exceptionResult('record_completion_submission', $taskId, $exception);
        }
    }

    public function reviewStatusForTask(string $taskId, string $playerId): array
    {
        $task = BenchmarkCollectionTask::query()
            ->with(['assignedPlayer.profile', 'submittedBy.profile', 'reviewedBy.profile', 'promotedBy.profile'])
            ->whereKey($taskId)
            ->where('assigned_to_player_id', $playerId)
            ->where('status', '!=', BenchmarkCollectionTask::STATUS_DRAFT)
            ->first();

        if (! $task) {
            return $this->taskNotFound('review_status', $taskId);
        }

        return $this->result(true, [
            'task_id' => $taskId,
            'team_id' => $task->team_id,
            'player_id' => $playerId,
            'review_status' => $task->review_status,
            'submitted_at' => $task->submitted_at?->toIso8601String(),
            'reviewed_at' => $task->reviewed_at?->toIso8601String(),
            'review_notes' => $task->review_notes,
            'rejection_reason' => $task->rejection_reason,
            'correction_message' => $task->correction_message,
            'task' => $this->reviewTaskPayload($task),
        ]);
    }

    public function reviewTaskPayload(BenchmarkCollectionTask $task): array
    {
        $serialized = $this->taskPersistence->serializeTask($task) ?? [];

        return [
            ...$serialized,
            'submitted_values_summary' => $this->submittedValuesSummary($serialized['submitted_payload'] ?? []),
            'review_state_label' => $this->reviewStateLabel($serialized['review_status'] ?? null),
        ];
    }

    private function reviewStatusForSubmitter(?string $submittedByUserId, array $submittedPayload): string
    {
        if (! $submittedByUserId) {
            return BenchmarkCollectionTask::REVIEW_NOT_REQUIRED;
        }

        $user = User::query()->find($submittedByUserId);
        if (! $user) {
            return BenchmarkCollectionTask::REVIEW_PENDING;
        }

        if ((string) $user->type === 'player') {
            return BenchmarkCollectionTask::REVIEW_PENDING;
        }

        return BenchmarkCollectionTask::REVIEW_APPROVED;
    }

    private function submittedValuesSummary(array $payload): array
    {
        $values = $payload['submitted_values'] ?? $payload['values'] ?? $payload;
        if (! is_array($values)) {
            return [];
        }

        return collect($values)
            ->filter(fn ($value, $key): bool => is_string($key) && ! in_array($key, ['manual_confirm', 'source', 'payload'], true))
            ->map(fn ($value, $key): array => [
                'key' => (string) $key,
                'label' => $this->headline((string) $key),
                'value' => is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES) : $value,
            ])
            ->values()
            ->all();
    }

    private function reviewStateLabel(?string $status): string
    {
        return match ($status) {
            BenchmarkCollectionTask::REVIEW_NOT_REQUIRED => 'No Review Required',
            BenchmarkCollectionTask::REVIEW_PENDING => 'Pending Coach Review',
            BenchmarkCollectionTask::REVIEW_APPROVED => 'Approved',
            BenchmarkCollectionTask::REVIEW_REJECTED => 'Rejected',
            BenchmarkCollectionTask::REVIEW_CORRECTION_REQUESTED => 'Correction Requested',
            default => 'Not Submitted',
        };
    }

    private function taskNotFound(string $action, string $taskId): array
    {
        return $this->result(false, [
            'action' => $action,
            'task_id' => $taskId,
            'error' => 'task_not_found',
            'task' => null,
        ]);
    }

    private function exceptionResult(string $action, string $taskId, Throwable $exception): array
    {
        return $this->result(false, [
            'action' => $action,
            'task_id' => $taskId,
            'error' => class_basename($exception),
            'message' => $exception->getMessage(),
            'task' => null,
        ]);
    }

    private function result(bool $ok, array $payload): array
    {
        return [
            'ok' => $ok,
            'generated_at' => now()->toIso8601String(),
            ...$payload,
        ];
    }

    private function days(mixed $days): int
    {
        return max(7, min(365, (int) $days));
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }

    private function headline(string $value): string
    {
        $value = trim(str_replace(['_', '-'], ' ', $value));

        return $value !== '' ? ucwords($value) : 'Benchmark Task';
    }
}
