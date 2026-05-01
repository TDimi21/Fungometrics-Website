<?php

declare(strict_types=1);

namespace Tests\Unit\User;

use App\Exceptions\NotFound;
use App\Models\User;
use App\Services\ListServiceData;
use Illuminate\Support\Str;
use Tests\TestCase;

class ListTest extends TestCase
{
    public function test_get_all_user(): void
    {
        $numModels = 5;
        $data = User::factory()->count($numModels)->create();

        $userModel = new ListServiceData(new User());
        $result = $userModel->all();

        $this->assertEquals($numModels, $result->count());
    }

    public function test_get_all_user_exception(): void
    {
        $this->expectException(NotFound::class);
        $userModel = new ListServiceData(new User());
        $result = $userModel->all();
    }

    public function test_get_user_by_uuid(): void
    {
        $data = User::factory()->create();
        $userModel = new ListServiceData(new User());
        $result = $userModel->findByUuid($data->id);
        $this->assertEquals($data->id, $result->id);
    }

    public function test_get_user_by_uuid_exception(): void
    {
        $this->expectException(NotFound::class);
        User::factory()->count(2)->create();
        $userModel = new ListServiceData(new User());
        $userModel->findByUuid(Str::uuid()->toString());
    }

    public function test_get_user_by_param(): void
    {
        $numModels = 5;
        $colum = 'phone';
        $value = fake()->phoneNumber;
        User::factory()->count($numModels)->create();
        $userTemp = User::factory()->create([
            $colum => $value,
        ]);
        $userModel = new ListServiceData(new User());
        $result = $userModel->byParamFirst($colum, $value);
        $this->assertEquals($userTemp->count(), $result->count());
        $this->assertEquals($userTemp->id, $result->id);
        $this->assertEquals($userTemp->phone, $result->phone);
        $this->assertEquals($userTemp->email, $result->email);
        $this->assertEquals($userTemp->status, $result->status);
    }

    public function test_get_user_by_param_null(): void
    {
        $numModels = 5;
        $colum = 'phone';
        $value = fake()->phoneNumber;
        User::factory()->count($numModels)->create();
        User::factory()->create([
            $colum => fake()->phoneNumber,
        ]);
        $userModel = new ListServiceData(new User());
        $data = $userModel->byParamFirst($colum, $value);
        $this->assertNull($data);
    }

    public function test_get_user_by_params(): void
    {
        $numModels = 5;
        User::factory()->count($numModels)->create(['type' => 'player']);
        User::factory()->count($numModels)->create(['type' => 'coach', 'status' => true]);
        $userModel = new ListServiceData(new User());
        $conditions = ['type' => 'coach', 'status' => true];
        $result = $userModel->byParams($conditions);
        $this->assertGreaterThanOrEqual($numModels, $result->count());
    }

    public function test_get_user_by_params_no_result(): void
    {
        $numModels = 5;
        User::factory()->count($numModels)->create(['type' => 'coach', 'status' => false]);
        $userModel = new ListServiceData(new User());
        $conditions = ['type' => 'player', 'status' => false];
        $result = $userModel->byParams($conditions);
        $this->assertEquals(0, $result->count());
    }
}
