<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RouteServiceProviderRateLimitTest extends TestCase
{
    public function test_authenticated_api_and_session_writes_use_separate_buckets(): void
    {
        $user = User::factory()->make(['id' => 'rate-limit-user']);
        $request = Request::create('/api/training', 'POST');
        $request->setUserResolver(fn () => $user);

        $apiLimit = RateLimiter::limiter('api')($request);
        $writeLimit = RateLimiter::limiter('session-write')($request);

        $this->assertSame(300, $apiLimit->maxAttempts);
        $this->assertSame('api:user:rate-limit-user', $apiLimit->key);
        $this->assertSame(120, $writeLimit->maxAttempts);
        $this->assertSame('session-write:user:rate-limit-user', $writeLimit->key);
    }

    public function test_guest_api_limit_remains_restricted_by_ip(): void
    {
        $request = Request::create('/api/login', 'POST', server: ['REMOTE_ADDR' => '203.0.113.10']);

        $limit = RateLimiter::limiter('api')($request);

        $this->assertSame(60, $limit->maxAttempts);
        $this->assertSame('api:ip:203.0.113.10', $limit->key);
    }
}
