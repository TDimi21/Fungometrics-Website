<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\TestingDatabaseSafety;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TestingDatabaseSafetyTest extends TestCase
{
    public function test_explicit_test_database_is_approved(): void
    {
        TestingDatabaseSafety::assertSafe('testing', 'fungo_test', ['fungo_test']);
        $this->addToAssertionCount(1);
    }

    /** @dataProvider unsafeConfigurations */
    public function test_unsafe_database_configurations_are_refused(string $environment, string $database): void
    {
        $this->expectException(RuntimeException::class);
        TestingDatabaseSafety::assertSafe($environment, $database, ['fungo_test', $database]);
    }

    public static function unsafeConfigurations(): array
    {
        return [['local', 'fungo_test'], ['testing', 'fungo'], ['testing', 'fmtrx_production'], ['testing', '']];
    }
}
