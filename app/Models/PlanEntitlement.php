<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanEntitlement extends Model
{
    use HasUuid;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['subscription_plan_id', 'entitlement_key', 'metadata'];
    protected $casts = ['metadata' => 'array'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }
}
