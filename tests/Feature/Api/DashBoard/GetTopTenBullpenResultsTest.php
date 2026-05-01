<?php

declare(strict_types=1);

namespace Tests\Feature\Api\DashBoard;

use App\Models\BullpenPracticeResult;
use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use App\Models\Concerns\UserTypes;
use App\Models\Practice;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetTopTenBullpenResultsTest extends TestCase
{
    public function test_get_dashboard_top_ten_bullpen_velocity_ok(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();

        BullpenPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BullpenPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BullpenPracticeResult::factory(15)->create([
            'team_id' => $team->id,
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BullpenPracticeResult::factory(15)->create([
            'team_id' => $team->id,
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        BullpenPracticeResult::factory(15)->create([
            'team_id' => $team->id,
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BullpenPracticeResult::factory(11)->create([
            'team_id' => $team->id,
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BullpenPracticeResult::factory(12)->create([
            'team_id' => $team->id,
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);


        $response = $this->json('POST', 'api/table/'.$team->id, [
            'option'=>4,
            'range'=>0
        ]);
        $response->assertOk()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data'
        ]);

    }
    public function test_get_dashboard_top_ten_bullpen_velocity_range_ok(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();

        BullpenPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BullpenPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BullpenPracticeResult::factory(15)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BullpenPracticeResult::factory(15)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        BullpenPracticeResult::factory(15)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BullpenPracticeResult::factory(11)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BullpenPracticeResult::factory(12)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);


        $response = $this->json('POST', 'api/table/'.$team->id, [
            'option'=>4,
            'range'=>3
        ]);
        $response->assertOk()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data'
        ]);

    }

    public function test_get_dashboard_top_ten_bullpen_velocity_average_ok(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();

        BullpenPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BullpenPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BullpenPracticeResult::factory(15)->create([
            'team_id' => $team->id,
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BullpenPracticeResult::factory(15)->create([
            'team_id' => $team->id,
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        BullpenPracticeResult::factory(15)->create([
            'team_id' => $team->id,
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BullpenPracticeResult::factory(11)->create([
            'team_id' => $team->id,
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BullpenPracticeResult::factory(12)->create([
            'team_id' => $team->id,
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);


        $response = $this->json('POST', 'api/table/'.$team->id, [
            'option'=>5,
            'range'=>0
        ]);
        $response->assertOk()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data'
        ]);

    }
    public function test_get_dashboard_top_ten_bullpen_velocity_average_range_ok(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();

        BullpenPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BullpenPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BullpenPracticeResult::factory(15)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BullpenPracticeResult::factory(15)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        BullpenPracticeResult::factory(15)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BullpenPracticeResult::factory(11)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BullpenPracticeResult::factory(12)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);


        $response = $this->json('POST', 'api/table/'.$team->id, [
            'option'=>5,
            'range'=>3
        ]);
        $response->assertOk()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data'
        ]);

    }

    public function test_get_dashboard_top_ten_batting_swings_ok(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();

        BullpenPracticeResult::factory(11)->create([
            'team_id' => $team->id,
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        BullpenPracticeResult::factory(11)->create([
            'team_id' => $team->id,
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        BullpenPracticeResult::factory(11)->create([
            'team_id' => $team->id,
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        BullpenPracticeResult::factory(11)->create([
            'team_id' => $team->id,
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        BullpenPracticeResult::factory(11)->create([
            'team_id' => $team->id,
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        BullpenPracticeResult::factory(11)->create([
            'team_id' => $team->id,
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        $response = $this->json('POST', 'api/table/'.$team->id, [
            'option'=>6,
            'range'=>0
        ]);
        $response->assertOk()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data'
        ]);

    }
    public function test_get_dashboard_top_ten_batting_swings_range_ok(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();

        BullpenPracticeResult::factory(11)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        BullpenPracticeResult::factory(13)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        BullpenPracticeResult::factory(9)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        BullpenPracticeResult::factory(7)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        BullpenPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        BullpenPracticeResult::factory(3)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'pitcher_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        $response = $this->json('POST', 'api/table/'.$team->id, [
            'option'=>6,
            'range'=>12
        ]);
        $response->assertOk()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data'
        ]);

    }


}
