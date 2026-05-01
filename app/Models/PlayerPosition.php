<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use App\Models\Concerns\UserTypes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerPosition extends Model
{
    use HasFactory;
    use HasUuid;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['position', 'player_id'];

    protected $casts = [
        'is_preferred' => 'boolean',
        'id' => 'string',
    ];

    public function user(): BelongsTo
    {
        $this->belongsTo(UserTypes::class, 'player_id', 'id');
    }
}
