<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Practice extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    public function scopeVisibleSharedTo($query, $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('is_player_shared', false)->orWhere('user_id', $userId)
                ->orWhereIn('team_id', CoachTeam::where('coach_id', $userId)->select('team_id'));
        });
    }

    protected $fillable = ['is_completed','team_id','user_id', 'started', 'finished', 'note', 'end_note', 'type', 'modes', 'is_scripted', 'script_plan', 'game_field', 'liveab_config', 'liveab_state', 'is_player_shared'];

    protected $casts = ['id' => 'string', 'is_completed' => 'boolean', 'is_scripted' => 'boolean', 'script_plan' => 'array', 'game_field' => 'array', 'liveab_config' => 'array', 'liveab_state' => 'array', 'is_player_shared' => 'boolean'];

    public function lineup(): HasMany
    {
        return $this->hasMany(PracticeLineUp::class);
    }
    public function team(): HasOne
    {
        return $this->hasOne(Team::class, 'id', 'team_id');
    }

    public function longToss(): HasMany
    {
        return $this->hasMany(LongTossPractice::class);
    }

    public function liveABTeams(): HasMany
    {
        return $this->hasMany(TeamsLiveAB::class);
    }

    public function cageMeta(): HasOne
    {
        return $this->hasOne(CagePracticeMeta::class);
    }

    public function batting(): HasMany
    {
        return $this->hasMany(BattingPracticeResult::class);
    }

    public function bullpen(): HasMany
    {
        return $this->hasMany(BullpenPracticeResult::class);
    }

    public function cage(): HasMany
    {
        return $this->hasMany(CagePracticeResult::class);
    }

    public function live(): HasMany
    {
        return $this->hasMany(LiveABPracticeResult::class);
    }

    public function exitVelocity(): HasMany
    {
        return $this->hasMany(ExitVelocityPractice::class);
    }

    public function weightBall(): HasMany
    {
        return $this->hasMany(WeightBallPractice::class);
    }

    public function scriptedBpSwings(): HasMany
    {
        return $this->hasMany(ScriptedBpSwing::class);
    }
}
