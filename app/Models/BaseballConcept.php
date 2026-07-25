<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class BaseballConcept extends Model
{
    use HasUuid;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['metadata' => 'array','research_eligible' => 'boolean','profile_visible' => 'boolean','valid_min' => 'float','valid_max' => 'float'];
}
