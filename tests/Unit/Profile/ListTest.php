<?php

declare(strict_types=1);

namespace Tests\Unit\Profile;

use App\Exceptions\NotFound;
use App\Models\Profile;
use App\Models\User;
use App\Services\ListServiceData;
use Faker\Provider\en_US\Address;
use Illuminate\Support\Str;
use Tests\TestCase;

class ListTest extends TestCase
{
    public function test_get_all_profile(): void
    {
        $numModels = 5;
        $data = Profile::factory()->count($numModels)->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $profileModel = new ListServiceData(new Profile());
        $result = $profileModel->all();

        $this->assertEquals($numModels, $result->count());
    }

    public function test_get_all_profile_exception(): void
    {
        $this->expectException(NotFound::class);
        $profileModel = new ListServiceData(new Profile());
        $result = $profileModel->all();
    }

    public function test_get_profile_by_uuid(): void
    {
        $data = Profile::factory()->create(['user_id' => User::factory()->create()->id]);
        $profileModel = new ListServiceData(new Profile());
        $result = $profileModel->findByUuid($data->id);
        $this->assertEquals($data->id, $result->id);
    }

    public function test_get_profile_by_uuid_exception(): void
    {
        $this->expectException(NotFound::class);
        Profile::factory()->count(2)->create(['user_id' => User::factory()->create()->id]);
        $profileModel = new ListServiceData(new Profile());
        $profileModel->findByUuid(Str::uuid()->toString());
    }

    public function test_get_profile_by_param(): void
    {
        $numModels = 5;
        $colum = 'first_name';
        $value = fake()->firstName;
        Profile::factory()->count($numModels)->create(['user_id' => User::factory()->create()->id]);
        $profileTemp = Profile::factory()->create([
            'user_id' => User::factory()->create()->id,
            $colum => $value,
        ]);
        $profileModel = new ListServiceData(new Profile());
        $result = $profileModel->byParamFirst($colum, $value);
        $this->assertEquals($profileTemp->count(), $result->count());
        $this->assertEquals($profileTemp->id, $result->id);
        $this->assertEquals($profileTemp->first_name, $result->first_name);
        $this->assertEquals($profileTemp->last_name, $result->last_name);
        $this->assertEquals($profileTemp->state, $result->state);
    }

    public function test_get_profile_by_param_null(): void
    {
        $numModels = 5;
        $colum = 'first_name';
        $value = fake()->firstName;
        Profile::factory()->count($numModels)->create(['user_id' => User::factory()->create()->id]);
        Profile::factory()->create([
            'user_id' => User::factory()->create()->id,
            $colum => fake()->phoneNumber,
        ]);
        $profileModel = new ListServiceData(new Profile());
        $data = $profileModel->byParamFirst($colum, fake()->word);
        $this->assertNull($data);
    }

    public function test_get_profile_by_params(): void
    {
        $numModels = 5;
        $data = ['city' => fake()->city, 'state' => Address::state(), 'user_id' => User::factory()->create()->id];
        Profile::factory()->count($numModels)->create(['user_id' => User::factory()->create()->id]);
        Profile::factory()->count($numModels)->create($data);
        $profileModel = new ListServiceData(new Profile());
        $result = $profileModel->byParams($data);
        $this->assertGreaterThanOrEqual($numModels, $result->count());
    }

    public function test_get_profile_by_no_results(): void
    {
        $numModels = 5;
        Profile::factory()->count($numModels)->create(['user_id' => User::factory()->create()->id]);
        $profileModel = new ListServiceData(new Profile());
        $data = ['city' => fake()->city, 'state' => Address::state()];
        $result = $profileModel->byParams($data);
        $this->assertEquals(0, $result->count());
    }
}
