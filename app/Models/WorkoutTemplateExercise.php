<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasUuid;

class WorkoutTemplateExercise extends Model
{
    use HasUuid;
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['ball_weights' => 'array','metadata' => 'array','is_optional' => 'boolean'];
}
