<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlayerAssessment extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'user_id',
        'team_id',
        'assessed_by',
        'assessment_date',
        'type',
        // raw
        'squat_lbs',
        'deadlift_lbs',
        'bench_lbs',
        'broad_jump_in',
        'vertical_jump_in',
        'sprint_10yd_sec',
        // strength percentiles
        'squat_percentile',
        'deadlift_percentile',
        'lunge_percentile',
        'bench_press_percentile',
        'pull_up_percentile',
        'push_up_percentile',
        'broad_jump_percentile',
        'vertical_jump_percentile',
        'sprint_10yd_percentile',
        'med_ball_rotational_percentile',
        'exit_velocity_percentile',
        'bat_speed_percentile',
        // mobility
        'hip_mobility',
        'shoulder_mobility',
        'ankle_mobility',
        'hip_flexor_mobility',
        'rotational_mobility',
        // computed scores
        'strength_lower_body_score',
        'strength_upper_body_score',
        'strength_explosive_score',
        'strength_rotational_score',
        'strength_overall_score',
        'mobility_overall_score',
        'overall_score',
        'team_percentiles',
        'age_group_percentiles',
        'overall_team_percentile',
        'overall_age_percentile',
        'age_group_years',
        'notes',
    ];

    protected $casts = [
        'id'               => 'string',
        'user_id'          => 'string',
        'team_id'          => 'string',
        'assessed_by'      => 'string',
        'assessment_date'  => 'date',
        'team_percentiles' => 'array',
        'age_group_percentiles' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'user_id', 'user_id');
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }
}
