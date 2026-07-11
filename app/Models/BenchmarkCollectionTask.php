<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BenchmarkCollectionTask extends Model
{
    use HasFactory;
    use HasUuid;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_DISMISSED = 'dismissed';

    public const REVIEW_NOT_REQUIRED = 'not_required';
    public const REVIEW_PENDING = 'pending_review';
    public const REVIEW_APPROVED = 'approved';
    public const REVIEW_REJECTED = 'rejected';
    public const REVIEW_CORRECTION_REQUESTED = 'correction_requested';

    public const ACTIVE_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ASSIGNED,
        self::STATUS_IN_PROGRESS,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'team_id',
        'assigned_to_player_id',
        'created_by_user_id',
        'source',
        'temporary_key',
        'task_type',
        'title',
        'description',
        'priority',
        'status',
        'review_status',
        'submitted_by_user_id',
        'reviewed_by_user_id',
        'due_window',
        'estimated_minutes',
        'metrics',
        'missing_fields',
        'instructions',
        'coach_notes',
        'payload',
        'submitted_payload',
        'approved_payload',
        'assigned_at',
        'completed_at',
        'submitted_at',
        'reviewed_at',
        'dismissed_at',
        'review_notes',
        'rejection_reason',
        'correction_message',
    ];

    protected $casts = [
        'metrics' => 'array',
        'missing_fields' => 'array',
        'instructions' => 'array',
        'payload' => 'array',
        'submitted_payload' => 'array',
        'approved_payload' => 'array',
        'assigned_at' => 'datetime',
        'completed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function assignedPlayer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_player_id', 'id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id', 'id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id', 'id');
    }
}
