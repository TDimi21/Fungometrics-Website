<?php

declare(strict_types=1);

namespace Tests\Unit\Team;

use App\Exceptions\DeleteException;
use App\Models\Team;
use App\Services\DeleteServiceData;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeleteTest extends TestCase
{
    public function test_delete_team(): void
    {
        $team = Team::factory()->create();
        $tempData = new DeleteServiceData(new Team());
        $result = $tempData->handle($team->id);
        $this->assertTrue($result);
    }

    public function test_delete_team_exception(): void
    {
        $this->expectException(DeleteException::class);
        $team = Team::factory()->create();
        $tempData = new DeleteServiceData(new Team());
        $result = $tempData->handle(Str::uuid()->toString());
    }
}
