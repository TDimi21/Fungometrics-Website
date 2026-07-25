<?php

declare(strict_types=1);

namespace Tests\Feature\DataHub;

use App\Models\CoachTeam;
use App\Models\ExternalPlayerMapping;
use App\Models\PlayerTeam;
use App\Models\PlatformDefinition;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use App\Services\DataHub\Platforms\TrackMan\TrackManPlayerExtractor;
use App\Services\DataHub\Services\PlayerMatchingService;
use Database\Seeders\BaseballDictionarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class TrackManPlayerMappingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaseballDictionarySeeder::class);
    }

    public function test_extraction_merges_roles_by_trackman_id_but_keeps_conflicting_ids_separate(): void
    {
        $players = app(TrackManPlayerExtractor::class)->extract([
            ['batter' => 'Tom Smith', 'batter_id' => '123', 'batter_team' => 'North', 'pitcher' => 'Tom Smith', 'pitcher_id' => '123', 'pitcher_team' => 'North'],
            ['batter' => 'Tom Smith', 'batter_id' => '456', 'batter_team' => 'North'],
        ]);

        $this->assertCount(2, $players);
        $merged = collect($players)->firstWhere('external_player_id', '123');
        $this->assertSame(['batter', 'pitcher'], $merged['roles']);
        $this->assertSame(2, $merged['row_count']);
        $this->assertSame(1, $merged['batter_row_count']);
        $this->assertSame(1, $merged['pitcher_row_count']);
        $this->assertSame(['North'], $merged['source_team_names']);
    }

    public function test_name_normalization_supports_last_first_middle_suffix_and_nickname_is_review_only(): void
    {
        $matching = app(PlayerMatchingService::class);
        $this->assertSame('thomas dimitroff', $matching->normalize(' Dimitroff, Thomas A. Jr. '));
        $team = Team::factory()->create();
        $player = $this->player($team, 'Thomas', 'Dimitroff');
        $suggestion = $matching->suggestions((string) $team->id, 'Tom Dimitroff')[0];
        $this->assertSame((string) $player->id, $suggestion['player_id']);
        $this->assertSame('nickname', $suggestion['match_type']);
        $this->assertFalse($suggestion['auto_select']);
    }

    public function test_remembered_external_id_and_name_mappings_have_priority(): void
    {
        $team = Team::factory()->create();
        $coach = User::factory()->create(['type' => 'coach']);
        $external = $this->player($team, 'Thomas', 'Dimitroff');
        $nameOnly = $this->player($team, 'Robert', 'Jones');
        $platform = PlatformDefinition::query()->where('key', 'trackman')->firstOrFail();
        foreach ([['77', 'Tom Dimitroff', $external], [null, 'Bob Jones', $nameOnly]] as [$sourceId, $sourceName, $player]) {
            ExternalPlayerMapping::query()->create([
                'team_id' => $team->id, 'platform_definition_id' => $platform->id,
                'source_player_id' => $sourceId, 'source_player_name' => $sourceName,
                'normalized_source_player_name' => app(PlayerMatchingService::class)->normalize($sourceName),
                'fmtrx_player_id' => $player->id, 'approved_by' => $coach->id, 'approved_at' => now(),
            ]);
        }
        $matching = app(PlayerMatchingService::class);
        $this->assertSame('remembered_external_id', $matching->suggestions((string) $team->id, 'Different Name', '77', (string) $platform->id)[0]['match_type']);
        $this->assertSame('remembered_name', $matching->suggestions((string) $team->id, 'Bob Jones', null, (string) $platform->id)[0]['match_type']);
    }

    public function test_roster_and_approval_are_team_scoped_and_skip_is_not_remembered(): void
    {
        $team = Team::factory()->create();
        $other = Team::factory()->create();
        $coach = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id]);
        $target = $this->player($team, 'Thomas', 'Dimitroff');
        $this->player($other, 'Other', 'Player');
        Sanctum::actingAs($coach, ['coach']);

        $this->getJson('/api/data-hub/player-mappings/roster?team_id='.$team->id)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', (string) $target->id);
        $before = ['practices' => DB::table('practices')->count(), 'cage' => DB::table('cage_practice_results')->count()];
        $payload = ['team_id' => $team->id, 'platform' => 'trackman', 'confirmed_duplicate_targets' => [], 'mappings' => [
            ['source_key' => 'trackman:id:77', 'source_name' => 'Tom Dimitroff', 'external_player_id' => '77', 'roles' => ['batter'], 'fmtrx_player_id' => $target->id, 'skipped' => false],
            ['source_key' => 'trackman:id:88', 'source_name' => 'Missing Player', 'external_player_id' => '88', 'roles' => ['pitcher'], 'fmtrx_player_id' => null, 'skipped' => true],
        ]];
        $this->postJson('/api/data-hub/player-mappings/approve', $payload)
            ->assertOk()->assertJsonPath('data.skipped_source_keys.0', 'trackman:id:88');
        $this->assertDatabaseCount('external_player_mappings', 1);
        $this->assertSame($before['practices'], DB::table('practices')->count());
        $this->assertSame($before['cage'], DB::table('cage_practice_results')->count());
        $this->getJson('/api/data-hub/player-mappings/roster?team_id='.$other->id)->assertForbidden();
    }

    public function test_unresolved_and_unconfirmed_duplicate_targets_block_approval(): void
    {
        $team = Team::factory()->create();
        $coach = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id]);
        $target = $this->player($team, 'Thomas', 'Dimitroff');
        Sanctum::actingAs($coach, ['coach']);
        $base = ['team_id' => $team->id, 'platform' => 'trackman', 'confirmed_duplicate_targets' => []];
        $unresolved = [['source_key' => 'a', 'source_name' => 'A', 'external_player_id' => null, 'roles' => ['batter'], 'fmtrx_player_id' => null, 'skipped' => false]];
        $this->postJson('/api/data-hub/player-mappings/approve', $base + ['mappings' => $unresolved])->assertUnprocessable();
        $duplicates = [
            ['source_key' => 'a', 'source_name' => 'A', 'external_player_id' => '1', 'roles' => ['batter'], 'fmtrx_player_id' => $target->id, 'skipped' => false],
            ['source_key' => 'b', 'source_name' => 'B', 'external_player_id' => '2', 'roles' => ['pitcher'], 'fmtrx_player_id' => $target->id, 'skipped' => false],
        ];
        $this->postJson('/api/data-hub/player-mappings/approve', $base + ['mappings' => $duplicates])->assertUnprocessable();
        $this->postJson('/api/data-hub/player-mappings/approve', array_merge($base, ['mappings' => $duplicates, 'confirmed_duplicate_targets' => [$target->id]]))->assertOk();
    }

    private function player(Team $team, string $first, string $last): User
    {
        $user = User::factory()->create(['type' => 'player', 'status' => true]);
        Profile::factory()->create(['user_id' => $user->id, 'first_name' => $first, 'last_name' => $last]);
        PlayerTeam::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

        return $user;
    }
}
