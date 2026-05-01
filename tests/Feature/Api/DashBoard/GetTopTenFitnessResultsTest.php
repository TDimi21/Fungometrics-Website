<?php

declare(strict_types=1);

namespace Tests\Feature\Api\DashBoard;

use App\Models\Concerns\UserTypes;
use App\Models\Player;
use App\Models\PlayerFitness;
use App\Models\PlayerTeam;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetTopTenFitnessResultsTest extends TestCase
{
    public function test_get_dashboard_top_ten_weight_ok(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();


        $user1 = User::factory()->create([
            'type' => UserTypes::PLAYER->value
        ])->id;
        PlayerFitness::factory(12)->create([
            'user_id' => Profile::factory()->create([
                'user_id' => $user1
            ])->user_id
        ]);
        $user2 = User::factory()->create([
            'type' => UserTypes::PLAYER->value
        ])->id;
        PlayerFitness::factory(5)->create([
            'user_id' => Profile::factory()->create([
                'user_id' => $user2
            ])->user_id
        ]);

        $user3 = User::factory()->create([
            'type' => UserTypes::PLAYER->value
        ])->id;
        PlayerFitness::factory(18)->create([
            'user_id' => Profile::factory()->create([
                'user_id' => $user3
            ])->user_id
        ]);

        PlayerTeam::factory()->create([
            'user_id' => $user1,
            'team_id' => $team->id,
        ]);
        PlayerTeam::factory()->create([
            'user_id' => $user2,
            'team_id' => $team->id,
        ]);
        PlayerTeam::factory()->create([
            'user_id' => $user3,
            'team_id' => $team->id,
        ]);

        $response = $this->json('POST', 'api/table/'.$team->id, [
            'option'=>10,
            'range'=>0,
        ]);
        $response->assertOk()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data'
        ]);

    }
    public function test_get_dashboard_top_ten_weight_range_ok(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();
        $user1 = User::factory()->create([
            'type' => UserTypes::PLAYER->value
        ])->id;
        PlayerFitness::factory(12)->create([
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'user_id' => Profile::factory()->create([
                'user_id' => $user1
            ])->user_id
        ]);
        $user2 = User::factory()->create([
            'type' => UserTypes::PLAYER->value
        ])->id;
        PlayerFitness::factory(5)->create([
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'user_id' => Profile::factory()->create([
                'user_id' => $user2
            ])->user_id
        ]);

        $user3 = User::factory()->create([
            'type' => UserTypes::PLAYER->value
        ])->id;
        PlayerFitness::factory(18)->create([
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'user_id' => Profile::factory()->create([
                'user_id' => $user3
            ])->user_id
        ]);

        PlayerTeam::factory()->create([
            'user_id' => $user1,
            'team_id' => $team->id,
        ]);
        PlayerTeam::factory()->create([
            'user_id' => $user2,
            'team_id' => $team->id,
        ]);
        PlayerTeam::factory()->create([
            'user_id' => $user3,
            'team_id' => $team->id,
        ]);

        $response = $this->json('POST', 'api/table/'.$team->id, [
            'option'=>10,
            'range'=>6
        ]);
        $response->assertOk()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data'
        ]);

    }


    public function test_get_dashboard_top_ten_power_body_weight_ok(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();


        $user1 = User::factory()->create([
            'type' => UserTypes::PLAYER->value
        ])->id;
        PlayerFitness::factory(12)->create([
            'user_id' => Player::factory()->create([
                'user_id' => Profile::factory()->create([
                    'user_id' => $user1
                ])->user_id
            ])->user_id
        ]);
        $user2 = User::factory()->create([
            'type' => UserTypes::PLAYER->value
        ])->id;
        PlayerFitness::factory(5)->create([
            'user_id' => Player::factory()->create([
                'user_id' => Profile::factory()->create([
                    'user_id' => $user2
                ])->user_id
            ])->user_id
        ]);

        $user3 = User::factory()->create([
            'type' => UserTypes::PLAYER->value
        ])->id;
        PlayerFitness::factory(18)->create([
            'user_id' => Player::factory()->create([
                'user_id' => Profile::factory()->create([
                    'user_id' => $user3
                ])->user_id
            ])->user_id
        ]);

        PlayerTeam::factory()->create([
            'user_id' => $user1,
            'team_id' => $team->id,
        ]);
        PlayerTeam::factory()->create([
            'user_id' => $user2,
            'team_id' => $team->id,
        ]);
        PlayerTeam::factory()->create([
            'user_id' => $user3,
            'team_id' => $team->id,
        ]);

        $response = $this->json('POST', 'api/table/'.$team->id, [
            'option'=>11,
            'range'=>0
        ]);
        $response->assertOk()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data'
        ]);

    }
    public function test_get_dashboard_top_ten_power_body_weight_range_ok(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();


        $user1 = User::factory()->create([
            'type' => UserTypes::PLAYER->value
        ])->id;
        PlayerFitness::factory(12)->create([
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'fitness_date' => fake()->dateTimeBetween('-1 years'),
            'user_id' => Player::factory()->create([
                'user_id' => Profile::factory()->create([
                    'user_id' => $user1
                ])->user_id
            ])->user_id
        ]);
        $user2 = User::factory()->create([
            'type' => UserTypes::PLAYER->value
        ])->id;
        PlayerFitness::factory(5)->create([
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'fitness_date' => fake()->dateTimeBetween('-1 years'),
            'user_id' => Player::factory()->create([
                'user_id' => Profile::factory()->create([
                    'user_id' => $user2
                ])->user_id
            ])->user_id
        ]);

        $user3 = User::factory()->create([
            'type' => UserTypes::PLAYER->value
        ])->id;
        PlayerFitness::factory(18)->create([
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'fitness_date' => fake()->dateTimeBetween('-1 years'),
            'user_id' => Player::factory()->create([
                'user_id' => Profile::factory()->create([
                    'user_id' => $user3
                ])->user_id
            ])->user_id
        ]);

        PlayerTeam::factory()->create([
            'user_id' => $user1,
            'team_id' => $team->id,
        ]);
        PlayerTeam::factory()->create([
            'user_id' => $user2,
            'team_id' => $team->id,
        ]);
        PlayerTeam::factory()->create([
            'user_id' => $user3,
            'team_id' => $team->id,
        ]);

        $response = $this->json('POST', 'api/table/'.$team->id, [
            'option'=>11,
            'range'=>6
        ]);
        $response->assertOk()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data'
        ]);

    }


}
