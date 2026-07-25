<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class UnitDefinition extends Model
{
    use HasUuid;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
}
