<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class TeamJoinChallenge extends Model
{
    use HasUuid;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $hidden = ['phone_hash', 'code_hash'];
    protected $casts = ['expires_at' => 'datetime', 'used_at' => 'datetime'];
}
