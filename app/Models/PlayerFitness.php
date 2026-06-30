<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlayerFitness extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'fitness_date',
        'bench_press',
        'front_squat',
        'back_squat',
        'power_clean',
        'hand_strength',
        'dead_lift',
        'vertical_jump',
        'broad_jump',
        'med_ball_rotational_throw',
        'sprint_10yd',
        'exit_velo',
        'bat_speed',
        'throwing_velo',
        'pitch_velo',
        'yd_40_dash',
        'yd_60_dash',
        'body_weight',
        'sleep_hours',
        'sleep_quality_1_to_5',
        'recovery_score',
        'mobility_score',
        'strength_score',
        'overall_api_score',
        'pull_ups',
        'push_ups',
    ];

    protected $casts =[
        'fitness_date' => 'date',
        'id' => 'string',
        'user_id' => 'string',
        'bench_press'=>'integer',
        'front_squat'=>'integer',
        'back_squat'=>'integer',
        'power_clean'=>'integer',
        'hand_strength' => 'float',
        'dead_lift'=>'integer',
        'vertical_jump' => 'float',
        'broad_jump' => 'float',
        'med_ball_rotational_throw' => 'float',
        'sprint_10yd' => 'float',
        'exit_velo' => 'float',
        'bat_speed' => 'float',
        'throwing_velo' => 'float',
        'pitch_velo' => 'float',
        'sleep_hours' => 'float',
        'sleep_quality_1_to_5' => 'integer',
        'recovery_score' => 'integer',
        'mobility_score' => 'integer',
        'strength_score' => 'integer',
        'overall_api_score' => 'float',
        'pull_ups' => 'integer',
        'push_ups' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class, 'user_id', 'user_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

}
