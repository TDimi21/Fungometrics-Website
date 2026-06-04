<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AthleticPerformanceScore extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'player_id',
        'team_id',
        'assessment_id',
        'role',
        'overall_api_score',
        'strength_score',
        'power_score',
        'speed_score',
        'baseball_score',
        'recovery_mobility_score',
        'lower_body_strength_score',
        'upper_body_strength_score',
        'relative_strength_score',
        'projection_label',
        'grade_label',
        'team_percentile',
        'team_rank',
        'team_count',
        'strengths',
        'weaknesses',
        'development_plan',
        'calculated_at',
    ];

    protected $casts = [
        'id' => 'string',
        'player_id' => 'string',
        'team_id' => 'string',
        'assessment_id' => 'string',
        'overall_api_score' => 'float',
        'strength_score' => 'float',
        'power_score' => 'float',
        'speed_score' => 'float',
        'baseball_score' => 'float',
        'recovery_mobility_score' => 'float',
        'lower_body_strength_score' => 'float',
        'upper_body_strength_score' => 'float',
        'relative_strength_score' => 'float',
        'team_percentile' => 'integer',
        'team_rank' => 'integer',
        'team_count' => 'integer',
        'strengths' => 'array',
        'weaknesses' => 'array',
        'development_plan' => 'array',
        'calculated_at' => 'datetime',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_id');
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(PlayerFitness::class, 'assessment_id');
    }
}
