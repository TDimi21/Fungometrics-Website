<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Training\Result\Bullpen;

use App\Models\BullpenPracticeResult;
use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use App\Models\Practice;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BullpenIdempotencyTest extends TestCase
{
    private function payload(): array
    {
        $coach = User::factory()->create(['type' => 'coach']);
        Sanctum::actingAs($coach, ['coach']);
        $practice = Practice::factory()->create([
            'user_id' => $coach->id, 'type' => PracticeTypes::BULLPEN->value,
            'modes' => PracticeModes::HIT_OR_PITCH->value,
        ]);
        return [
            'practice_id' => $practice->id, 'pitcher_id' => $coach->id,
            'type_throw' => 'FB', 'pitch_side' => 'MC', 'miles_per_hour' => 80,
            'zone' => 'S', 'client_request_id' => (string) Str::uuid(),
        ];
    }

    public function test_response_loss_and_repeated_replay_return_the_original_pitch(): void
    {
        $payload = $this->payload();
        $id = $this->postJson('/api/result/bullpen', $payload)->assertCreated()->json('data.id');
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/result/bullpen', $payload)->assertOk()
                ->assertJsonPath('data.id', $id)
                ->assertJsonPath('client_request_id', $payload['client_request_id']);
        }
        $this->assertDatabaseCount('bullpen_practice_results', 1);
    }

    public function test_identical_real_pitches_with_different_keys_are_not_collapsed(): void
    {
        $payload = $this->payload();
        $first = $this->postJson('/api/result/bullpen', $payload)->assertCreated();
        $payload['client_request_id'] = (string) Str::uuid();
        $second = $this->postJson('/api/result/bullpen', $payload)->assertCreated();
        $this->assertNotSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, $second->json('data.sort'));
        $this->assertDatabaseCount('bullpen_practice_results', 2);
    }

    public function test_reusing_key_for_changed_payload_is_a_conflict(): void
    {
        $payload = $this->payload();
        $this->postJson('/api/result/bullpen', $payload)->assertCreated();
        $payload['miles_per_hour'] = 99;
        $this->postJson('/api/result/bullpen', $payload)->assertStatus(409);
        $this->assertDatabaseCount('bullpen_practice_results', 1);
        $this->assertDatabaseHas('bullpen_practice_results', ['miles_per_hour' => 80]);
    }

    public function test_key_cannot_be_reused_in_a_different_owned_session(): void
    {
        $payload = $this->payload();
        $this->postJson('/api/result/bullpen', $payload)->assertCreated();
        $practice = Practice::factory()->create([
            'user_id' => auth()->id(), 'type' => PracticeTypes::BULLPEN->value,
            'modes' => PracticeModes::HIT_OR_PITCH->value,
        ]);
        $payload['practice_id'] = $practice->id;
        $this->postJson('/api/result/bullpen', $payload)->assertStatus(409);
        $this->assertDatabaseCount('bullpen_practice_results', 1);
    }

    public function test_deleted_pitch_is_not_resurrected_by_replay(): void
    {
        $payload = $this->payload();
        $id = $this->postJson('/api/result/bullpen', $payload)->assertCreated()->json('data.id');
        BullpenPracticeResult::findOrFail($id)->delete();
        $this->postJson('/api/result/bullpen', $payload)->assertStatus(410);
        $this->assertSame(0, BullpenPracticeResult::count());
        $this->assertSame(1, BullpenPracticeResult::withTrashed()->count());
    }

    public function test_validation_failure_does_not_consume_identity(): void
    {
        $payload = $this->payload();
        $this->postJson('/api/result/bullpen', array_diff_key($payload, ['type_throw' => true]))->assertUnprocessable();
        $this->postJson('/api/result/bullpen', $payload)->assertCreated();
        $this->assertDatabaseCount('bullpen_practice_results', 1);
    }

    public function test_legacy_clients_can_still_record_without_an_identity(): void
    {
        $payload = $this->payload();
        unset($payload['client_request_id']);
        $this->postJson('/api/result/bullpen', $payload)->assertCreated();
        $this->postJson('/api/result/bullpen', $payload)->assertCreated();
        $this->assertDatabaseCount('bullpen_practice_results', 2);
    }

    public function test_capability_route_is_authenticated_and_not_matched_as_a_result_id(): void
    {
        $this->getJson('/api/result/bullpen/sync-capabilities')->assertUnauthorized();
        $this->payload();
        $this->getJson('/api/result/bullpen/sync-capabilities')->assertOk()->assertJsonPath('idempotency_version', 1);
    }

    public function test_zero_string_is_a_valid_deduplicated_identity(): void
    {
        $payload = $this->payload();
        $payload['client_request_id'] = '0';
        $this->postJson('/api/result/bullpen', $payload)->assertCreated();
        $this->postJson('/api/result/bullpen', $payload)->assertOk();
        $this->assertDatabaseCount('bullpen_practice_results', 1);
    }

    public function test_database_enforces_identity_uniqueness(): void
    {
        $payload = $this->payload();
        $id = $this->postJson('/api/result/bullpen', $payload)->assertCreated()->json('data.id');
        $copy = BullpenPracticeResult::findOrFail($id)->replicate();
        $this->expectException(\Illuminate\Database\QueryException::class);
        $copy->save();
    }
}
