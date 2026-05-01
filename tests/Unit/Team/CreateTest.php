<?php

declare(strict_types=1);

namespace Tests\Unit\Team;

use App\Exceptions\NotCreated;
use App\Models\Team;
use App\Services\CreateServiceData;
use Faker\Provider\en_US\Address;
use Tests\TestCase;

class CreateTest extends TestCase
{
    public function test_create_team(): void
    {
        $team = new CreateServiceData(new Team());
        $data = [
            'name' => fake()->word.' '.fake()->city,
            'logo' => fake()->imageUrl,
            'state' => Address::state(),
            'zip' => Address::postcode(),
        ];
        $result = $team->handle($data);
        $this->assertNotNull($result->id);
        $this->assertEquals($data['name'], $result->name);
        $this->assertEquals($data['logo'], $result->logo);
        $this->assertEquals($data['state'], $result->state);
    }

    public function test_create_team_exception(): void
    {
        $this->expectException(NotCreated::class);
        $team = new CreateServiceData(new Team());
        $result = $team->handle([]);
    }
}
