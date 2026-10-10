<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkoutProgram extends Model
{
    protected static function booted(): void
    {
        static::creating(function ($model): void { if ( ! $model->id) { $model->id = (string) \Illuminate\Support\Str::uuid(); } });
    }
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['training_settings' => 'array','schedule' => 'array','version' => 'integer'];
}
