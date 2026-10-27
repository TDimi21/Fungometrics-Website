<?php
namespace Tests\Feature\Video;

use App\Models\{CoachTeam, PlayerTeam, PlayerVideo, Team, User};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlayerVideoTest extends TestCase
{
    private function actor(string $role): User { return User::factory()->create(['type' => $role]); }
    private function login(User $user): void { Sanctum::actingAs($user, [$user->type]); }
    private function upload(User $player, array $extra = []) {
        return $this->postJson('/api/player-videos', array_merge(['player_id' => $player->id, 'title' => 'Mechanics', 'category' => 'pitching',
            'video' => UploadedFile::fake()->create('clip.mp4', 16, 'video/mp4')], $extra));
    }
    public function test_selected_sharing_and_revocation_control_metadata_and_bytes(): void
    {
        Storage::fake('player_videos');
        $player = $this->actor('player'); $coach = $this->actor('coach'); $other = $this->actor('coach');
        $team = Team::factory()->create();
        foreach ([$player, $coach, $other] as $user) $this->grantTeamAccess($user, $team);
        $this->login($player);
        $id = $this->upload($player)->assertCreated()->json('data.id');
        $private = $this->upload($player)->assertCreated()->json('data.id');
        $this->assertStringNotContainsString('path', $this->getJson('/api/player-videos?player_id='.$player->id)->getContent());
        $this->login($coach);
        $this->getJson('/api/player-videos?player_id='.$player->id)->assertJsonCount(0, 'data');
        $this->getJson("/api/player-videos/$id/content")->assertNotFound();
        $this->login($player);
        $share = $this->postJson("/api/player-videos/$id/shares", ['team_id' => $team->id, 'coach_id' => $coach->id])->assertOk()->json('data.shares.0.id');
        $this->login($coach);
        $this->getJson('/api/player-videos?player_id='.$player->id)->assertJsonCount(1, 'data');
        $this->getJson("/api/player-videos/$id/content")->assertOk();
        $this->getJson("/api/player-videos/$private/content")->assertNotFound();
        $this->deleteJson("/api/player-videos/$id")->assertForbidden();
        $this->postJson("/api/player-videos/$id/shares", ['team_id' => $team->id])->assertForbidden();
        $this->login($other); $this->getJson("/api/player-videos/$id/content")->assertNotFound();
        $this->login($player); $this->deleteJson("/api/player-videos/$id/shares/$share")->assertNoContent();
        $this->login($coach); $this->getJson("/api/player-videos/$id/content")->assertNotFound();
        $this->login($player); $this->postJson("/api/player-videos/$id/shares", ['team_id' => $team->id])->assertOk();
        $this->login($other); $this->getJson("/api/player-videos/$id/content")->assertOk();
        PlayerTeam::where('user_id', $player->id)->delete();
        $this->getJson("/api/player-videos/$id/content")->assertNotFound();
        $this->login($player); $this->deleteJson("/api/player-videos/$id")->assertNoContent();
        $this->assertDatabaseMissing('player_videos', ['id' => $id]);
    }
    public function test_coach_upload_is_attached_to_roster_player_and_player_controls_access(): void
    {
        Storage::fake('player_videos');
        $player = $this->actor('player'); $coach = $this->actor('coach'); $other = $this->actor('player');
        $team = Team::factory()->create();
        $this->grantTeamAccess($player, $team); $this->grantTeamAccess($coach, $team);
        $this->login($coach);
        $this->upload($other, ['team_id' => $team->id])->assertForbidden();
        $id = $this->upload($player, ['team_id' => $team->id])->assertCreated()->json('data.id');
        $this->getJson("/api/player-videos/$id/content")->assertOk();
        $this->login($player);
        $share = $this->getJson('/api/player-videos?player_id='.$player->id)->assertJsonCount(1, 'data')->json('data.0.shares.0.id');
        $this->deleteJson("/api/player-videos/$id/shares/$share")->assertNoContent();
        $this->login($coach); $this->getJson("/api/player-videos/$id/content")->assertNotFound();
        $this->login($other); $this->getJson("/api/player-videos/$id/content")->assertForbidden();
    }
    public function test_upload_validation_and_context(): void
    {
        Storage::fake('player_videos'); $player = $this->actor('player'); $this->login($player);
        $this->getJson('/api/player-videos/context')->assertOk()->assertJsonPath('role', 'player');
        $this->upload($player, ['video' => UploadedFile::fake()->create('bad.html', 1, 'text/html')])->assertUnprocessable();
        $this->upload($player, ['video' => UploadedFile::fake()->create('big.mp4', 102401, 'video/mp4')])->assertUnprocessable();
        $other = $this->actor('player'); $this->upload($other)->assertForbidden();
        $this->assertCount(0, Storage::disk('player_videos')->allFiles());
    }
    public function test_authenticated_range_playback_and_coach_membership_loss(): void
    {
        Storage::fake('player_videos');
        $player = $this->actor('player'); $coach = $this->actor('coach'); $team = Team::factory()->create();
        $this->grantTeamAccess($player, $team); $this->grantTeamAccess($coach, $team);
        $this->login($player); $id = $this->upload($player)->assertCreated()->json('data.id');
        $this->postJson("/api/player-videos/$id/shares", ['team_id' => $team->id])->assertOk();
        $this->login($coach);
        // UploadedFile::fake reports a size but writes an empty file; supply bytes for the range test.
        Storage::disk('player_videos')->put(PlayerVideo::findOrFail($id)->path, '0123456789');
        $this->withHeader('Range', 'bytes=0-3')->get("/api/player-videos/$id/content")->assertStatus(206)->assertHeader('Content-Length', '4');
        CoachTeam::where('coach_id', $coach->id)->delete();
        $this->getJson('/api/player-videos?player_id='.$player->id)->assertJsonCount(0, 'data');
        $this->getJson("/api/player-videos/$id/content")->assertNotFound();
    }
    public function test_library_quota_and_account_deletion_remove_private_files(): void
    {
        // AccountDeletionTest covers throttling; isolate this storage cleanup check from shared rate counters.
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        Storage::fake('player_videos');
        $player = User::factory()->create(['type' => 'player', 'password' => \Illuminate\Support\Facades\Hash::make('CorrectPassword1!')]);
        $this->login($player); $id = $this->upload($player)->assertCreated()->json('data.id');
        $video = PlayerVideo::findOrFail($id); $path = $video->path;
        $video->update(['bytes' => 2 * 1024 * 1024 * 1024]);
        $this->upload($player)->assertUnprocessable();
        $this->assertCount(1, Storage::disk('player_videos')->allFiles());
        $auth = $this->postJson('/api/me/account-deletion/authorize', ['password' => 'CorrectPassword1!'])->assertOk();
        $this->deleteJson('/api/me/account', ['confirmation' => 'DELETE', 'confirmation_token' => $auth->json('data.confirmation_token')])->assertOk();
        Storage::disk('player_videos')->assertMissing($path);
        $this->assertDatabaseMissing('player_videos', ['id' => $id]);
    }

}
