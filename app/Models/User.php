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
        'subscription_plan',
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
        'subscription_plan' => 'string',
    ];

    private const PLAN_FEATURES = [
        'free' => [
            'create_session',
            'record_pitches',
        ],
        'coach_basic' => [
            'create_session',
            'record_pitches',
            'view_session_report',
            'view_team_stats',
            'manage_team',
        ],
        'coach_pro' => [
            'create_session',
            'record_pitches',
            'view_session_report',
            'view_team_stats',
            'manage_team',
            'view_player_cards',
            'view_advanced_stats',
            'export_stats',
            'performance_overview',
            'sms_results',
        ],
        'player_basic' => [
            'view_session_report',
            'view_own_stats',
        ],
        'player_pro' => [
            'view_session_report',
            'view_own_stats',
            'view_advanced_stats',
            'export_stats',
        ],
    ];

    public function planHasFeature(string $feature): bool
    {
        $plan = $this->subscription_plan ?? 'free';
        $allowed = self::PLAN_FEATURES[$plan] ?? self::PLAN_FEATURES['free'];

        return in_array($feature, $allowed, true);
    }

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
