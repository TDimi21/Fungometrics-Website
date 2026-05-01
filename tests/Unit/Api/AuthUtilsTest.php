<?php

declare(strict_types=1);

namespace Tests\Unit\Api;

use App\Exceptions\NotFound;
use App\Http\Controllers\Api\Auth\AuthUtils;
use App\Models\User;
use Tests\TestCase;

class AuthUtilsTest extends TestCase
{
    public function test_create_token(): void
    {
        $user = User::factory()->create();
        $response = AuthUtils::createTokenFromUser($user);
        $this->assertNotNull($response['token']);
        $this->assertNotNull($response['model']);
    }

    public function test_create_token_exception(): void
    {
        $this->expectException(NotFound::class);
        $response = AuthUtils::createTokenFromUser(new User());
    }
}
