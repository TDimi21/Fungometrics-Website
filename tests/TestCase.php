<?php

declare(strict_types=1);

namespace Tests;

use App\Support\TestingDatabaseSafety;
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
}
