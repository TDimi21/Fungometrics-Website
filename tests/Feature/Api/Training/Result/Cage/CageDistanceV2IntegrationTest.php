<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Training\Result\Cage;

use App\Models\CagePracticeResult;
use App\Models\Concerns\CagePositions;
use App\Models\Concerns\UserTypes;
use App\Models\Practice;
use App\Models\Team;
use App\Models\User;
use App\Services\Cage\CageDistanceService;
use Arr;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

/**
 * Covers the FMTRX Cage Distance Model v2 parallel-integration: storage
 * (migration), the feature flag, non-blocking failure handling, and that v1
 * (distance_travel) is never altered by any of it.
 */
class CageDistanceV2IntegrationTest extends TestCase
{
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'practice_id' => Practice::factory()->create(['type' => UserTypes::PLAYER->value])->id,
            'user_id' => User::factory()->create()->id,
            'team_id' => Team::factory()->create()->id,
            'launch_angle' => 25.0,
            'launch_angle_velocity' => 95.0,
            'spray_angle' => -5.0,
            'distance_travel' => 320,
            'cage_mark' => 100,
            'cage_position' => Arr::random(CagePositions::cases()),
            'ground_ball' => false,
        ], $overrides);
    }

    // ── Migration ────────────────────────────────────────────────────────────

    public function test_migration_adds_nullable_v2_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('cage_practice_results', [
            'distance_model_version',
            'distance_model_meta',
            'estimated_carry_v2',
            'estimated_carry_low_v2',
            'estimated_carry_high_v2',
            'distance_confidence_v2',
        ]));
    }

    public function test_new_row_has_null_v2_fields_by_default(): void
    {
        // Simulates a historical row: created directly, bypassing the
        // controller's v2 enrichment entirely.
        $result = CagePracticeResult::factory()->create([
            'practice_id' => Practice::factory()->create()->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $this->assertNull($result->distance_model_version);
        $this->assertNull($result->distance_model_meta);
        $this->assertNull($result->estimated_carry_v2);
        $this->assertNull($result->distance_confidence_v2);
    }

    // ── Flag disabled (default) ─────────────────────────────────────────────

    public function test_flag_disabled_by_default_v2_fields_stay_null(): void
    {
        config(['fmtrx.cage_distance_v2_enabled' => false]);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->json('POST', 'api/result/cage', $this->validPayload());
        $response->assertCreated();

        $result = CagePracticeResult::query()->findOrFail($response->json('data.id'));
        $this->assertNull($result->distance_model_version);
        $this->assertNull($result->estimated_carry_v2);
        $this->assertSame(320, (int) $result->distance_travel);
    }

    // ── Flag enabled ─────────────────────────────────────────────────────────

    public function test_flag_enabled_populates_v2_fields_without_changing_v1(): void
    {
        config(['fmtrx.cage_distance_v2_enabled' => true]);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->json('POST', 'api/result/cage', $this->validPayload());
        $response->assertCreated();

        $result = CagePracticeResult::query()->findOrFail($response->json('data.id'));

        // v1 unchanged.
        $this->assertSame(320, (int) $result->distance_travel);
        $this->assertSame(25.0, (float) $result->launch_angle);
        $this->assertSame(95.0, (float) $result->launch_angle_velocity);

        // v2 populated.
        $this->assertSame('cage_v2.0', $result->distance_model_version);
        $this->assertNotNull($result->estimated_carry_v2);
        $this->assertNotNull($result->estimated_carry_low_v2);
        $this->assertNotNull($result->estimated_carry_high_v2);
        $this->assertContains($result->distance_confidence_v2, ['high', 'medium', 'low']);

        $this->assertIsArray($result->distance_model_meta);
        $this->assertEquals(95.0, $result->distance_model_meta['exit_velocity_mph']);
        $this->assertEquals(25.0, $result->distance_model_meta['launch_angle_deg']);
        $this->assertSame('standardized', $result->distance_model_meta['mode']);
        $this->assertArrayHasKey('hang_time_seconds', $result->distance_model_meta);
        $this->assertArrayHasKey('assumptions', $result->distance_model_meta);
    }

    public function test_flag_enabled_ground_ball_still_populates_confidence_and_meta(): void
    {
        config(['fmtrx.cage_distance_v2_enabled' => true]);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->json('POST', 'api/result/cage', $this->validPayload([
            'launch_angle' => -5.0,
            'ground_ball' => true,
            'distance_travel' => 12,
        ]));
        $response->assertCreated();

        $result = CagePracticeResult::query()->findOrFail($response->json('data.id'));
        $this->assertSame(12, (int) $result->distance_travel);
        $this->assertSame('cage_v2.0', $result->distance_model_version);
        $this->assertSame('low', $result->distance_confidence_v2);
        // Ground balls have no estimated_carry_ft (only air-carry-to-contact,
        // which isn't one of the persisted v2 columns per the spec).
        $this->assertNull($result->estimated_carry_v2);
    }

    // ── Non-blocking failure ─────────────────────────────────────────────────

    public function test_service_failure_does_not_block_save(): void
    {
        config(['fmtrx.cage_distance_v2_enabled' => true]);
        Sanctum::actingAs(User::factory()->create());

        // Force CageDistanceService::estimate() to throw, with an otherwise
        // fully valid payload — isolates "v2 fails" from "v1 fails", since
        // launch_angle_velocity/launch_angle/spray_angle are NOT NULL at the
        // DB level even though CageRequest marks them nullable, so a null
        // input there would break the v1 save too and wouldn't prove
        // anything about v2's non-blocking behavior.
        $this->mock(CageDistanceService::class, function ($mock): void {
            $mock->shouldReceive('estimate')->andThrow(new RuntimeException('simulated v2 failure'));
        });

        $response = $this->json('POST', 'api/result/cage', $this->validPayload());

        $response->assertCreated();
        $result = CagePracticeResult::query()->findOrFail($response->json('data.id'));

        // v1 still saved successfully...
        $this->assertSame(320, (int) $result->distance_travel);
        $this->assertSame(25.0, (float) $result->launch_angle);
        // ...v2 silently stayed null rather than failing the request.
        $this->assertNull($result->distance_model_version);
        $this->assertNull($result->estimated_carry_v2);
    }
}
