<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Player;

use App\Models\Concerns\UserTypes;
use App\Models\CoachTeam;
use App\Models\PlayerTeam;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use App\Models\PlayerFitness;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SaveFitnessTest extends TestCase
{
    public function test_save_fitness_ok(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user);

        $player = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);
        $team = Team::factory()->create();
        CoachTeam::factory()->create(['coach_id' => $user->id, 'team_id' => $team->id]);
        PlayerTeam::factory()->create(['user_id' => $player->user_id, 'team_id' => $team->id]);

        $data = [
            'user_id' => $player->user_id,
            'fitness_date' => fake()->date,
            'bench_press' => fake()->numberBetween('10', 50),
            'front_squat' => fake()->numberBetween(10, 50),
            'back_squat' => fake()->numberBetween(10, 50),
            'power_clean' => fake()->numberBetween(30, 90),
            'dead_lift' => fake()->numberBetween(50, 100),
            'yd_40_dash' => fake()->numerify('##.##'),
            'yd_60_dash' => fake()->numerify('##.##'),
        ];

        $response = $this->json('POST', 'api/player/fitness', $data);
        $response->assertOk()->assertJsonStructure([
            'code',
            'status',
            'message',
            'data'
        ]);
    }

    public function test_strength_v1_raw_facts_and_protocols_are_preserved(): void
    {
        $coach = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($coach);
        $player = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id,
        ]);
        $team = Team::factory()->create();
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id]);
        PlayerTeam::factory()->create(['user_id' => $player->user_id, 'team_id' => $team->id]);

        $this->postJson('api/player/fitness', [
            'user_id' => $player->user_id,
            'fitness_date' => '2026-08-05',
            'body_weight' => 180,
            'dead_lift' => 275,
            'trap_bar_deadlift' => 315,
            'grip_strength_left' => 82,
            'grip_strength_right' => 90,
            'plank_hold' => 120,
            'strength_score' => 99,
            'strength_test_metadata' => [
                'metrics' => ['deadlift' => ['repetitions' => 5, 'method' => 'rep_max']],
                'protocols' => ['plank_hold' => 'front plank', 'grip_device' => 'dynamometer'],
            ],
        ])->assertOk();

        $fitness = PlayerFitness::query()->where('user_id', $player->user_id)->firstOrFail();
        $this->assertSame(275, $fitness->dead_lift);
        $this->assertSame(315.0, $fitness->trap_bar_deadlift);
        $this->assertSame(82.0, $fitness->grip_strength_left);
        $this->assertSame(90.0, $fitness->grip_strength_right);
        $this->assertSame(120.0, $fitness->plank_hold);
        $this->assertSame(5, $fitness->strength_test_metadata['metrics']['deadlift']['repetitions']);
        $this->assertNull($fitness->strength_score, 'A client-provided score must not override an ineligible governed result.');
    }

    public function test_save_fitness_not_authorized(): void
    {
        $player = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $data = [
            'user_id' => $player->user_id,
            'bench_press' => fake()->numberBetween('10', 50),
            'front_squat' => fake()->numberBetween(10, 50),
            'back_squat' => fake()->numberBetween(10, 50),
            'power_clean' => fake()->numberBetween(30, 90),
            'dead_lift' => fake()->numberBetween(50, 100),
            'yd_40_dash' => fake()->numerify('##.##'),
            'yd_60_dash' => fake()->numerify('##.##'),
        ];

        $response = $this->json('POST', 'api/player/fitness', $data);
        $response->assertUnauthorized()->assertJsonStructure([
            'code',
            'status',
            'message',
            'data'
        ]);
    }
    public function test_save_fitness_validations(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user);
        $player = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $data = [

            'bench_press' => fake()->numberBetween('10', 50),
            'front_squat' => fake()->numberBetween(10, 50),
            'back_squat' => fake()->numberBetween(10, 50),
            'power_clean' => fake()->numberBetween(30, 90),
            'dead_lift' => fake()->numberBetween(50, 100),
            'yd_40_dash' => fake()->numerify('##.##'),
            'yd_60_dash' => fake()->numerify('##.##'),
        ];

        $response = $this->json('POST', 'api/player/fitness', $data);
        $response->assertUnprocessable()->assertJsonStructure([
            'code',
            'status',
            'message',
            'data'
        ]);
    }

    public function test_save_fitness_error(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user);
        $player = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $data = [
            'user_id' => fake()->uuid,
            'fitness_date' => fake()->date,
            'bench_press' => fake()->numberBetween('10', 50),
            'front_squat' => fake()->numberBetween(10, 50),
            'back_squat' => fake()->numberBetween(10, 50),
            'power_clean' => fake()->numberBetween(30, 90),
            'dead_lift' => fake()->numberBetween(50, 100),
            'yd_40_dash' => fake()->numerify('##.##'),
            'yd_60_dash' => fake()->numerify('##.##'),
        ];

        $response = $this->json('POST', 'api/player/fitness', $data);
        $response->assertForbidden()->assertJsonStructure([
            'code',
            'status',
            'message',
            'data'
        ]);
    }
}
