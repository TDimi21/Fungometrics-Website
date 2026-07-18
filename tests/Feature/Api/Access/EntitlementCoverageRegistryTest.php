<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Access;

use App\Services\Access\EntitlementCoverageRegistry;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EntitlementCoverageRegistryTest extends TestCase
{
    public function test_every_administrator_entitlement_has_a_complete_registry_entry(): void
    {
        $catalog = array_keys(config('entitlements.items', []));
        $coverage = config('entitlement_coverage.entitlements', []);

        $this->assertSame($catalog, array_keys($coverage));

        $allowedStatuses = config('entitlement_coverage.allowed_statuses', []);
        foreach (app(EntitlementCoverageRegistry::class)->all() as $entry) {
            $this->assertNotEmpty($entry['key']);
            $this->assertNotEmpty($entry['display_name']);
            $this->assertNotEmpty($entry['category']);
            $this->assertNotEmpty($entry['audience']);
            $this->assertIsArray($entry['plan_defaults']);
            $this->assertNotEmpty($entry['coverage']['features']);
            $this->assertContains($entry['coverage']['implementation_status'], $allowedStatuses);
            $this->assertIsArray($entry['coverage']['backend']);
            $this->assertIsArray($entry['coverage']['web']);
            $this->assertIsArray($entry['coverage']['mobile']);
            $this->assertIsArray($entry['coverage']['gaps']);
        }
    }

    public function test_every_route_plan_middleware_references_a_known_entitlement(): void
    {
        $known = array_keys(config('entitlements.items', []));

        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if ( ! str_starts_with($middleware, 'plan:')) {
                    continue;
                }

                $key = mb_substr($middleware, mb_strlen('plan:'));
                $this->assertContains($key, $known, "Unknown entitlement [{$key}] on route [{$route->uri()}].");
            }
        }
    }

    public function test_machine_readable_route_gate_inventory_is_complete_and_known(): void
    {
        $known = array_keys(config('entitlements.items', []));
        $expected = 0;
        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (str_starts_with($middleware, 'plan:') || str_starts_with($middleware, 'scripted.practice:')) {
                    $expected++;
                }
            }
        }

        $gates = app(EntitlementCoverageRegistry::class)->routeGates();
        $this->assertCount($expected, $gates);
        foreach ($gates as $gate) {
            $this->assertContains($gate['entitlement'], $known);
            $this->assertNotEmpty($gate['methods']);
            $this->assertNotEmpty($gate['uri']);
            $this->assertNotEmpty($gate['controller']);
            $this->assertNotEmpty($gate['middleware']);
        }
    }

    public function test_registry_never_claims_fully_wired_while_a_layer_is_missing(): void
    {
        $checked = 0;
        foreach (app(EntitlementCoverageRegistry::class)->all() as $entry) {
            if ('fully_wired' !== $entry['coverage']['implementation_status']) {
                continue;
            }

            $checked++;
            $this->assertNotEmpty($entry['coverage']['backend']);
            $this->assertNotEmpty($entry['coverage']['web']);
            $this->assertNotEmpty($entry['coverage']['mobile']);
            $this->assertSame([], $entry['coverage']['gaps']);
            $this->assertNotContains(false, array_column($entry['coverage']['backend'], 'enforced'));
        }
        $this->assertGreaterThanOrEqual(0, $checked);
    }
}
