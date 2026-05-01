<?php

declare(strict_types=1);

namespace Tests\Feature\Api\DashBoard;

use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use App\Models\Concerns\UserTypes;
use App\Models\LongTossPractice;
use App\Models\Practice;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetTopTenLongTossResultsTest extends TestCase
{
    public function test_get_dashboard_top_ten_long_toss_ok(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();

        LongTossPractice::factory(5)->create([
            'team_id' => $team->id,
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::TRAINING->value,
                'modes' => PracticeModes::LONG_TOSS->value])
        ]);
        LongTossPractice::factory(5)->create([
            'team_id' => $team->id,
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::TRAINING->value,
                'modes' => PracticeModes::LONG_TOSS->value])
        ]);
        LongTossPractice::factory(5)->create([
            'team_id' => $team->id,
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::TRAINING->value,
                'modes' => PracticeModes::LONG_TOSS->value])
        ]);

        LongTossPractice::factory(5)->create([
            'team_id' => $team->id,
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::TRAINING->value,
                'modes' => PracticeModes::LONG_TOSS->value])
        ]);
        LongTossPractice::factory(5)->create([
            'team_id' => $team->id,
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::TRAINING->value,
                'modes' => PracticeModes::LONG_TOSS->value])
        ]);
        LongTossPractice::factory(5)->create([
            'team_id' => $team->id,
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::TRAINING->value,
                'modes' => PracticeModes::LONG_TOSS->value])
        ]);
        LongTossPractice::factory(5)->create([
            'team_id' => $team->id,
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::TRAINING->value,
                'modes' => PracticeModes::LONG_TOSS->value])
        ]);
        $response = $this->json('POST', 'api/table/'.$team->id, [
            'option'=>8,
            'range'=>0
        ]);
        $response->assertOk()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data'
        ]);

    }
    public function test_get_dashboard_top_ten_long_toss_range_ok(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();

        LongTossPractice::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::TRAINING->value,
                'modes' => PracticeModes::LONG_TOSS->value])
        ]);
        LongTossPractice::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::TRAINING->value,
                'modes' => PracticeModes::LONG_TOSS->value])
        ]);
        LongTossPractice::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::TRAINING->value,
                'modes' => PracticeModes::LONG_TOSS->value])
        ]);

        LongTossPractice::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::TRAINING->value,
                'modes' => PracticeModes::LONG_TOSS->value])
        ]);
        LongTossPractice::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::TRAINING->value,
                'modes' => PracticeModes::LONG_TOSS->value])
        ]);
        LongTossPractice::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::TRAINING->value,
                'modes' => PracticeModes::LONG_TOSS->value])
        ]);
        LongTossPractice::factory(5)->create([
            'team_id' => $team->id,
            'updated_at' => fake()->dateTimeBetween('-1 years'),
            'created_at' => fake()->dateTimeBetween('-1 years'),
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::TRAINING->value,
                'modes' => PracticeModes::LONG_TOSS->value])
        ]);
        $response = $this->json('POST', 'api/table/'.$team->id, [
            'option'=>8,
            'range'=>3,
        ]);
        $response->assertOk()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data'
        ]);

    }



}
