<?php

declare(strict_types=1);

namespace App\Services\Access;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

class EntitlementCoverageRegistry
{
    /** @return Collection<int, array<string, mixed>> */
    public function all(): Collection
    {
        $coverage = config('entitlement_coverage.entitlements', []);
        $plans = config('access.plans', []);

        return collect(config('entitlements.items', []))->map(function (array $item, string $key) use ($coverage, $plans): array {
            $defaults = collect($plans)
                ->filter(fn (array $plan): bool => in_array($key, $plan['entitlements'] ?? [], true))
                ->keys()
                ->values()
                ->all();

            return $item + [
                'plan_defaults' => $defaults,
                'coverage' => $coverage[$key] ?? [
                    'features' => [$item['display_name'] ?? $key],
                    'backend' => [],
                    'web' => [],
                    'mobile' => [],
                    'platforms' => ['backend', 'web', 'mobile'],
                    'enforcement_behavior' => 'deny',
                    'access_type' => 'mutating',
                    'dependencies' => [],
                    'numeric_limits' => [],
                    'implementation_status' => 'not_implemented',
                    'gaps' => ['missing_registry_entry'],
                ],
            ];
        })->values();
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        $items = $this->all();

        return [
            'total' => $items->count(),
            'by_status' => $items->countBy('coverage.implementation_status')->sortKeys()->all(),
            'limits' => config('entitlement_coverage.limits', []),
            'system_capabilities' => config('entitlement_coverage.system_capabilities', []),
            'route_gates' => $this->routeGates()->all(),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function routeGates(): Collection
    {
        return collect(Route::getRoutes())->flatMap(function ($route): array {
            return collect($route->gatherMiddleware())->flatMap(function (string $middleware) use ($route): array {
                $prefix = collect(['plan:', 'scripted.practice:'])
                    ->first(fn (string $candidate): bool => str_starts_with($middleware, $candidate));
                if (null === $prefix) {
                    return [];
                }

                return [[
                    'entitlement' => mb_substr($middleware, mb_strlen($prefix)),
                    'methods' => array_values(array_diff($route->methods(), ['HEAD'])),
                    'uri' => $route->uri(),
                    'controller' => $route->getActionName(),
                    'middleware' => $middleware,
                ]];
            })->all();
        })->sortBy(fn (array $gate): string => $gate['entitlement'].'|'.$gate['uri'])
            ->values();
    }
}
