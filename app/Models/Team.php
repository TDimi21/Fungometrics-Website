<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

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
        'join_code',
        'is_dummy',
        'owner_team_id',
    ];

    public static function generateJoinCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (DB::table('teams')->where('join_code', $code)->exists());

        return $code;
    }

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
