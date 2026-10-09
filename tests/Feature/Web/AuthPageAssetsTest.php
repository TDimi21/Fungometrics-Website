<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\HtmlString;
use Tests\TestCase;

class AuthPageAssetsTest extends TestCase
{
    public function test_login_uses_the_configured_production_asset_entries(): void
    {
        // The legacy Sass entry is not built by vite.config.js. Requesting it
        // throws a manifest exception when an auth page is refreshed in production.
        Vite::shouldReceive('__invoke')
            ->once()
            ->with(['resources/css/app.css', 'resources/js/app.js'])
            ->andReturn(new HtmlString('<script data-test="auth-assets"></script>'));

        $this->get('/login')
            ->assertOk()
            ->assertViewIs('auth.login')
            ->assertSee('data-test="auth-assets"', false);
    }
}
