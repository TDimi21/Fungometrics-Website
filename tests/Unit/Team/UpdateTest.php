<?php

declare(strict_types=1);

namespace Tests\Unit\Team;

use App\Exceptions\UpdateException;
use App\Models\Team;
use App\Services\UpdateServiceData;
use Illuminate\Support\Str;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    public function test_update_team(): void
    {
        $team = Team::factory()->create();
        $data = [
            'name' => fake()->word,
            'status' => true,
        ];

        $teamUpdate = new UpdateServiceData(new Team());

        $result = $teamUpdate->handle($team->id, $data);

        $this->assertEquals($data['name'], $result->name);
        $this->assertEquals($data['status'], $result->status);
    }

    public function test_update_team_not_found(): void
    {
        $this->expectException(UpdateException::class);
        $team = Team::factory()->create(['name' => fake()->word, 'status' => false]);
        $data = [
            'name' => 'coach',
            'status' => true,
        ];

        $teamUpdate = new UpdateServiceData(new Team());
        $result = $teamUpdate->handle(Str::uuid()->toString(), $data);
    }

    public function test_update_team_exception(): void
    {
        $this->expectException(UpdateException::class);
        $team = Team::factory()->create();
        $data = [
            'name' => null,
            'status' => null,
        ];

        $teamUpdate = new UpdateServiceData(new Team());

        $result = $teamUpdate->handle($team->id, $data);
    }
}
