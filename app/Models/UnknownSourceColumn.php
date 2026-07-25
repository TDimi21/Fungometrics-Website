<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class UnknownSourceColumn extends Model
{
    use HasUuid;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['sample_values' => 'array','first_seen_at' => 'datetime','last_seen_at' => 'datetime'];
}
