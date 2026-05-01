<?php

declare(strict_types=1);

namespace Tests\Unit\User;

use App\Exceptions\UpdateException;
use App\Models\User;
use App\Services\UpdateServiceData;
use Illuminate\Support\Str;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    public function test_update_user(): void
    {
        $user = User::factory()->create(['type' => 'player', 'status' => false]);
        $data = [
            'type' => 'coach',
            'status' => true,
        ];

        $userUpdate = new UpdateServiceData(new User());

        $result = $userUpdate->handle($user->id, $data);

        $this->assertEquals($data['type'], $result->type);
        $this->assertEquals($data['status'], $result->status);
    }

    public function test_update_user_not_found(): void
    {
        $this->expectException(UpdateException::class);
        $user = User::factory()->create(['type' => 'player', 'status' => false]);
        $data = [
            'type' => 'coach',
            'status' => true,
        ];

        $userUpdate = new UpdateServiceData(new User());

        $result = $userUpdate->handle(Str::uuid()->toString(), $data);

        $this->assertEquals($data['type'], $result->type);
        $this->assertEquals($data['status'], $result->status);
    }

    public function test_update_user_exception(): void
    {
        $this->expectException(UpdateException::class);
        $user = User::factory()->create(['type' => 'player', 'status' => false]);
        $data = [
            'type' => null,
            'status' => null,
        ];

        $userUpdate = new UpdateServiceData(new User());

        $result = $userUpdate->handle($user->id, $data);
    }
}
