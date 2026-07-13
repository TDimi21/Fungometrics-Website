<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyReportNote extends Model
{
    use HasFactory;
    use HasUuid;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'team_id',
        'created_by_user_id',
        'week_start_date',
        'week_end_date',
        'audience',
        'note_type',
        'title',
        'body',
        'visibility',
        'player_id',
        'payload',
    ];

    protected $casts = [
        'week_start_date' => 'date:Y-m-d',
        'week_end_date' => 'date:Y-m-d',
        'payload' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_id', 'id');
    }
}
