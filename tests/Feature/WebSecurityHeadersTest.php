<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class WebSecurityHeadersTest extends TestCase
{
    public function test_web_responses_include_strict_browser_security_headers(): void
    {
        Route::middleware('web')->get('/_security-headers-test', fn () => response('ok'));

        $response = $this->get('/_security-headers-test')->assertOk();
        $policy = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
    }
}
