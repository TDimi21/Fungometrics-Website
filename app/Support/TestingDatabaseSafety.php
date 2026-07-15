<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

class TestingDatabaseSafety
{
    /** @param array<int, string> $allowlist */
    public static function assertSafe(string $environment, string $database, array $allowlist): void
    {
        if ('testing' !== $environment
            || '' === $database
            || 'fungo' === $database
            || ! in_array($database, $allowlist, true)
            || preg_match('/(^|_)(prod|production)(_|$)/i', $database)) {
            throw new RuntimeException("Unsafe test database configuration refused: environment={$environment}, database={$database}");
        }
    }
}
