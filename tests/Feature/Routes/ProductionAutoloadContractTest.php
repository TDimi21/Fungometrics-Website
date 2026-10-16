<?php

declare(strict_types=1);

namespace Tests\Feature\Routes;

use Illuminate\Support\Facades\Route;
use ReflectionClass;
use Tests\TestCase;

class ProductionAutoloadContractTest extends TestCase
{
    public function test_warned_classes_load_from_canonical_files_and_preserve_route_bindings(): void
    {
        $contract = json_decode(file_get_contents(base_path('tests/Fixtures/autoload/production-warning-contract.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach ($contract as $class => $expected) {
            $this->assertTrue(class_exists($class), $class);
            $this->assertSame(realpath(base_path($expected['path'])), (new ReflectionClass($class))->getFileName(), $class);

            $actual = [];
            foreach (Route::getRoutes() as $route) {
                if (explode('@', $route->getActionName())[0] === $class) {
                    $actual[] = ['methods' => $route->methods(), 'uri' => $route->uri(), 'action' => $route->getActionName()];
                    $method = explode('@', $route->getActionName())[1] ?? '__invoke';
                    $this->assertTrue((new ReflectionClass($class))->getMethod($method)->isPublic(), $class);
                }
            }
            $this->assertEqualsCanonicalizing($expected['routes'], $actual, $class);
        }
    }
}
