<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\HasUuid;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use CanResetPassword;
    use HasApiTokens;
    use HasFactory;
    use HasUuid;
    use Notifiable;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'email',
        'phone',
        'type',
        'password',
        'status',
    ];

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'status' => 'boolean',
        'id' => 'string',
    ];

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function teamsCoach(): HasMany
    {
        return $this->hasMany(CoachTeam::class, 'coach_id', 'id');
    }

    public function fitness(): HasOne
    {
        return $this->hasOne(PlayerFitness::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(PlayerPosition::class, 'player_id', 'id');
    }

    public function team_players(): HasMany
    {
        return $this->hasMany(PlayerTeam::class);
    }

    public function player(): HasOne
    {
        return $this->HasOne(Player::class);
    }

    public function smsLog(): HasMany
    {
        return $this->hasMany(SmsLog::class);
    }
}
