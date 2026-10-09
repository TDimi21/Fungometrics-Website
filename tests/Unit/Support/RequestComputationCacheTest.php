<?php

namespace Tests\Unit\Support;

use App\Support\RequestComputationCache;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class RequestComputationCacheTest extends TestCase
{
    public function test_reuses_calculations_but_isolates_keys_requests_and_writes(): void
    {
        $original = Container::getInstance();
        $container = new Container();
        Container::setInstance($container);
        $calls = 0;
        $compute = function () use (&$calls) { return ['generation' => ++$calls]; };
        try {
            $request = Request::create('/report', 'GET');
            $request->attributes->set('_fmtrx_reuse_intelligence', true);
            $container->instance('request', $request);
            $this->assertSame(['generation' => 1], RequestComputationCache::remember('team-a:60', $compute));
            $this->assertSame(['generation' => 1], RequestComputationCache::remember('team-a:60', $compute));
            $this->assertSame(['generation' => 2], RequestComputationCache::remember('team-b:60', $compute));
            $this->assertSame(['generation' => 3], RequestComputationCache::remember('team-a:365', $compute));
            $next = Request::create('/report', 'GET');
            $next->attributes->set('_fmtrx_reuse_intelligence', true);
            $container->instance('request', $next);
            $this->assertSame(['generation' => 4], RequestComputationCache::remember('team-a:60', $compute));
            $next->setMethod('POST');
            $this->assertSame(['generation' => 5], RequestComputationCache::remember('team-a:60', $compute));
            $this->assertSame(['generation' => 6], RequestComputationCache::remember('team-a:60', $compute));
            $next->setMethod('GET');
            $next->attributes->remove('_fmtrx_reuse_intelligence');
            $this->assertSame(['generation' => 7], RequestComputationCache::remember('team-a:60', $compute));
        } finally {
            Container::setInstance($original);
        }
    }
}
