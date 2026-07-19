<?php

declare(strict_types=1);

namespace Tests;

use App\Models\CoachTeam;
use App\Models\PlayerTeam;
use App\Models\Team;
use App\Models\User;
use App\Support\TestingDatabaseSafety;
use BackedEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\WithFaker;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;
    use WithFaker;

    protected function setUp(): void
    {
        $environment = (string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '');
        $database = (string) ($_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: '');
        $approved = array_filter(array_map('trim', explode(',', (string) ($_ENV['TEST_DATABASE_ALLOWLIST'] ?? getenv('TEST_DATABASE_ALLOWLIST') ?: ''))));

        TestingDatabaseSafety::assertSafe($environment, $database, $approved);

        parent::setUp();
    }

    protected function grantTeamAccess(User $user, Team $team): void
    {
        $type = $user->type instanceof BackedEnum ? $user->type->value : (string) $user->type;

        if ('coach' === $type) {
            CoachTeam::factory()->create([
                'coach_id' => $user->id,
                'team_id' => $team->id,
                'is_main' => true,
            ]);

            return;
        }

        PlayerTeam::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'actual' => true,
        ]);
    }
}
