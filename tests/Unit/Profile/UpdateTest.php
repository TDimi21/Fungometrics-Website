<?php

declare(strict_types=1);

namespace Tests\Unit\Profile;

use App\Exceptions\UpdateException;
use App\Models\Profile;
use App\Models\User;
use App\Services\UpdateServiceData;
use Faker\Provider\en_US\Address;
use Illuminate\Support\Str;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    public function test_update_profile(): void
    {
        $profile = Profile::factory()->create(['user_id' => User::factory()->create()->id]);
        $data = [
            'city' => fake()->city,
            'state' => Address::state(),
        ];

        $profileUpdate = new UpdateServiceData(new Profile());

        $result = $profileUpdate->handle($profile->id, $data);

        $this->assertEquals($data['city'], $result->city);
        $this->assertEquals($data['state'], $result->state);
    }

    public function test_update_profile_not_found(): void
    {
        $this->expectException(UpdateException::class);
        $profile = Profile::factory()->create(['user_id' => User::factory()->create()->id]);
        $data = [
            'city' => fake()->city,
            'state' => Address::state(),
        ];

        $profileUpdate = new UpdateServiceData(new Profile());

        $result = $profileUpdate->handle(Str::uuid()->toString(), $data);

        $this->assertEquals($data['type'], $result->type);
        $this->assertEquals($data['status'], $result->status);
    }

    public function test_update_profile_exception(): void
    {
        $this->expectException(UpdateException::class);
        $profile = Profile::factory()->create(['user_id' => User::factory()->create()->id]);
        $data = [
            'first_name' => null,
        ];

        $profileUpdate = new UpdateServiceData(new Profile());

        $result = $profileUpdate->handle($profile->id, $data);
    }
}
