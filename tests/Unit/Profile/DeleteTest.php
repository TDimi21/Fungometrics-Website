<?php

declare(strict_types=1);

namespace Tests\Unit\Profile;

use App\Exceptions\DeleteException;
use App\Models\Profile;
use App\Models\User;
use App\Services\DeleteServiceData;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeleteTest extends TestCase
{
    public function test_delete_profile(): void
    {
        $profile = Profile::factory()->create(['user_id' => User::factory()->create()->id]);
        $tempData = new DeleteServiceData(new Profile());
        $result = $tempData->handle($profile->id);
        $this->assertTrue($result);
    }

    public function test_delete_profile_exception(): void
    {
        $this->expectException(DeleteException::class);
        $profile = Profile::factory()->create(['user_id' => User::factory()->create()->id]);
        $tempData = new DeleteServiceData(new Profile());
        $result = $tempData->handle(Str::uuid()->toString());
    }
}
