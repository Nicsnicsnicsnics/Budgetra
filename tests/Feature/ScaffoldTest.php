<?php
namespace Tests\Feature;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Route-level smoke test: every shell page should render for a signed-in
 * traveller (and admin) rather than erroring.
 *
 * This used to call withoutMiddleware() and skip RefreshDatabase, which meant
 * it ran against a database with no tables at all — the pages died on their
 * first query. Signing a real user in exercises the same routes the way the
 * app actually serves them.
 */
class ScaffoldTest extends TestCase
{
    use RefreshDatabase;

    private function traveler(): User
    {
        $user = User::factory()->create();
        UserProfile::create(['user_id' => $user->id, 'home_city' => 'Manila']);

        return $user;
    }

    public function test_traveler_routes_return_200(): void
    {
        $user = $this->traveler();

        $routes = [
            '/dashboard',
            '/trips',
            '/trips/type',
            '/trips/create',
            '/savings',
            '/itinerary',
            '/attractions',
            '/notifications',
            '/reports',
            '/expenses',
            '/expenses/create',
        ];

        foreach ($routes as $route) {
            $this->actingAs($user)->get($route)->assertStatus(200);
        }
    }

    public function test_admin_routes_return_200(): void
    {
        $admin = User::factory()->admin()->create();

        $routes = [
            '/admin',
            '/admin/users',
            '/admin/destinations',
            '/admin/attractions',
            '/admin/reviews',
            '/admin/config',
            '/admin/reports',
        ];

        foreach ($routes as $route) {
            $this->actingAs($admin)->get($route)->assertStatus(200);
        }
    }

    // "/" is the public landing page for visitors; only a signed-in traveller
    // is sent straight through to the app.
    public function test_root_shows_the_landing_page_to_guests(): void
    {
        $this->get('/')->assertStatus(200);
    }

    public function test_root_redirects_a_signed_in_user_to_the_dashboard(): void
    {
        $this->actingAs($this->traveler())->get('/')->assertRedirect('/dashboard');
    }
}
