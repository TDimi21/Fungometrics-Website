<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class SubscriptionAudit extends Model
{
    use HasUuid;

    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['before_state' => 'array', 'after_state' => 'array', 'created_at' => 'datetime'];
}
