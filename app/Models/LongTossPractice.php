<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class LongTossPractice extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';
    protected $casts = [
        'practice_id' => 'string',
        'team_id' => 'string',
        'user_id' => 'string',
        'id' => 'string',
        'created_at' => 'datetime:Y-m-d',
        'updated_at' => 'datetime:Y-m-d',
        'deleted_at' => 'datetime:Y-m-d h:i:s'
    ];

    protected $fillable = [
        'practice_id',
        'user_id',
        'team_id',
        'set',
        'sort',
        'hop',
        'distance'
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
