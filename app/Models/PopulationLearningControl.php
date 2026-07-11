<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PopulationLearningControl extends Model
{
    use HasFactory;
    use HasUuid;

    public const STATUS_AUTO = 'auto';
    public const STATUS_RESEARCH_ONLY = 'research_only';
    public const STATUS_POPULATION_ENABLED = 'population_enabled';
    public const STATUS_COMPOSITE_ENABLED = 'composite_enabled';
    public const STATUS_DISABLED = 'disabled';
    public const STATUS_NEEDS_REVIEW = 'needs_review';

    public const STATUSES = [
        self::STATUS_AUTO,
        self::STATUS_RESEARCH_ONLY,
        self::STATUS_POPULATION_ENABLED,
        self::STATUS_COMPOSITE_ENABLED,
        self::STATUS_DISABLED,
        self::STATUS_NEEDS_REVIEW,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'metric_key',
        'category',
        'status',
        'population_enabled',
        'research_enabled',
        'composite_enabled',
        'minimum_sample_size',
        'minimum_confidence',
        'allow_global_bucket',
        'allow_exact_peer_bucket',
        'allow_age_bucket',
        'max_exclusion_rate',
        'admin_notes',
        'last_audit_summary',
        'last_reviewed_at',
        'reviewed_by_user_id',
    ];

    protected $casts = [
        'id' => 'string',
        'population_enabled' => 'boolean',
        'research_enabled' => 'boolean',
        'composite_enabled' => 'boolean',
        'minimum_sample_size' => 'integer',
        'allow_global_bucket' => 'boolean',
        'allow_exact_peer_bucket' => 'boolean',
        'allow_age_bucket' => 'boolean',
        'max_exclusion_rate' => 'float',
        'last_audit_summary' => 'array',
        'last_reviewed_at' => 'datetime',
    ];

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id', 'id');
    }
}
