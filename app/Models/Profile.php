<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Profile extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'first_name',
        'last_name',
        'user_id',
        'picture',
        'zip',
        'city',
        'state',
        'level',
    ];

    protected $casts = [
        'id' => 'string',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'user_id', 'user_id');
    }

}
