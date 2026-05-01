<?php

declare(strict_types=1);

namespace Tests\Feature\Api\DashBoard;

use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use App\Models\Concerns\UserTypes;
use App\Models\Practice;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use App\Models\WeightBallPractice;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetDataChartsAverageThrowVelocityTrainingTest extends TestCase
{
    public function test_get_charts_data_avg_throw_velocity_ok_range_all(): void
    {
        $date = Carbon::now();
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();
        $practice = Practice::factory()->create([
            'modes' => PracticeModes::WEIGHT_BALL->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice2 = Practice::factory()->create([
            'modes' => PracticeModes::WEIGHT_BALL->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice3 = Practice::factory()->create([
            'modes' => PracticeModes::WEIGHT_BALL->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice4 = Practice::factory()->create([
            'modes' => PracticeModes::WEIGHT_BALL->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);

        $profile1 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);
        $profile2 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $profile3 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $profile4 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile1->user_id,
            'practice_id' => $practice->id,
            'created_at' => now()
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile1->user_id,
            'practice_id' => $practice->id,
            'created_at' => now()
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile1->user_id,
            'practice_id' => $practice->id,
            'created_at' => now()
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile1->user_id,
            'practice_id' => $practice->id,
            'created_at' => now()
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile2->user_id,
            'practice_id' => $practice2->id,
            'created_at' => now()
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile2->user_id,
            'practice_id' => $practice2->id,
            'created_at' => now()
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile2->user_id,
            'practice_id' => $practice2->id,
            'created_at' => now()
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile2->user_id,
            'practice_id' => $practice2->id,
            'created_at' => now()
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile3->user_id,
            'practice_id' => $practice3->id,
            'created_at' => now()
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile3->user_id,
            'practice_id' => $practice3->id,
            'created_at' => now()
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile3->user_id,
            'practice_id' => $practice3->id,
            'created_at' => now()
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile3->user_id,
            'practice_id' => $practice3->id,
            'created_at' => now()
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile3->user_id,
            'practice_id' => $practice3->id,
            'created_at' => now()
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'practice_id' => $practice4->id,
            'created_at' => now()
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'practice_id' => $practice4->id,
            'created_at' => now()
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'practice_id' => $practice4->id,
            'created_at' => now()
        ]);


        $data = [
            'team'=>$team->id,
            'range' => 0,
            'players' => [$profile1->user_id, $profile2->user_id, $profile3->user_id, $profile4->user_id,],
            'type' => 6
        ];
        $response = $this->json('POST', 'api/charts/', $data);
        $response->assertOk()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data'
        ]);

    }
    public function test_get_charts_data_avg_throw_velocity_ok_range_one_year(): void
    {
        $date = Carbon::now();
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();
        $practice = Practice::factory()->create([
            'modes' => PracticeModes::WEIGHT_BALL->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice2 = Practice::factory()->create([
            'modes' => PracticeModes::WEIGHT_BALL->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice3 = Practice::factory()->create([
            'modes' => PracticeModes::WEIGHT_BALL->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice4 = Practice::factory()->create([
            'modes' => PracticeModes::WEIGHT_BALL->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);

        $profile1 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);
        $profile2 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $profile3 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $profile4 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile1->user_id,
            'practice_id' => $practice->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile1->user_id,
            'practice_id' => $practice->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile1->user_id,
            'practice_id' => $practice->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile1->user_id,
            'practice_id' => $practice->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile2->user_id,
            'practice_id' => $practice2->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile2->user_id,
            'practice_id' => $practice2->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile2->user_id,
            'practice_id' => $practice2->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile2->user_id,
            'practice_id' => $practice2->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile3->user_id,
            'practice_id' => $practice3->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile3->user_id,
            'practice_id' => $practice3->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile3->user_id,
            'practice_id' => $practice3->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile3->user_id,
            'practice_id' => $practice3->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile3->user_id,
            'practice_id' => $practice3->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'practice_id' => $practice4->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'practice_id' => $practice4->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'practice_id' => $practice4->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);


        $data = [
            'team'=>$team->id,
            'range' => 12,
            'players' => [$profile1->user_id, $profile2->user_id, $profile3->user_id, $profile4->user_id,],
            'type' => 6
        ];
        $response = $this->json('POST', 'api/charts/', $data);
        $response->assertOk()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data'
        ]);
    }
    public function test_get_charts_data_avg_throw_velocity_ok_range_six_months(): void
    {
        $date = Carbon::now();
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();
        $practice = Practice::factory()->create([
            'modes' => PracticeModes::WEIGHT_BALL->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice2 = Practice::factory()->create([
            'modes' => PracticeModes::WEIGHT_BALL->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice3 = Practice::factory()->create([
            'modes' => PracticeModes::WEIGHT_BALL->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice4 = Practice::factory()->create([
            'modes' => PracticeModes::WEIGHT_BALL->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);

        $profile1 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);
        $profile2 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $profile3 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $profile4 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile1->user_id,
            'practice_id' => $practice->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile1->user_id,
            'practice_id' => $practice->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile1->user_id,
            'practice_id' => $practice->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile1->user_id,
            'practice_id' => $practice->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile2->user_id,
            'practice_id' => $practice2->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile2->user_id,
            'practice_id' => $practice2->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile2->user_id,
            'practice_id' => $practice2->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile2->user_id,
            'practice_id' => $practice2->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile3->user_id,
            'practice_id' => $practice3->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile3->user_id,
            'practice_id' => $practice3->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile3->user_id,
            'practice_id' => $practice3->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile3->user_id,
            'practice_id' => $practice3->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile3->user_id,
            'practice_id' => $practice3->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'practice_id' => $practice4->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'practice_id' => $practice4->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'practice_id' => $practice4->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);


        $data = [
            'team'=>$team->id,
            'range' => 6,
            'players' => [$profile1->user_id, $profile2->user_id, $profile3->user_id, $profile4->user_id,],
            'type' => 6
        ];
        $response = $this->json('POST', 'api/charts/', $data);
        $response->assertOk()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data'
        ]);
    }
    public function test_get_charts_data_avg_throw_velocity_ok_range_three_months(): void
    {
        $date = Carbon::now();
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();
        $practice = Practice::factory()->create([
            'modes' => PracticeModes::WEIGHT_BALL->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice2 = Practice::factory()->create([
            'modes' => PracticeModes::WEIGHT_BALL->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice3 = Practice::factory()->create([
            'modes' => PracticeModes::WEIGHT_BALL->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice4 = Practice::factory()->create([
            'modes' => PracticeModes::WEIGHT_BALL->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);

        $profile1 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);
        $profile2 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $profile3 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $profile4 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile1->user_id,
            'practice_id' => $practice->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile1->user_id,
            'practice_id' => $practice->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile1->user_id,
            'practice_id' => $practice->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile1->user_id,
            'practice_id' => $practice->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile2->user_id,
            'practice_id' => $practice2->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile2->user_id,
            'practice_id' => $practice2->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile2->user_id,
            'practice_id' => $practice2->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile2->user_id,
            'practice_id' => $practice2->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile3->user_id,
            'practice_id' => $practice3->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile3->user_id,
            'practice_id' => $practice3->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile3->user_id,
            'practice_id' => $practice3->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile3->user_id,
            'practice_id' => $practice3->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile3->user_id,
            'practice_id' => $practice3->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'practice_id' => $practice4->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'practice_id' => $practice4->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'practice_id' => $practice4->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);


        $data = [
            'team'=>$team->id,
            'range' => 3,
            'players' => [$profile1->user_id, $profile2->user_id, $profile3->user_id, $profile4->user_id,],
            'type' => 6
        ];
        $response = $this->json('POST', 'api/charts/', $data);
        $response->assertOk()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data'
        ]);
    }
    public function test_get_charts_data_avg_throw_velocity_not_found(): void
    {
        $date = Carbon::now();
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);

        $profile1 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);
        $profile2 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $profile3 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $profile4 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $data = [
            'team'=>fake()->uuid,
            'range' => 0,
            'players' => [$profile1->user_id, $profile2->user_id, $profile3->user_id, $profile4->user_id,],
            'type' => 6
        ];
        $response = $this->json('POST', 'api/charts/', $data);
        $response->assertNotFound()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data'
        ]);
    }

    public function test_get_charts_data_avg_throw_velocity_ok_no_team(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $practice = Practice::factory()->create([
            'modes' => PracticeModes::WEIGHT_BALL->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice2 = Practice::factory()->create([
            'modes' => PracticeModes::WEIGHT_BALL->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice3 = Practice::factory()->create([
            'modes' => PracticeModes::WEIGHT_BALL->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice4 = Practice::factory()->create([
            'modes' => PracticeModes::WEIGHT_BALL->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);

        $profile1 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);


        WeightBallPractice::factory()->create([
            'user_id' => $profile1->user_id,
            'practice_id' => $practice->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'user_id' => $profile1->user_id,
            'practice_id' => $practice->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'user_id' => $profile1->user_id,
            'practice_id' => $practice->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'user_id' => $profile1->user_id,
            'practice_id' => $practice->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'user_id' => $profile1->user_id,
            'practice_id' => $practice2->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'user_id' => $profile1->user_id,
            'practice_id' => $practice2->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'user_id' => $profile1->user_id,
            'practice_id' => $practice2->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'user_id' => $profile1->user_id,
            'practice_id' => $practice2->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'user_id' => $profile1->user_id,
            'practice_id' => $practice3->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'user_id' => $profile1->user_id,
            'practice_id' => $practice3->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'user_id' => $profile1->user_id,
            'practice_id' => $practice3->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'user_id' => $profile1->user_id,
            'practice_id' => $practice3->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'user_id' => $profile1->user_id,
            'practice_id' => $practice3->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'user_id' => $profile1->user_id,
            'practice_id' => $practice4->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'user_id' => $profile1->user_id,
            'practice_id' => $practice4->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);
        WeightBallPractice::factory()->create([
            'user_id' => $profile1->user_id,
            'practice_id' => $practice4->id,
            'created_at' => fake()->dateTimeBetween('-1 years')
        ]);

        $data = [
            'range' => 0,
            'players' => [$profile1->user_id],
            'type' => 6
        ];

        $response = $this->json('POST', 'api/charts/', $data);
        $response->assertOk()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data'
        ]);
    }

    public function test_get_charts_data_avg_throw_velocity_not_found_no_team(): void
    {
        $date = Carbon::now();
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);

        $data = [
            'range' => 0,
            'players' => [fake()->uuid],
            'type' => 6
        ];
        //avg exit velocity -- live
        $response = $this->json('POST', 'api/charts/', $data);
        $response->assertNotFound()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data'
        ]);
        //max exit velocity -- exit velocity
        //max cage distance -- cage
        //total strike percent -- pitching
        //max fb velocity -- pitching
        //average throw velocity for each weigth -- weigth ball
        //max distance throws with 0 hops -- long toss
        //average traininig exit velocity per session -- exit velocity
    }
}
