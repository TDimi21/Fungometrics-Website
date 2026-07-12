<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyPlanRevision extends Model
{
    use HasFactory;
    use HasUuid;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'daily_plan_id',
        'team_id',
        'created_by_user_id',
        'revision_number',
        'source',
        'change_type',
        'title_before',
        'title_after',
        'status_before',
        'status_after',
        'plan_before',
        'plan_after',
        'diff_summary',
        'applied_suggestions',
        'reason',
        'coach_notes',
    ];

    protected $casts = [
        'id' => 'string',
        'daily_plan_id' => 'string',
        'team_id' => 'string',
        'created_by_user_id' => 'string',
        'revision_number' => 'integer',
        'plan_before' => 'array',
        'plan_after' => 'array',
        'diff_summary' => 'array',
        'applied_suggestions' => 'array',
    ];

    public function dailyPlan(): BelongsTo
    {
        return $this->belongsTo(DailyPlan::class, 'daily_plan_id', 'id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id', 'id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'id');
    }
}
