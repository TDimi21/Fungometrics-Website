<?php

declare(strict_types=1);

namespace Tests\Unit\User;

use App\Exceptions\DeleteException;
use App\Models\User;
use App\Services\DeleteServiceData;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeleteTest extends TestCase
{
    public function test_delete_user(): void
    {
        $user = User::factory()->create();
        $tempData = new DeleteServiceData(new User());
        $result = $tempData->handle($user->id);
        $this->assertTrue($result);
    }

    public function test_delete_user_exception(): void
    {
        $this->expectException(DeleteException::class);
        $user = User::factory()->create();
        $tempData = new DeleteServiceData(new User());
        $result = $tempData->handle(Str::uuid()->toString());
    }
}
