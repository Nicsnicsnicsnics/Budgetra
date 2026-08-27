<?php
namespace Tests\Feature\UI;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingAuthUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_landing_page(): void
    {
        $this->get('/')->assertStatus(200)->assertSee('Plan Smart');
    }

    public function test_authenticated_user_redirected_from_root(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/')->assertRedirect('/dashboard');
    }

    public function test_login_page_loads(): void
    {
        $this->get('/login')->assertStatus(200)->assertSee('Sign In');
    }

    public function test_register_page_loads(): void
    {
        $this->get('/register')->assertStatus(200)->assertSee('Create Account');
    }

    public function test_dashboard_loads_for_auth_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')->assertStatus(200);
    }
}
