<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Auth;

use App\Models\{CoachTeam, Player, PlayerTeam, Profile, Team, User};
use Tests\TestCase;

class CoachDefaultTeamLoginTest extends TestCase
{
    private function coach(): User
    {
        $coach = User::factory()->create(['type' => 'coach', 'password' => bcrypt('Password123')]);
        Profile::factory()->create(['user_id' => $coach->id]);
        return $coach;
    }

    private function team(User $coach, bool $main = false): Team
    {
        $team = Team::factory()->create();
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id, 'is_main' => $main]);
        return $team;
    }

    private function player(Team $team): User
    {
        $player = User::factory()->create(['type' => 'player']);
        Profile::factory()->create(['user_id' => $player->id]);
        Player::factory()->create(['user_id' => $player->id]);
        PlayerTeam::factory()->create(['user_id' => $player->id, 'team_id' => $team->id, 'actual' => true]);
        return $player;
    }

    private function login(User $coach)
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        return $this->withHeader('Authorization', '')->postJson('/api/login', ['email' => $coach->email, 'password' => 'Password123'])->assertOk();
    }

    public function test_web_saved_default_is_first_native_login_team_with_matching_roster(): void
    {
        $coach = $this->coach();
        $main = $this->team($coach, true);
        $default = $this->team($coach);
        $this->player($main);
        $selectedPlayer = $this->player($default);
        $token = $coach->createToken('test', ['coach'])->plainTextToken;
        // Same endpoint the web's Set as Default action uses. Viewing another
        // team afterward must not change the saved login default.
        $this->withToken($token)->patchJson('/api/coach/team-preferences', ['default_team_id' => $default->id])->assertOk();
        $this->withToken($token)->patchJson('/api/coach/team-preferences', ['active_team_id' => $main->id])->assertOk();
        $this->login($coach)->assertJsonPath('data.teams.0.id_team', $default->id)
            ->assertJsonPath('data.teams.1.id_team', $main->id)
            ->assertJsonCount(1, 'data.players')
            ->assertJsonPath('data.players.0.id', $selectedPlayer->id);
        $this->assertSame($main->id, $coach->fresh()->active_team_id);
        $this->assertTrue((bool) CoachTeam::where('coach_id', $coach->id)->where('team_id', $main->id)->first()->is_main);
    }

    /** @dataProvider unavailableDefaults */
    public function test_unavailable_default_falls_back_to_accessible_main_team(string $reason): void
    {
        $coach = $this->coach();
        $main = $this->team($coach, true);
        $default = $reason === 'foreign' ? Team::factory()->create() : $this->team($coach);
        $coach->forceFill(['default_team_id' => $default->id])->save();
        if ($reason === 'deleted_team') $default->delete();
        if ($reason === 'removed_membership') CoachTeam::where('coach_id', $coach->id)->where('team_id', $default->id)->first()->delete();
        $this->login($coach)->assertJsonPath('data.teams.0.id_team', $main->id)->assertJsonCount(1, 'data.teams');
    }

    public static function unavailableDefaults(): array
    {
        return [['foreign'], ['deleted_team'], ['removed_membership']];
    }

    public function test_no_default_uses_main_team_and_no_teams_returns_empty_lists(): void
    {
        $coach = $this->coach();
        $this->team($coach);
        $main = $this->team($coach, true);
        $this->login($coach)->assertJsonPath('data.teams.0.id_team', $main->id);
        CoachTeam::where('coach_id', $coach->id)->get()->each->delete();
        $this->login($coach)->assertJsonCount(0, 'data.teams')->assertJsonCount(0, 'data.players');
    }
}
