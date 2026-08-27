<?php
namespace Tests\Feature;

use Tests\TestCase;

class ScaffoldTest extends TestCase
{
    public function test_traveler_routes_return_200(): void
    {
        $routes = [
            '/dashboard',
            '/trips',
            '/trips/type',
            '/trips/create',
            '/savings',
            '/itinerary',
            '/attractions',
            '/alerts',
            '/reports',
            '/expenses',
            '/expenses/create',
        ];
        foreach ($routes as $route) {
            $this->withoutMiddleware()
                 ->get($route)
                 ->assertStatus(200);
        }
    }

    public function test_admin_routes_return_200(): void
    {
        $routes = [
            '/admin',
            '/admin/users',
            '/admin/destinations',
            '/admin/attractions',
            '/admin/reviews',
            '/admin/integrations',
            '/admin/reports',
        ];
        foreach ($routes as $route) {
            $this->withoutMiddleware()
                 ->get($route)
                 ->assertStatus(200);
        }
    }

    public function test_root_redirects(): void
    {
        $this->withoutMiddleware()
             ->get('/')
             ->assertRedirect('/dashboard');
    }
}
