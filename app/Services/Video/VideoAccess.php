<?php
namespace App\Services\Video;

use App\Models\{CoachTeam, PlayerTeam, PlayerVideo, User};

final class VideoAccess
{
    public function playerTeams(string $playerId)
    {
        return PlayerTeam::where('user_id', $playerId)->where('actual', true)->whereHas('team')->pluck('team_id');
    }
    public function coachTeams(string $coachId)
    {
        return CoachTeam::where('coach_id', $coachId)->whereHas('team')->pluck('team_id');
    }
    public function roster(User $coach, string $playerId, string $teamId): bool
    {
        return $coach->type === 'coach' && $this->coachTeams($coach->id)->contains($teamId)
            && $this->playerTeams($playerId)->contains($teamId)
            && User::whereKey($playerId)->where('type', 'player')->exists();
    }
    public function videos(User $viewer, string $playerId)
    {
        abort_unless(User::whereKey($playerId)->where('type', 'player')->exists(), 404);
        $query = PlayerVideo::where('player_id', $playerId);
        if ($viewer->type === 'player' && $viewer->id === $playerId) return $query;
        abort_unless($viewer->type === 'coach', 403);
        $teams = $this->playerTeams($playerId)->intersect($this->coachTeams($viewer->id));
        return $query->whereHas('shares', fn ($share) => $share->whereIn('team_id', $teams)
            ->where(fn ($target) => $target->whereNull('coach_id')->orWhere('coach_id', $viewer->id)));
    }
    public function view(User $viewer, PlayerVideo $video): void
    {
        abort_unless(User::whereKey($video->player_id)->where('type', 'player')->exists()
            && $this->videos($viewer, $video->player_id)->whereKey($video->id)->exists(), 404);
    }
}
