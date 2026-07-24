<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Tests\TestCase;

class ProductionDebugSafetyTest extends TestCase
{
    public function test_clockwork_is_disabled_by_default(): void
    {
        $this->assertFalse((bool) config('clockwork.enable'));
    }

    public function test_production_page_does_not_install_destructive_error_overlay(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('window.onerror', false);
        $response->assertDontSee('unhandledrejection', false);
    }

    public function test_csp_allows_the_configured_google_font_origins(): void
    {
        $response = $this->get('/');
        $policy = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('https://fonts.googleapis.com', $policy);
        $this->assertStringContainsString('https://fonts.gstatic.com', $policy);
    }
}
