<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyPlanProgress extends Model
{
    use HasFactory;
    use HasUuid;

    protected $table = 'daily_plan_progress';

    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'version',
        'post_training',
        'actual_history',
        'alert_review',

        'plan_id',
        'user_id',
        'readiness',
        'items',
        'reflection',
        'coach_review',
        'started_at',
        'completed_at',
    ];

    protected $casts = ['version' => 'integer','post_training' => 'array','actual_history' => 'array','alert_review' => 'array',
        'readiness'    => 'array',
        'items'        => 'array',
        'reflection'   => 'array',
        'coach_review' => 'array',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(DailyPlan::class, 'plan_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
