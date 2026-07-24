<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class CagePracticeResult extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $casts =[
        'id'=>'string',
        'practice_id'=>'string',
        'user_id'=>'string',
        'team_id'=>'string',
        'ground_ball' => 'boolean',
        'distance_model_meta' => 'array'
    ];

    protected $fillable =[
        'practice_id',
        'user_id',
        'team_id',
        'launch_angle',
        'launch_angle_velocity',
        'spray_angle',
        'distance_travel',
        'ground_ball',
        'cage_mark',
        'cage_position',
        'sort',
        'distance_model_version',
        'distance_model_meta',
        'estimated_carry_v2',
        'estimated_carry_low_v2',
        'estimated_carry_high_v2',
        'distance_confidence_v2'
    ];

    public function practice(): BelongsTo
    {
        return $this->belongsTo(Practice::class);
    }

  public function profile(): HasOne
  {
      return $this->hasOne(Profile::class, 'user_id', 'user_id');
  }
}
