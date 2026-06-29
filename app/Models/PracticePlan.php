<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PracticePlan extends Model
{
    use HasFactory;
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id',
        'team_id',
        'created_by',
        'title',
        'date',
        'start_time',
        'focus',
        'notes',
        'total_duration',
        'scheduled_minutes',
        'drill_count',
        'slots',
    ];

    protected $casts = [
        'slots' => 'array',
        'date'  => 'date:Y-m-d',
    ];
}
