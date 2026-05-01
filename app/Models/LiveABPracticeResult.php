<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LiveABPracticeResult extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $casts = [
        'id' => 'string',
        'practice_id' => 'string',
        'is_hit' => 'boolean',
        'is_strike' => 'boolean',
        'is_ball' => 'boolean',
        'batting_result_id' => 'string',
        'pitching_result_id' => 'string',
    ];

    protected $fillable = [
        'turn',
        'turn_pitches',
        'turn_is_over',
        'turn_strike',
        'turn_ball',
        'sort',
        'bases',
        'practice_id',
        'is_hit',
        'is_strike',
        'is_ball',
        'batting_result_id',
        'pitching_result_id',
        'count_b_s'
    ];

    public function practice(): BelongsTo
    {
        return $this->belongsTo(Practice::class);
    }

  public function batting(): BelongsTo
  {
      return $this->belongsTo(BattingPracticeResult::class, 'batting_result_id', 'id');
  }


  public function pitching(): BelongsTo
  {
      return $this->belongsTo(BullpenPracticeResult::class, 'pitching_result_id', 'id');
  }

  public function teams(): HasMany
  {
      return $this->hasMany(TeamsLiveAB::class, 'practice_id', 'practice_id');
  }
}
