<?php

declare(strict_types=1);

namespace Tests\Unit\Relations;

use App\Models\Profile;
use App\Models\User;
use App\Services\ListServiceData;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    public function test_get_user_relation(): void
    {
        $profile = Profile::factory()->create(['user_id' => User::factory()->create()->id]);
        $profileTest = new ListServiceData(new Profile());
        $result = $profileTest->findByUuid($profile->id);
        $this->assertNotNull($result->user);
        $this->assertNotNull($result->user->email);
        $this->assertNotNull($result->user->id);
        $this->assertNotNull($result->user->type);
    }
}
