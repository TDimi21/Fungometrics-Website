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
        $forbidden = $this->patterns();

        $violations = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path()));
        foreach ($files as $file) {
            if (! $file->isFile() || 'php' !== $file->getExtension()) {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            // Exclude only literal event labels (first argument), never context keys/values.
            // Variable messages and interpolated strings remain scanned.
            $source = $this->withoutEventLabels($source);
            foreach ($forbidden as $pattern) {
                if (preg_match($pattern, $source)) {
                    $violations[] = str_replace(base_path().'/', '', $file->getPathname());
                }
            }
        }

        $this->assertSame([], array_values(array_unique($violations)), 'Sensitive production logging found.');
    }
    private function patterns(): array
    {
        return [
            '/Log::(?:debug|info|notice|warning|error)\s*\([^;]*(?:api_token|authorization|bearer|request->all|request->input|subscription_plan|phone|email)/is',
            '/Log::info\s*\(\s*\$(?:message|element|request|response)/i',
        ];
    }

    private function withoutEventLabels(string $source): string
    {
        return preg_replace('/(Log::(?:debug|info|notice|warning|error)\\s*\\(\\s*)\'[a-z0-9_.-]+\'\\s*,/i', '$1\'event\',', $source);
    }

    public function test_scanner_ignores_fixed_labels_but_still_detects_secret_values(): void
    {
        $safe = "Log::notice('player_creation.authorization_denied', ['status' => 403]);";
        $this->assertSame(0, preg_match($this->patterns()[0], $this->withoutEventLabels($safe)));
        foreach ([
            'Log::notice("event", ["authorization" => $request->header("Authorization")]);',
            'Log::info("event", ["email" => $user->email]);',
            'Log::error($request->all());',
            'Log::warning("token: $api_token");',
        ] as $unsafe) {
            $this->assertSame(1, preg_match($this->patterns()[0], $this->withoutEventLabels($unsafe)));
        }
    }
}
