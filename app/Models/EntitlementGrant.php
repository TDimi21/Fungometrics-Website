<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class EntitlementGrant extends Model
{
    use HasUuid;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'revoked_at' => 'datetime', 'metadata' => 'array'];

    protected static function booted(): void
    {
        static::saving(function (self $grant): void {
            if ((null === $grant->user_id) === (null === $grant->team_id)) {
                throw new InvalidArgumentException('An entitlement grant must belong to exactly one user or team.');
            }
        });
    }
}
