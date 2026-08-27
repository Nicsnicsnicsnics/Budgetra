<?php
namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_is_redirected_from_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_unauthenticated_user_is_redirected_from_admin(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_authenticated_traveler_can_access_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'traveler']);
        $this->actingAs($user)->get('/dashboard')->assertStatus(200);
    }

    public function test_traveler_cannot_access_admin_area(): void
    {
        $user = User::factory()->create(['role' => 'traveler']);
        $this->actingAs($user)->get('/admin')->assertStatus(403);
    }

    public function test_admin_can_access_admin_area(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin')->assertStatus(200);
    }
}
