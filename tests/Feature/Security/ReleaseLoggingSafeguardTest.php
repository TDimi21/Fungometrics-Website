<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class ReleaseLoggingSafeguardTest extends TestCase
{
    public function test_known_sensitive_values_are_not_written_to_production_logs(): void
    {
        $forbidden = [
            '/Log::(?:debug|info|notice|warning|error)\s*\([^;]*(?:api_token|authorization|bearer|request->all|request->input|subscription_plan|phone|email)/is',
            '/Log::info\s*\(\s*\$(?:message|element|request|response)/i',
        ];

        $violations = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path()));
        foreach ($files as $file) {
            if (! $file->isFile() || 'php' !== $file->getExtension()) {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            foreach ($forbidden as $pattern) {
                if (preg_match($pattern, $source)) {
                    $violations[] = str_replace(base_path().'/', '', $file->getPathname());
                }
            }
        }

        $this->assertSame([], array_values(array_unique($violations)), 'Sensitive production logging found.');
    }
}
