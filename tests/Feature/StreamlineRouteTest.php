<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class StreamlineRouteTest extends TestCase
{
    public function test_no_conventional_shbackend_api_routes_are_registered(): void
    {
        Artisan::call('route:list', ['--json' => true]);

        $routes = collect(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR));
        $apiRoutes = $routes->filter(fn (array $route): bool => str_starts_with($route['uri'], 'api/'));

        $this->assertNotEmpty($apiRoutes);
        $this->assertTrue($apiRoutes->every(
            fn (array $route): bool => str_starts_with($route['uri'], 'api/streamline'),
        ));
        $this->assertFalse($routes->contains(
            fn (array $route): bool => str_starts_with($route['uri'], 'api/auth/'),
        ));
    }
}
