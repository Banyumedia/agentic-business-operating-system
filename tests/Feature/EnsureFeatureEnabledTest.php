<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureFeatureEnabled;
use App\Services\DynamicMenuRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EnsureFeatureEnabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_aborts_when_module_is_unknown(): void
    {
        $request = Request::create('/app/unknown-module');
        $request->setRouteResolver(function () {
            return new class
            {
                public function parameter($key, $default = null)
                {
                    return 'unknown-module';
                }
            };
        });

        $registry = $this->createMock(DynamicMenuRegistry::class);
        $registry->method('hasModule')->willReturn(false);

        $middleware = new EnsureFeatureEnabled($registry);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('');

        $middleware->handle($request, fn () => response('OK'));
    }

    public function test_aborts_when_module_is_disabled(): void
    {
        $request = Request::create('/app/dashboard');
        $request->setRouteResolver(function () {
            return new class
            {
                public function parameter($key, $default = null)
                {
                    return 'dashboard';
                }
            };
        });

        $registry = $this->createMock(DynamicMenuRegistry::class);
        $registry->method('hasModule')->willReturn(true);
        $registry->method('hasPath')->willReturn(true);
        $registry->method('isModuleVisible')->willReturn(false); // Disabled

        $middleware = new EnsureFeatureEnabled($registry);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('');

        $middleware->handle($request, fn () => response('OK'));
    }

    public function test_allows_when_module_is_enabled(): void
    {
        $request = Request::create('/app/dashboard');
        $request->setRouteResolver(function () {
            return new class
            {
                public function parameter($key, $default = null)
                {
                    return 'dashboard';
                }
            };
        });

        $registry = $this->createMock(DynamicMenuRegistry::class);
        $registry->method('hasModule')->willReturn(true);
        $registry->method('hasPath')->willReturn(true);
        $registry->method('isModuleVisible')->willReturn(true); // Enabled
        $registry->method('routeDefinition')->willReturn([]); // Mock screen definition

        $middleware = new EnsureFeatureEnabled($registry);

        $response = $middleware->handle($request, fn () => response('OK'));

        $this->assertEquals(200, $response->getStatusCode());
    }
}
