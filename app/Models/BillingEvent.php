<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class BillingEvent extends Model
{
    use HasUuid;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
        'last_attempted_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'processing_attempts' => 'integer',
    ];
}
