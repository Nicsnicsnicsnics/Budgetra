<?php
namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_page_loads(): void
    {
        $this->get('/register')->assertStatus(200);
    }

    public function test_user_can_register_with_valid_data(): void
    {
        $response = $this->post('/register', [
            'full_name'             => 'Kent Pielago',
            'email'                 => 'kent@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('users', ['email' => 'kent@example.com', 'role' => 'traveler']);
        $this->assertAuthenticated();
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->post('/register', [
            'full_name'             => 'Someone',
            'email'                 => 'taken@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_register_fails_with_mismatched_passwords(): void
    {
        $response = $this->post('/register', [
            'full_name'             => 'Test User',
            'email'                 => 'test@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'different',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_register_fails_with_short_password(): void
    {
        $response = $this->post('/register', [
            'full_name'             => 'Test User',
            'email'                 => 'test@example.com',
            'password'              => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_authenticated_user_is_redirected_from_register_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/register')->assertRedirect(route('dashboard'));
    }

    // The Country dropdown at registration used to be purely decorative —
    // the controller never read it, so every account ended up with
    // country = null regardless of what was picked. Confirms it's now
    // actually persisted.
    public function test_register_saves_the_selected_country(): void
    {
        $this->post('/register', [
            'first_name'            => 'Kent',
            'last_name'             => 'Pielago',
            'email'                 => 'kent@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'country'               => 'Canada',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'kent@example.com', 'country' => 'Canada']);
    }

    public function test_register_succeeds_without_a_country_selected(): void
    {
        $response = $this->post('/register', [
            'first_name'            => 'Kent',
            'last_name'             => 'Pielago',
            'email'                 => 'kent@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseHas('users', ['email' => 'kent@example.com', 'country' => null]);
    }
}
