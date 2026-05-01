<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class BattingPracticeResult extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $casts = [
        'id' => 'string',
        'is_contact' => 'boolean',
        'is_in_match' => 'boolean',
        'created_at' => 'datetime:Y-m-d',
        'updated_at' => 'datetime:Y-m-d',
        'deleted_at' => 'datetime:Y-m-d h:i:s'
    ];

    protected $fillable = [
        'practice_id',
        'team_id',
        'batter_id',
        'is_contact',
        'pitch_location',
        'quality_of_contact',
        'type_of_hit',
        'field_mark',
        'pitch_mark',
        'is_in_match',
        'field_direction',
        'velocity',
        'sort',
        'zone'
    ];

    public function practice(): BelongsTo
    {
        return $this->belongsTo(Practice::class);
    }

  public function profile(): HasOne
  {
      return $this->hasOne(Profile::class, 'user_id', 'batter_id');
  }

  public function livePractice(): HasOne
  {
      return $this->hasOne(LiveABPracticeResult::class, 'batting_result_id', 'id');
  }
}
