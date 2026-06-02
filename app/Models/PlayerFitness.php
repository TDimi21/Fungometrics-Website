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
        'dead_lift',
        'yd_40_dash',
        'yd_60_dash',
        'body_weight',
        'sleep_hours',
        'sleep_quality_1_to_5',
        'recovery_score',
        'mobility_score',
        'strength_score',
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
        'dead_lift'=>'integer',
        'sleep_hours' => 'float',
        'sleep_quality_1_to_5' => 'integer',
        'recovery_score' => 'integer',
        'mobility_score' => 'integer',
        'strength_score' => 'integer',
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
