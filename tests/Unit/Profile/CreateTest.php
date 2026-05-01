<?php

declare(strict_types=1);

namespace Tests\Unit\Profile;

use App\Exceptions\NotCreated;
use App\Models\Profile;
use App\Models\User;
use App\Services\CreateServiceData;
use Faker\Provider\Address;
use Tests\TestCase;

class CreateTest extends TestCase
{
    public function test_create_profile(): void
    {
        $profile = new CreateServiceData(new Profile());
        $data = [
            'first_name' => fake()->firstName,
            'last_name' => fake()->lastName,
            'user_id' => User::factory()->create()->id,
            'picture' => fake()->imageUrl,
            'city' => fake()->city,
            'zip' => Address::postcode(),
            'state' => \Faker\Provider\en_US\Address::state(),
        ];
        $result = $profile->handle($data);
        $this->assertNotNull($result->id);
    }

    public function test_create_profile_exception(): void
    {
        $this->expectException(NotCreated::class);
        $profile = new CreateServiceData(new Profile());
        $result = $profile->handle([]);
    }
}
