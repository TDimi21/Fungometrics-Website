<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DailyPlan extends Model
{
    use HasFactory;
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id',
        'team_id',
        'created_by',
        'name',
        'date',
        'phase',
        'primary_goal',
        'estimated_minutes',
        'workload_level',
        'status',
        'buckets',
        'published_at',
    ];

    protected $casts = [
        'buckets'      => 'array',
        'date'         => 'date:Y-m-d',
        'published_at' => 'datetime',
    ];

    // Expose the assigned player ids as a flat array (matches the app's plan shape).
    protected $appends = ['assigned_player_ids'];

    public function assignments(): HasMany
    {
        return $this->hasMany(DailyPlanAssignment::class, 'plan_id');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(DailyPlanProgress::class, 'plan_id');
    }

    /**
     * @return array<int, string>
     */
    public function getAssignedPlayerIdsAttribute(): array
    {
        $assignments = $this->relationLoaded('assignments')
            ? $this->assignments
            : $this->assignments();

        return $assignments->pluck('user_id')->all();
    }
}
