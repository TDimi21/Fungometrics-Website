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
        // ── Free Coach ────────────────────────────────────────────────────────
        'free' => [
            'create_session',
            'record_pitches',
            'view_session_history',
            'roster_view',
            'invite_players',
            'notifications',
            'recent_sessions',
        ],

        // ── Coach Basic (legacy — same as free) ───────────────────────────────
        'coach_basic' => [
            'create_session',
            'record_pitches',
            'view_session_history',
            'roster_view',
            'invite_players',
            'notifications',
            'recent_sessions',
        ],

        // ── Coach Pro ─────────────────────────────────────────────────────────
        'coach_pro' => [
            'create_session',
            'record_pitches',
            'view_session_history',
            'roster_view',
            'invite_players',
            'notifications',
            'recent_sessions',
            'liveab_sessions',
            'exit_velocity_sessions',
            'long_toss_sessions',
            'weighted_ball_sessions',
            'practice_sessions',
            'view_team_stats',
            'view_advanced_stats',
            'performance_overview',
            'heat_maps',
            'export_stats',
            'ai_analytics',
            'view_session_report',
            'liveab_analytics',
            'box_score',
            'team_recaps',
            'player_recaps',
            'add_coaches',
            'team_switching',
            'edit_team',
            'edit_player',
            'add_team',
            'manage_multiple_teams',
            'sms_results',
            'view_player_cards',
            'unlimited_players',
        ],

        // ── Player Basic ($2.99) ──────────────────────────────────────────────
        'player_basic' => [
            'view_own_profile',
            'view_own_sessions',
            'personal_stats',
            'notifications',
            'recent_sessions',
        ],

        // ── Player Pro / Premium ($6.99) ──────────────────────────────────────
        'player_pro' => [
            'view_own_profile',
            'view_own_sessions',
            'personal_stats',
            'notifications',
            'recent_sessions',
            'liveab_sessions',
            'exit_velocity_sessions',
            'long_toss_sessions',
            'weighted_ball_sessions',
            'view_own_stats',
            'view_advanced_stats',
            'heat_maps',
            'development_graphs',
            'ai_recommendations',
            'view_session_report',
            'export_stats',
            'box_score',
            'player_recaps',
            'shareable_profile',
            'recruiting_profile',
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
        return $this->hasOne(PlayerFitness::class)->latestOfMany('fitness_date');
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
