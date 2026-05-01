<?php

declare(strict_types=1);

namespace Tests\Unit\User;

use App\Exceptions\NotCreated;
use App\Models\User;
use App\Services\CreateServiceData;
use Tests\TestCase;

class CreateTest extends TestCase
{
    public function test_create_user(): void
    {
        $user = new CreateServiceData(new User());
        $data = [
            'email' => $this->faker->safeEmail,
            'phone' => $this->faker->phoneNumber,
            'password' => bcrypt('password'),
            'type' => $this->faker->randomElement(['coach', 'player']),
            'status' => false,
        ];
        $result = $user->handle($data);
        $this->assertNotNull($result->id);
        $this->assertEquals($data['email'], $result->email);
        $this->assertEquals($data['phone'], $result->phone);
        $this->assertEquals($data['type'], $result->type);
    }

    public function test_create_user_exception(): void
    {
        $this->expectException(NotCreated::class);
        $user = new CreateServiceData(new User());
        $result = $user->handle([]);
    }
}
