<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScriptedBpSwing extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $casts = [
        'id'               => 'string',
        'score_modifiers'  => 'array',
        'exit_velocity'    => 'integer',
        'raw_score'        => 'integer',
        'round_swing_index'=> 'integer',
        'sort'             => 'integer',
        'created_at'       => 'datetime:Y-m-d',
        'updated_at'       => 'datetime:Y-m-d',
        'deleted_at'       => 'datetime:Y-m-d H:i:s',
    ];

    protected $fillable = [
        'practice_id',
        'batter_id',
        'round_type',
        'round_swing_index',
        'contact_type',
        'trajectory',
        'direction',
        'exit_velocity',
        'raw_score',
        'score_modifiers',
        'sort',
    ];

    public function practice(): BelongsTo
    {
        return $this->belongsTo(Practice::class);
    }

    public function batter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'batter_id');
    }
}
