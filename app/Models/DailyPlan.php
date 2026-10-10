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
        'version',
        'settings',

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

    protected $casts = ['version' => 'integer','settings' => 'array',
        'buckets'      => 'array',
        'date'         => 'date:Y-m-d',
        'published_at' => 'datetime',
    ];

    // Expose the assigned player ids as a flat array (matches the app's plan shape).
    protected $appends = ['assigned_player_ids'];

    protected static function booted(): void
    {
        static::saving(function (self $plan): void {
            $buckets = $plan->buckets ?? [];
            if (!collect($buckets)->contains(fn ($bucket) => ($bucket['type'] ?? null) === 'daily_readiness')) {
                // Append to preserve existing section order and template snapshot references.
                $buckets[] = ['type'=>'daily_readiness','title'=>'Daily Readiness','kind'=>'survey','items'=>[],'note'=>'Complete before starting your workout.'];
            }
            $reflection = collect($buckets)->firstWhere('type', 'player_reflection')
                ?? ['type'=>'player_reflection','title'=>'Player Reflection','kind'=>'survey','items'=>[],'note'=>'Complete after finishing your workout.'];
            $buckets = array_values(array_filter($buckets, fn ($bucket) => ($bucket['type'] ?? null) !== 'player_reflection'));
            $buckets[] = $reflection;
            $plan->buckets = $buckets;
        });
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DailyPlanAssignment::class, 'plan_id');
    }

    public function bucketsFor(string $playerId): array
    {
        $this->loadMissing('assignments');
        $assignment=$this->assignments->firstWhere('user_id',$playerId);
        return $assignment?->prescription_override['buckets']??$this->buckets??[];
    }

    public function progress(): HasMany
    {
        return $this->hasMany(DailyPlanProgress::class, 'plan_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(DailyPlanRevision::class, 'daily_plan_id');
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
