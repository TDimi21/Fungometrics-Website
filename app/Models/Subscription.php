<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class Subscription extends Model
{
    use HasUuid;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = [
        'starts_at' => 'datetime', 'current_period_ends_at' => 'datetime',
        'grace_period_ends_at' => 'datetime', 'canceled_at' => 'datetime',
        'ended_at' => 'datetime', 'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $subscription): void {
            if ((null === $subscription->user_id) === (null === $subscription->team_id)) {
                throw new InvalidArgumentException('A subscription must belong to exactly one user or team.');
            }
        });
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
