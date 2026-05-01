<?php

declare(strict_types=1);

namespace Tests\Feature\Api\DashBoard;

use App\Models\BattingPracticeResult;
use App\Models\CagePracticeResult;
use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use App\Models\Concerns\UserTypes;
use App\Models\ExitVelocityPractice;
use App\Models\Practice;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetTopTenBattingResultsTest extends TestCase
{
    public function test_get_dashboard_top_ten_batting_velocity_ok(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();

        BattingPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BattingPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BattingPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        BattingPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BattingPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BattingPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        ExitVelocityPractice::factory(6)->create([
            'team_id' => $team->id,
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::TRAINING->value,
                'modes' => PracticeModes::EXIT_VELOCITY->value])
        ]);
        ExitVelocityPractice::factory(7)->create([
            'team_id' => $team->id,
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::TRAINING->value,
                'modes' => PracticeModes::EXIT_VELOCITY->value])
        ]);
        ExitVelocityPractice::factory(5)->create([
            'team_id' => $team->id,
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::TRAINING->value,
                'modes' => PracticeModes::EXIT_VELOCITY->value])
        ]);

        CagePracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::CAGE->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        CagePracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::CAGE->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        CagePracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::CAGE->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        $response = $this->json('POST', 'api/table/'.$team->id, [
            'option'=>1,
            'range'=>0 //0,12,6,3
        ]);
        $response->assertOk()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data'
        ]);

    }


    public function test_get_dashboard_top_ten_batting_velocity_range_ok(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();

        BattingPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BattingPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BattingPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        BattingPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BattingPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BattingPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        ExitVelocityPractice::factory(6)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::TRAINING->value,
                'modes' => PracticeModes::EXIT_VELOCITY->value])
        ]);
        ExitVelocityPractice::factory(7)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::TRAINING->value,
                'modes' => PracticeModes::EXIT_VELOCITY->value])
        ]);
        ExitVelocityPractice::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::TRAINING->value,
                'modes' => PracticeModes::EXIT_VELOCITY->value])
        ]);

        CagePracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::CAGE->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        CagePracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::CAGE->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        CagePracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::CAGE->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        $response = $this->json('POST', 'api/table/'.$team->id, [
            'option'=>1,
            'range'=>3 //0,12,6,3
        ]);
        $response->assertOk()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data'
        ]);

    }
    public function test_get_dashboard_top_ten_batting_velocity_average_ok(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();

        BattingPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BattingPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BattingPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        BattingPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BattingPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BattingPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        ExitVelocityPractice::factory(6)->create([
            'team_id' => $team->id,
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::TRAINING->value,
                'modes' => PracticeModes::EXIT_VELOCITY->value])
        ]);
        ExitVelocityPractice::factory(7)->create([
            'team_id' => $team->id,
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::TRAINING->value,
                'modes' => PracticeModes::EXIT_VELOCITY->value])
        ]);
        ExitVelocityPractice::factory(5)->create([
            'team_id' => $team->id,
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::TRAINING->value,
                'modes' => PracticeModes::EXIT_VELOCITY->value])
        ]);

        CagePracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::CAGE->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        CagePracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::CAGE->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        CagePracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::CAGE->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        $response = $this->json('POST', 'api/table/'.$team->id, [
            'option'=>2,
            'range'=>0 //0,12,6,3
        ]);
        $response->assertOk()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data'
        ]);

    }

    public function test_get_dashboard_top_ten_batting_velocity_average_range_ok(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();

        BattingPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BattingPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BattingPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        BattingPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BattingPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BattingPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        ExitVelocityPractice::factory(6)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::TRAINING->value,
                'modes' => PracticeModes::EXIT_VELOCITY->value])
        ]);
        ExitVelocityPractice::factory(7)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::TRAINING->value,
                'modes' => PracticeModes::EXIT_VELOCITY->value])
        ]);
        ExitVelocityPractice::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::TRAINING->value,
                'modes' => PracticeModes::EXIT_VELOCITY->value])
        ]);

        CagePracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::CAGE->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        CagePracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::CAGE->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        CagePracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::CAGE->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        $response = $this->json('POST', 'api/table/'.$team->id, [
            'option'=>2,
            'range'=>6//0,12,6,3
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

        BattingPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BattingPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BattingPracticeResult::factory(15)->create([
            'team_id' => $team->id,
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BattingPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        BattingPracticeResult::factory(15)->create([
            'team_id' => $team->id,
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BattingPracticeResult::factory(15)->create([
            'team_id' => $team->id,
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BattingPracticeResult::factory(15)->create([
            'team_id' => $team->id,
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BattingPracticeResult::factory(15)->create([
            'team_id' => $team->id,
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        ExitVelocityPractice::factory(16)->create([
            'team_id' => $team->id,
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::TRAINING->value,
                'modes' => PracticeModes::EXIT_VELOCITY->value])
        ]);
        ExitVelocityPractice::factory(17)->create([
            'team_id' => $team->id,
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::TRAINING->value,
                'modes' => PracticeModes::EXIT_VELOCITY->value])
        ]);
        ExitVelocityPractice::factory(15)->create([
            'team_id' => $team->id,
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::TRAINING->value,
                'modes' => PracticeModes::EXIT_VELOCITY->value])
        ]);
        ExitVelocityPractice::factory(12)->create([
            'team_id' => $team->id,
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::TRAINING->value,
                'modes' => PracticeModes::EXIT_VELOCITY->value])
        ]);

        CagePracticeResult::factory(9)->create([
            'team_id' => $team->id,
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::CAGE->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        CagePracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::CAGE->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        CagePracticeResult::factory(4)->create([
            'team_id' => $team->id,
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::CAGE->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        CagePracticeResult::factory(15)->create([
            'team_id' => $team->id,
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::CAGE->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        $response = $this->json('POST', 'api/table/'.$team->id, [
            'option'=>3,
            'range'=>0 //0,12,6,3

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

        BattingPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BattingPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BattingPracticeResult::factory(15)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BattingPracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => false,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        BattingPracticeResult::factory(15)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BattingPracticeResult::factory(15)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BattingPracticeResult::factory(15)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        BattingPracticeResult::factory(15)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'batter_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'is_in_match' => true,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::BATTING->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        ExitVelocityPractice::factory(16)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::TRAINING->value,
                'modes' => PracticeModes::EXIT_VELOCITY->value])
        ]);
        ExitVelocityPractice::factory(17)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::TRAINING->value,
                'modes' => PracticeModes::EXIT_VELOCITY->value])
        ]);
        ExitVelocityPractice::factory(15)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::TRAINING->value,
                'modes' => PracticeModes::EXIT_VELOCITY->value])
        ]);
        ExitVelocityPractice::factory(12)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::TRAINING->value,
                'modes' => PracticeModes::EXIT_VELOCITY->value])
        ]);

        CagePracticeResult::factory(9)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::CAGE->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        CagePracticeResult::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::CAGE->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        CagePracticeResult::factory(4)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::CAGE->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);
        CagePracticeResult::factory(15)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::CAGE->value,
                'modes' => PracticeModes::HIT_OR_PITCH->value])
        ]);

        $response = $this->json('POST', 'api/table/'.$team->id, [
            'option'=>3,
            'range'=>6 //0,12,6,3

        ]);
        $response->assertOk()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data'
        ]);

    }


}
