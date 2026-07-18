<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Coach;

use App\Http\Requests\Api\Coach\AddTeamRequest;
use App\Models\CoachTeam;
use App\Models\Concerns\UserTypes;
use App\Models\PlanEntitlement;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use Faker\Provider\en_US\Address;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AddTeamTest extends TestCase
{
    public function test_finite_team_limit_blocks_a_second_team_without_removing_the_existing_team(): void
    {
        Storage::fake('s3');
        $coach = User::factory()->create([
            'type' => UserTypes::COACH->value,
            'subscription_plan' => 'coach_basic',
        ]);
        $plan = SubscriptionPlan::query()->where('key', 'coach_basic')->firstOrFail();
        PlanEntitlement::query()->firstOrCreate([
            'subscription_plan_id' => $plan->id,
            'entitlement_key' => 'add_team',
        ], ['metadata' => []]);
        $metadata = $plan->metadata;
        $metadata['limits']['teams'] = 1;
        $plan->update(['metadata' => $metadata]);
        $existingTeam = Team::factory()->create(['is_dummy' => false]);
        CoachTeam::factory()->create([
            'coach_id' => $coach->id,
            'team_id' => $existingTeam->id,
            'is_main' => true,
        ]);
        Sanctum::actingAs($coach, [UserTypes::COACH->value]);

        $this->json('POST', 'api/coach/add/teams', [
            'name' => fake()->words(2, true),
            'zip' => Address::postcode(),
            'state' => Address::state(),
            'logo' => UploadedFile::fake()->image('second-team.jpg'),
        ])->assertForbidden()->assertJsonPath('code', '004-LIMIT');

        $this->assertSame(1, CoachTeam::query()
            ->where('coach_id', $coach->id)
            ->whereHas('team', fn ($query) => $query->where('is_dummy', false))
            ->count());
    }

    public function test_coach_pro_unlimited_team_limit_allows_a_second_team(): void
    {
        Storage::fake('s3');
        $coach = User::factory()->create([
            'type' => UserTypes::COACH->value,
            'subscription_plan' => 'coach_pro',
        ]);
        $existingTeam = Team::factory()->create(['is_dummy' => false]);
        CoachTeam::factory()->create([
            'coach_id' => $coach->id,
            'team_id' => $existingTeam->id,
            'is_main' => true,
        ]);
        Sanctum::actingAs($coach, [UserTypes::COACH->value]);

        $this->json('POST', 'api/coach/add/teams', [
            'name' => fake()->words(2, true),
            'zip' => Address::postcode(),
            'state' => Address::state(),
            'logo' => UploadedFile::fake()->image('second-team.jpg'),
        ])->assertCreated();

        $this->assertSame(2, CoachTeam::query()
            ->where('coach_id', $coach->id)
            ->whereHas('team', fn ($query) => $query->where('is_dummy', false))
            ->count());
    }

    public function test_add_team_to_coach_ok(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value,
            'subscription_plan' => 'coach_pro',
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $data = [
            'name' => fake()->word.' '.fake()->word,
            'zip' => Address::postcode(),
            'state' => Address::state(),
            'logo' => UploadedFile::fake()->image('team.jpg'),
        ];
        $response = $this->json('POST', 'api/coach/add/teams', $data);
        $data_response = json_decode($response->getContent(), false, 512, JSON_THROW_ON_ERROR);
        $response->assertCreated();
        $this->assertEquals($data_response->data->name, $data['name']);
        $this->assertEquals($data_response->data->state, $data['state']);
        $this->assertNotNull($data_response->data->logo);
    }

    public function test_add_team_to_coach_unauthorized(): void
    {
        User::factory()->create(['type' => UserTypes::COACH->value]);
        $data = [
            'name' => fake()->word.' '.fake()->word,
            'zip' => Address::postcode(),
            'state' => Address::state(),
            'logo' => fake()->imageUrl,
        ];
        $response = $this->json('POST', 'api/coach/add/teams', $data);
        $response->assertUnauthorized();
    }

    public function test_add_team_to_coach_validated(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value,
            'subscription_plan' => 'coach_pro',
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);

        $data = [

        ];
        $response = $this->json('POST', 'api/coach/add/teams', $data);
        $response->assertUnprocessable();
    }

    public function test_add_team_to_coach_error(): void
    {
        $this->mock(AddTeamRequest::class, function ($mock): void {
            $mock->shouldReceive('passes')->andReturn(true);
        });
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value,
            'subscription_plan' => 'coach_pro',
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);

        $data = [
        ];
        $response = $this->json('POST', 'api/coach/add/teams', $data);
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }



    public function test_add_team_to_coach_forbidden(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user, ['player']);
        $data = [
            'name' => fake()->word.' '.fake()->word,
            'zip' => Address::postcode(),
            'state' => Address::state(),
            'logo' => fake()->imageUrl,
        ];
        $response = $this->json('POST', 'api/coach/add/teams', $data);
        $response->assertForbidden();
    }
}
