<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class BullpenPracticeResult extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $casts = [
        'id' => 'string',
        'is_in_match' => 'boolean',
        'is_strike' => 'boolean',
        'created_at' => 'datetime:Y-m-d',
        'updated_at' => 'datetime:Y-m-d',
        'deleted_at' => 'datetime:Y-m-d h:i:s'
    ];

    protected $fillable = [
        'practice_id',
        'team_id',
        'pitcher_id',
        'pitch_side',
        'pitch_mark',
        'is_strike',
        'miles_per_hour',
        'type_throw',
        'trajectory',
        'is_in_match',
        'sort',
        'zone'
    ];

    public function practice(): BelongsTo
    {
        return $this->belongsTo(Practice::class);
    }
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class, 'user_id', 'pitcher_id');
    }
    public function livePractice(): HasOne
    {
        return $this->hasOne(LiveABPracticeResult::class, 'pitching_result_id', 'id');
    }

}
