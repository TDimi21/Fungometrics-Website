<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class SecurityAudit extends Model
{
    use HasUuid;

    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['metadata' => 'array', 'created_at' => 'datetime'];
}
