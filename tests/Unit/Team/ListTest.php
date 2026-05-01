<?php

declare(strict_types=1);

namespace Tests\Unit\Team;

use App\Exceptions\NotFound;
use App\Models\Team;
use App\Services\ListServiceData;
use Faker\Provider\en_US\Address;
use Str;
use Tests\TestCase;

class ListTest extends TestCase
{
    public function test_get_all_team(): void
    {
        $numModels = 5;
        Team::factory()->count($numModels)->create();
        $teamModel = new ListServiceData(new Team());
        $result = $teamModel->all();
        $this->assertEquals($numModels, $result->count());
    }

    public function test_get_all_team_exception(): void
    {
        $this->expectException(NotFound::class);
        $teamModel = new ListServiceData(new Team());
        $teamModel->all();
    }

    public function test_get_team_by_uuid(): void
    {
        $data = Team::factory()->create();
        $teamModel = new ListServiceData(new Team());
        $result = $teamModel->findByUuid($data->id);
        $this->assertEquals($data->id, $result->id);
    }

    public function test_get_team_by_uuid_exception(): void
    {
        $this->expectException(NotFound::class);
        Team::factory()->count(2)->create();
        $teamModel = new ListServiceData(new Team());
        $teamModel->findByUuid(Str::uuid()->toString());
    }

    public function test_get_team_by_param(): void
    {
        $numModels = 5;
        $colum = 'state';
        $value = 'test state';
        Team::factory()->count($numModels)->create();
        $teamTemp = Team::factory()->create([
            $colum => $value,
        ]);
        $teamModel = new ListServiceData(new Team());
        $result = $teamModel->byParamFirst($colum, $value);
        $this->assertEquals($teamTemp->count(), $result->count());
        $this->assertEquals($teamTemp->id, $result->id);
        $this->assertEquals($teamTemp->logo, $result->logo);
        $this->assertEquals($teamTemp->state, $result->state);
    }

    public function test_get_team_by_param_null(): void
    {
        $numModels = 5;
        $colum = 'state';
        $value = fake()->word;
        Team::factory()->count($numModels)->create();
        Team::factory()->create([
            $colum => Address::state(),
        ]);
        $teamModel = new ListServiceData(new Team());
        $data = $teamModel->byParamFirst($colum, $value);
        $this->assertNull($data);
    }

    public function test_get_team_by_params(): void
    {
        $numModels = 5;
        $value = Address::state();
        Team::factory()->count($numModels)->create();
        Team::factory()->count($numModels)->create(['state' => $value, 'status' => true]);
        $teamModel = new ListServiceData(new Team());
        $conditions = ['state' => $value, 'status' => true];
        $result = $teamModel->byParams($conditions);
        $this->assertGreaterThanOrEqual($numModels, $result->count());
    }

    public function test_get_team_by_params_no_result(): void
    {
        $numModels = 5;
        Team::factory()->count($numModels)->create(['status' => true]);
        $teamModel = new ListServiceData(new Team());
        $conditions = ['state' => fake()->word, 'status' => false];
        $result = $teamModel->byParams($conditions);
        $this->assertEquals(0, $result->count());
    }
}
