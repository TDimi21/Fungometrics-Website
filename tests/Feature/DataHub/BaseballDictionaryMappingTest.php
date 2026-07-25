<?php

declare(strict_types=1);

namespace Tests\Feature\DataHub;

use App\Models\CoachTeam;
use App\Models\MappingTemplate;
use App\Models\PlatformDefinition;
use App\Models\Team;
use App\Models\User;
use App\Services\DataHub\Dictionary\BaseballDictionaryService;
use App\Services\DataHub\Dictionary\MappingResolutionService;
use App\Services\DataHub\Dictionary\TemplateFingerprintService;
use App\Services\DataHub\Dictionary\UnitConversionService;
use Database\Seeders\BaseballDictionarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class BaseballDictionaryMappingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaseballDictionarySeeder::class);
    }

    public function test_seed_contains_domains_unique_concepts_aliases_and_conversions(): void
    {
        $this->assertSame(14, DB::table('baseball_domains')->count());
        $this->assertSame(DB::table('baseball_concepts')->count(), DB::table('baseball_concepts')->distinct()->count('canonical_key'));
        $this->assertTrue(DB::table('baseball_concept_aliases')->where('alias', 'ExitSpeed')->where('is_official', true)->exists());
        $this->assertTrue(DB::table('unit_conversions')->where('transformation_key', 'kph_to_mph')->exists());
    }

    public function test_official_alias_resolves_but_unknown_column_does_not_auto_select(): void
    {
        $platform = PlatformDefinition::query()->where('key', 'trackman')->firstOrFail();
        $resolved = app(MappingResolutionService::class)->resolve('unused-team', $platform->id, str_repeat('a', 64), ['ExitSpeed', 'MysteryMetric']);

        $this->assertSame('official_platform_alias', $resolved[0]['resolution_source']);
        $this->assertTrue($resolved[0]['trusted']);
        $this->assertNull($resolved[1]['concept_id']);
        $this->assertFalse($resolved[1]['trusted']);
    }

    public function test_units_defaults_ranges_and_fingerprint_are_deterministic(): void
    {
        $units = app(UnitConversionService::class);
        $this->assertEqualsWithDelta(62.1371, $units->convert(100, 'kph', 'mph'), 0.001);
        $this->assertSame('imperial', $units->defaultSystem('USA'));
        $this->assertSame('metric', $units->defaultSystem('CA'));
        $concept = app(BaseballDictionaryService::class)->catalog()['concepts']->firstWhere('canonical_key', 'hitting.exit_velocity');
        $this->assertFalse(app(BaseballDictionaryService::class)->validate($concept, 200)['valid']);
        $fingerprints = app(TemplateFingerprintService::class);
        $this->assertSame($fingerprints->fingerprint([' Exit Speed ', 'ANGLE']), $fingerprints->fingerprint(['exitspeed', 'angle']));
    }

    public function test_mapping_approval_is_authorized_team_scoped_versioned_and_writes_no_session_tables(): void
    {
        $team = Team::factory()->create();
        $other = Team::factory()->create();
        $coach = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id]);
        Sanctum::actingAs($coach, ['coach']);
        $platform = PlatformDefinition::query()->where('key', 'trackman')->firstOrFail();
        $concept = DB::table('baseball_concepts')->where('canonical_key', 'hitting.exit_velocity')->first();
        $fingerprint = app(TemplateFingerprintService::class)->fingerprint(['ExitSpeed']);
        $payload = [
            'team_id' => $team->id, 'platform' => 'trackman', 'template_fingerprint' => $fingerprint,
            'headers' => ['ExitSpeed'], 'remember' => true, 'entries' => [[
                'source_column_name' => 'ExitSpeed', 'normalized_source_column' => 'exitspeed',
                'baseball_concept_id' => $concept->id, 'source_unit_id' => null, 'canonical_unit_id' => null,
                'transformation_key' => null, 'resolution_source' => 'official_platform_alias',
                'confidence' => 100, 'required_type' => null, 'action' => 'map', 'metadata' => null,
            ]],
        ];
        $before = ['practices' => DB::table('practices')->count(), 'cage' => DB::table('cage_practice_results')->count()];
        $this->postJson('/api/data-hub/mappings/approve', $payload)->assertOk()->assertJsonPath('data.version', 1);
        $this->postJson('/api/data-hub/mappings/approve', $payload)->assertOk()->assertJsonPath('data.version', 2);
        $this->assertSame(2, MappingTemplate::query()->firstOrFail()->versions()->count());
        $this->assertSame($before['practices'], DB::table('practices')->count());
        $this->assertSame($before['cage'], DB::table('cage_practice_results')->count());
        $this->postJson('/api/data-hub/mappings/resolve', ['team_id' => $other->id, 'platform' => 'trackman', 'headers' => ['ExitSpeed']])->assertForbidden();
    }

    public function test_not_importing_column_is_allowed_and_unknowns_and_submissions_are_scoped(): void
    {
        $team = Team::factory()->create();
        $coach = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id]);
        Sanctum::actingAs($coach, ['coach']);
        $fingerprint = app(TemplateFingerprintService::class)->fingerprint(['Batter']);
        $entry = ['source_column_name' => 'Batter', 'normalized_source_column' => 'batter', 'baseball_concept_id' => null, 'source_unit_id' => null, 'canonical_unit_id' => null, 'transformation_key' => null, 'resolution_source' => 'manual', 'confidence' => 0, 'required_type' => 'player_identity', 'action' => 'ignore', 'metadata' => ['sample_values' => ['A Player']]];
        $this->postJson('/api/data-hub/mappings/approve', ['team_id' => $team->id, 'platform' => 'trackman', 'template_fingerprint' => $fingerprint, 'headers' => ['Batter'], 'entries' => [$entry], 'remember' => true])->assertOk();
        $this->postJson('/api/data-hub/concept-submissions', ['team_id' => $team->id, 'source_column_name' => 'Mystery', 'proposed_display_name' => 'Mystery Metric'])->assertCreated();
        $this->assertDatabaseHas('concept_submissions', ['team_id' => $team->id, 'status' => 'pending']);
    }
}
