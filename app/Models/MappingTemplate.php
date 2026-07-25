<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MappingTemplate extends Model
{
    use HasUuid;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];

    public function versions(): HasMany
    {
        return $this->hasMany(MappingTemplateVersion::class);
    }
}
