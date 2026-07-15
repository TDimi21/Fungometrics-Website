<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasUuid;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'name', 'audience', 'active', 'metadata'];
    protected $casts = ['active' => 'boolean', 'metadata' => 'array'];

    public function entitlements(): HasMany
    {
        return $this->hasMany(PlanEntitlement::class);
    }
}
