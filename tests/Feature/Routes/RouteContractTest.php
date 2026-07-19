<?php

declare(strict_types=1);

namespace Tests\Feature\Routes;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RouteContractTest extends TestCase
{
    public function test_method_and_uri_combinations_are_unique(): void
    {
        $seen = [];
        $duplicates = [];

        foreach (Route::getRoutes() as $route) {
            foreach ($route->methods() as $method) {
                if ('HEAD' === $method) {
                    continue;
                }

                $key = $method.' '.$route->uri();
                if (array_key_exists($key, $seen)) {
                    $duplicates[] = $key;
                }
                $seen[$key] = true;
            }
        }

        $this->assertSame([], array_values(array_unique($duplicates)));
    }

    public function test_opcache_reset_is_not_exposed_over_http(): void
    {
        $uris = collect(Route::getRoutes())->map(fn ($route) => $route->uri());

        $this->assertFalse($uris->contains('api/opcache-clear'));
    }
}
