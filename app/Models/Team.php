<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $casts = ['id' => 'string'];

    protected $fillable = [
        'name',
        'logo',
        'state',
        'zip',
    ];

    public function team_coaches(): HasMany
    {
        return $this->hasMany(CoachTeam::class);
    }

    public function team_players(): HasMany
    {
        return $this->hasMany(PlayerTeam::class);
    }

    public function practices(): BelongsTo
    {
        return  $this->belongsTo(Practice::class);
    }
}
