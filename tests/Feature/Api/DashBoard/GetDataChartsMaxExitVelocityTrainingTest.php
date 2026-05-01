<?php

declare(strict_types=1);

namespace Tests\Feature\Api\DashBoard;

use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use App\Models\Concerns\UserTypes;
use App\Models\ExitVelocityPractice;
use App\Models\Practice;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetDataChartsMaxExitVelocityTrainingTest extends TestCase
{
    public function test_get_charts_data_max_exit_velocity_ok_range_all(): void
    {
        $date = Carbon::now();
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();
        $practice = Practice::factory()->create([
            'modes' => PracticeModes::EXIT_VELOCITY->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice2 = Practice::factory()->create([
            'modes' => PracticeModes::EXIT_VELOCITY->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice3 = Practice::factory()->create([
            'modes' => PracticeModes::EXIT_VELOCITY->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice4 = Practice::factory()->create([
            'modes' => PracticeModes::EXIT_VELOCITY->value,
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
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile1->user_id,
            'created_at' => fake()->dateTime(),
            'practice_id' => $practice->id
        ]);

        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile2->user_id,
            'created_at' => fake()->dateTime(),
            'practice_id' => $practice2->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile3->user_id,
            'created_at' => fake()->dateTime(),
            'practice_id' => $practice3->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'created_at' => fake()->dateTime(),
            'practice_id' => $practice4->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'created_at' => fake()->dateTime(),
            'practice_id' => $practice4->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'created_at' => fake()->dateTime(),
            'practice_id' => $practice4->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'created_at' => now(),
            'practice_id' => $practice4->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'created_at' => now(),
            'practice_id' => $practice4->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'created_at' => now(),
            'practice_id' => $practice4->id
        ]);

        $data = [
            'team'=>$team->id,
            'range' => 0,
            'players' => [$profile1->user_id, $profile2->user_id, $profile3->user_id, $profile4->user_id,],
            'type' => 2
        ];
        //max exit velocity -- exit velocity
        $response = $this->json('POST', 'api/charts/', $data);
        $response->assertOk()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data'
        ]);

        //max cage distance -- cage
        //total strike percent -- pitching
        //max fb velocity -- pitching
        //average throw velocity for each weigth -- weigth ball
        //max distance throws with 0 hops -- long toss
        //average traininig exit velocity per session -- exit velocity
    }

    public function test_get_charts_data_max_exit_velocity_ok_range_one_year(): void
    {
        $date = Carbon::now();
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();
        $practice = Practice::factory()->create([
            'modes' => PracticeModes::EXIT_VELOCITY->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice2 = Practice::factory()->create([
            'modes' => PracticeModes::EXIT_VELOCITY->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice3 = Practice::factory()->create([
            'modes' => PracticeModes::EXIT_VELOCITY->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice4 = Practice::factory()->create([
            'modes' => PracticeModes::EXIT_VELOCITY->value,
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
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile1->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date),
            'practice_id' => $practice->id
        ]);

        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile2->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date),
            'practice_id' => $practice2->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile3->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date),
            'practice_id' => $practice3->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date),
            'practice_id' => $practice4->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date),
            'practice_id' => $practice4->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date),
            'practice_id' => $practice4->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date),
            'practice_id' => $practice4->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date),
            'practice_id' => $practice4->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date),
            'practice_id' => $practice4->id
        ]);

        $data = [
            'range' => 12,
            'players' => [$profile1->user_id, $profile2->user_id, $profile3->user_id, $profile4->user_id,],
            'type' => 2
        ];
        //avg exit velocity -- live
        $response = $this->json('POST', 'api/charts/', $data);
        $response->assertOk()->assertJsonStructure([
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

    public function test_get_charts_data_max_exit_velocity_ok_range_six_months(): void
    {
        $date = Carbon::now();
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();
        $practice = Practice::factory()->create([
            'modes' => PracticeModes::EXIT_VELOCITY->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice2 = Practice::factory()->create([
            'modes' => PracticeModes::EXIT_VELOCITY->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice3 = Practice::factory()->create([
            'modes' => PracticeModes::EXIT_VELOCITY->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice4 = Practice::factory()->create([
            'modes' => PracticeModes::EXIT_VELOCITY->value,
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
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile1->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date),
            'practice_id' => $practice->id
        ]);

        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile2->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date),
            'practice_id' => $practice2->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile3->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date),
            'practice_id' => $practice3->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date),
            'practice_id' => $practice4->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date),
            'practice_id' => $practice4->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date),
            'practice_id' => $practice4->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date),
            'practice_id' => $practice4->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date),
            'practice_id' => $practice4->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date),
            'practice_id' => $practice4->id
        ]);

        $data = [
            'range' => 6,
            'players' => [$profile1->user_id, $profile2->user_id, $profile3->user_id, $profile4->user_id,],
            'type' => 2
        ];
        //avg exit velocity -- live
        $response = $this->json('POST', 'api/charts/', $data);
        $response->assertOk()->assertJsonStructure([
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


    public function test_get_charts_data_max_exit_velocity_ok_range_three_months(): void
    {
        $date = Carbon::now();
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();
        $practice = Practice::factory()->create([
            'modes' => PracticeModes::EXIT_VELOCITY->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice2 = Practice::factory()->create([
            'modes' => PracticeModes::EXIT_VELOCITY->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice3 = Practice::factory()->create([
            'modes' => PracticeModes::EXIT_VELOCITY->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice4 = Practice::factory()->create([
            'modes' => PracticeModes::EXIT_VELOCITY->value,
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
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile1->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date),
            'practice_id' => $practice->id
        ]);

        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile2->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date),
            'practice_id' => $practice2->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile3->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date),
            'practice_id' => $practice3->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date),
            'practice_id' => $practice4->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date),
            'practice_id' => $practice4->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date),
            'practice_id' => $practice4->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date),
            'practice_id' => $practice4->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date),
            'practice_id' => $practice4->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date),
            'practice_id' => $practice4->id
        ]);

        $data = [
            'range' => 3,
            'players' => [$profile1->user_id, $profile2->user_id, $profile3->user_id, $profile4->user_id,],
            'type' => 2
        ];
        //avg exit velocity -- live
        $response = $this->json('POST', 'api/charts/', $data);
        $response->assertOk()->assertJsonStructure([
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

    public function test_get_charts_data_max_exit_velocity_not_found(): void
    {
        $date = Carbon::now();
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();
        $practice = Practice::factory()->create([
            'modes' => PracticeModes::EXIT_VELOCITY->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice2 = Practice::factory()->create([
            'modes' => PracticeModes::EXIT_VELOCITY->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice3 = Practice::factory()->create([
            'modes' => PracticeModes::EXIT_VELOCITY->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice4 = Practice::factory()->create([
            'modes' => PracticeModes::EXIT_VELOCITY->value,
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
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile1->user_id,
            'created_at' => fake()->dateTime(),
            'practice_id' => $practice->id
        ]);

        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile2->user_id,
            'created_at' => fake()->dateTime(),
            'practice_id' => $practice2->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile3->user_id,
            'created_at' => fake()->dateTime(),
            'practice_id' => $practice3->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'created_at' => fake()->dateTime(),
            'practice_id' => $practice4->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'created_at' => fake()->dateTime(),
            'practice_id' => $practice4->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'created_at' => fake()->dateTime(),
            'practice_id' => $practice4->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'created_at' => now(),
            'practice_id' => $practice4->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'created_at' => now(),
            'practice_id' => $practice4->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile4->user_id,
            'created_at' => now(),
            'practice_id' => $practice4->id
        ]);

        $data = [
            'team'=>fake()->uuid,
            'range' => 0,
            'players' => [$profile1->user_id, $profile2->user_id, $profile3->user_id, $profile4->user_id,],
            'type' => 2
        ];
        //max exit velocity -- exit velocity
        $response = $this->json('POST', 'api/charts/', $data);
        $response->assertNotFound()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data'
        ]);


    }

    public function test_get_charts_data_max_exit_velocity_ok_no_team(): void
    {
        $date = Carbon::now();
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();
        $practice = Practice::factory()->create([
            'modes' => PracticeModes::EXIT_VELOCITY->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice2 = Practice::factory()->create([
            'modes' => PracticeModes::EXIT_VELOCITY->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice3 = Practice::factory()->create([
            'modes' => PracticeModes::EXIT_VELOCITY->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice4 = Practice::factory()->create([
            'modes' => PracticeModes::EXIT_VELOCITY->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);

        $profile1 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);
        ExitVelocityPractice::factory()->create([
            'user_id' => $profile1->user_id,
            'created_at' => fake()->dateTime(),
            'practice_id' => $practice->id
        ]);

        ExitVelocityPractice::factory()->create([
            'user_id' => $profile1->user_id,
            'created_at' => fake()->dateTime(),
            'practice_id' => $practice2->id
        ]);
        ExitVelocityPractice::factory()->create([
            'user_id' => $profile1->user_id,
            'created_at' => fake()->dateTime(),
            'practice_id' => $practice3->id
        ]);
        ExitVelocityPractice::factory()->create([
            'user_id' => $profile1->user_id,
            'created_at' => fake()->dateTime(),
            'practice_id' => $practice4->id
        ]);
        ExitVelocityPractice::factory()->create([
            'user_id' => $profile1->user_id,
            'created_at' => fake()->dateTime(),
            'practice_id' => $practice4->id
        ]);
        ExitVelocityPractice::factory()->create([
            'user_id' => $profile1->user_id,
            'created_at' => fake()->dateTime(),
            'practice_id' => $practice4->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile1->user_id,
            'created_at' => now(),
            'practice_id' => $practice4->id
        ]);
        ExitVelocityPractice::factory()->create([
            'user_id' => $profile1->user_id,
            'created_at' => now(),
            'practice_id' => $practice4->id
        ]);
        ExitVelocityPractice::factory()->create([
            'user_id' => $profile1->user_id,
            'created_at' => now(),
            'practice_id' => $practice4->id
        ]);

        $data = [
            'range' => 0,
            'players' => [$profile1->user_id,],
            'type' => 2
        ];
        //avg exit velocity -- live
        $response = $this->json('POST', 'api/charts/', $data);
        $response->assertOk()->assertJsonStructure([
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

    public function test_get_charts_data_max_exit_velocity_not_found_no_team(): void
    {
        $date = Carbon::now();
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();
        $practice = Practice::factory()->create([
            'modes' => PracticeModes::EXIT_VELOCITY->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice2 = Practice::factory()->create([
            'modes' => PracticeModes::EXIT_VELOCITY->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice3 = Practice::factory()->create([
            'modes' => PracticeModes::EXIT_VELOCITY->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);
        $practice4 = Practice::factory()->create([
            'modes' => PracticeModes::EXIT_VELOCITY->value,
            'type' => PracticeTypes::TRAINING->value,
        ]);

        $profile1 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);
        ExitVelocityPractice::factory()->create([
            'user_id' => $profile1->user_id,
            'created_at' => fake()->dateTime(),
            'practice_id' => $practice->id
        ]);

        ExitVelocityPractice::factory()->create([
            'user_id' => $profile1->user_id,
            'created_at' => fake()->dateTime(),
            'practice_id' => $practice2->id
        ]);
        ExitVelocityPractice::factory()->create([
            'user_id' => $profile1->user_id,
            'created_at' => fake()->dateTime(),
            'practice_id' => $practice3->id
        ]);
        ExitVelocityPractice::factory()->create([
            'user_id' => $profile1->user_id,
            'created_at' => fake()->dateTime(),
            'practice_id' => $practice4->id
        ]);
        ExitVelocityPractice::factory()->create([
            'user_id' => $profile1->user_id,
            'created_at' => fake()->dateTime(),
            'practice_id' => $practice4->id
        ]);
        ExitVelocityPractice::factory()->create([
            'user_id' => $profile1->user_id,
            'created_at' => fake()->dateTime(),
            'practice_id' => $practice4->id
        ]);
        ExitVelocityPractice::factory()->create([
            'team_id' => $team->id,
            'user_id' => $profile1->user_id,
            'created_at' => now(),
            'practice_id' => $practice4->id
        ]);
        ExitVelocityPractice::factory()->create([
            'user_id' => $profile1->user_id,
            'created_at' => now(),
            'practice_id' => $practice4->id
        ]);
        ExitVelocityPractice::factory()->create([
            'user_id' => $profile1->user_id,
            'created_at' => now(),
            'practice_id' => $practice4->id
        ]);

        $data = [
            'range' => 0,
            'players' => [fake()->uuid],
            'type' => 2
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
