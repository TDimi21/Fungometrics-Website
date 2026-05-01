<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeamsLiveAB extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table ='teams_live_a_b_s';
    protected $casts = [
        'id' => 'string',
        'team_id' => 'string',
        'practice_id' => 'string',
    ];

    protected $fillable = [
        'team_id',
        'practice_id',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function practice(): BelongsTo
    {
        return $this->belongsTo(Practice::class);
    }
}
