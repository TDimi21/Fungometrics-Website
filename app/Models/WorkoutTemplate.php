<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasUuid;

class WorkoutTemplate extends Model
{
    use HasUuid;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['is_premade' => 'boolean','is_public' => 'boolean','is_active' => 'boolean','version' => 'integer'];
    public function sections()
    {
        return $this->hasMany(WorkoutTemplateSection::class)->orderBy('sort_order');
    }
}
