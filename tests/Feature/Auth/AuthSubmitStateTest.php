<?php
namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sign-in and Create Account posted with fetch() instead of navigating.
 *
 * Two things fall out of not navigating. The password boxes keep what was
 * typed in them, which a reload could never do — Laravel keeps passwords out
 * of the flashed input on purpose, so they would come back empty — and the
 * button can hold a spinner for the real duration of the request rather than
 * leaving the browser tab to indicate it.
 *
 * What is testable here is the contract that makes both possible: a refusal
 * has to come back as 422 JSON, because a redirect would replace the page.
 */
class AuthSubmitStateTest extends TestCase
{
    use RefreshDatabase;

    // ── Create Account ──────────────────────────────────────────────

    public function test_a_taken_email_is_refused_without_replacing_the_page(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/register', [
            'first_name'            => 'Traveler',
            'last_name'             => 'Budgetra',
            'email'                 => 'taken@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // A 302 here is the bug: the page would reload and both password
        // boxes would come back empty.
        $response->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_a_good_sign_up_answers_with_where_to_go_next(): void
    {
        $response = $this->postJson('/register', [
            'first_name'            => 'Traveler',
            'last_name'             => 'Budgetra',
            'email'                 => 'new@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(200)->assertJson(['redirect' => route('login')]);
        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
    }

    public function test_a_plain_sign_up_post_still_redirects(): void
    {
        // The form works with JavaScript off, and the old tests describe it.
        $this->post('/register', [
            'first_name'            => 'Traveler',
            'last_name'             => 'Budgetra',
            'email'                 => 'plain@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('login'));
    }

    // ── Sign In ─────────────────────────────────────────────────────

    public function test_bad_credentials_are_refused_without_replacing_the_page(): void
    {
        $response = $this->postJson('/login', [
            'email'    => 'nobody@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email')
            ->assertJsonPath('errors.email.0', 'These credentials do not match our records.');
        $this->assertGuest();
    }

    public function test_a_suspended_account_is_refused_the_same_way_and_stays_signed_out(): void
    {
        User::factory()->create([
            'email'    => 'banned@example.com',
            'password' => 'password123',
            'role'     => 'banned',
        ]);

        $this->postJson('/login', ['email' => 'banned@example.com', 'password' => 'password123'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Your account has been suspended. Contact support if you believe this is a mistake.');

        // attempt() signed them in before the role was checked.
        $this->assertGuest();
    }

    public function test_a_traveler_is_told_to_go_to_their_trips(): void
    {
        User::factory()->create([
            'email'    => 'traveler@example.com',
            'password' => 'password123',
            'role'     => 'traveler',
        ]);

        $this->postJson('/login', ['email' => 'traveler@example.com', 'password' => 'password123'])
            ->assertStatus(200)
            ->assertJson(['redirect' => route('trips.index')]);

        $this->assertAuthenticated();
    }

    public function test_an_admin_is_told_to_go_to_the_admin_dashboard(): void
    {
        User::factory()->create([
            'email'    => 'admin@example.com',
            'password' => 'password123',
            'role'     => 'admin',
        ]);

        $this->postJson('/login', ['email' => 'admin@example.com', 'password' => 'password123'])
            ->assertStatus(200)
            ->assertJson(['redirect' => route('admin.dashboard')]);
    }

    public function test_a_plain_sign_in_post_still_redirects(): void
    {
        User::factory()->create([
            'email'    => 'plain@example.com',
            'password' => 'password123',
            'role'     => 'traveler',
        ]);

        $this->post('/login', ['email' => 'plain@example.com', 'password' => 'password123'])
            ->assertRedirect(route('trips.index'));
    }

    // ── The buttons ─────────────────────────────────────────────────

    public function test_both_buttons_name_what_they_say_while_waiting(): void
    {
        $this->get('/register')
            ->assertSee('data-busy-label="Creating account…"', false)
            ->assertSee('js/auth-form.js', false);

        $this->get('/login')
            ->assertSee('data-busy-label="Signing in…"', false)
            ->assertSee('js/auth-form.js', false);
    }
}
