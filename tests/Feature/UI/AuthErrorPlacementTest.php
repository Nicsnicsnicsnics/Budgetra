<?php
namespace Tests\Feature\UI;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Where a rejected sign-up or sign-in says what went wrong.
 *
 * Both forms used to print the first error twice: once in a banner under the
 * heading, and again under the field that caused it. Only the second one is
 * kept — but that is only safe while every error a form can raise has a field
 * to print under, which is what most of these tests are really checking.
 */
class AuthErrorPlacementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_taken_email_is_reported_once_under_its_field(): void
    {
        User::factory()->create(['email' => 'travelerbudgetra@gmail.com']);

        $html = $this->followingRedirects()->from('/register')->post('/register', [
            'first_name'            => 'Traveler',
            'last_name'             => 'Budgetra',
            'email'                 => 'travelerbudgetra@gmail.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(200)->getContent();

        $message = 'The email has already been taken.';

        $this->assertSame(1, substr_count($html, $message), 'the banner repeated it');
        $this->assertStringContainsString('<div class="error">' . $message . '</div>', $html);
        $this->assertStringNotContainsString('alert alert-danger', $html);
    }

    public function test_every_field_the_register_form_validates_can_report_for_itself(): void
    {
        // Without a banner, a validated field with no @error of its own fails
        // silently. These are exactly RegisterController's rule keys.
        $template = file_get_contents(resource_path('views/auth/register.blade.php'));

        foreach (['first_name', 'last_name', 'email', 'password', 'country'] as $field) {
            $this->assertStringContainsString(
                "@error('{$field}')",
                $template,
                "{$field} is validated but has nowhere to show its error"
            );
        }
    }

    public function test_several_bad_fields_each_speak_for_themselves(): void
    {
        $html = $this->followingRedirects()->from('/register')->post('/register', [
            'first_name' => '',
            'last_name'  => '',
            'email'      => 'not-an-email',
            'password'   => 'short',
        ])->getContent();

        $this->assertStringContainsString('The first name field is required.', $html);
        $this->assertStringContainsString('The last name field is required.', $html);
        $this->assertStringContainsString('The email field must be a valid email address.', $html);
        $this->assertStringNotContainsString('alert alert-danger', $html);
    }

    public function test_a_failed_sign_in_reports_once_under_the_email_field(): void
    {
        $html = $this->followingRedirects()->from('/login')->post('/login', [
            'email'    => 'nobody@example.com',
            'password' => 'wrong-password',
        ])->getContent();

        $message = 'These credentials do not match our records.';

        $this->assertSame(1, substr_count($html, $message));
        $this->assertStringContainsString('<div class="error">' . $message . '</div>', $html);
        $this->assertStringNotContainsString('alert alert-danger', $html);
    }

    public function test_a_suspended_account_is_reported_the_same_way(): void
    {
        // LoginController attaches this to 'email' too, so it still lands
        // under a field rather than needing a banner of its own.
        User::factory()->create([
            'email'    => 'banned@example.com',
            'password' => 'password123',
            'role'     => 'banned',
        ]);

        $html = $this->followingRedirects()->from('/login')->post('/login', [
            'email'    => 'banned@example.com',
            'password' => 'password123',
        ])->getContent();

        $this->assertStringContainsString('Your account has been suspended.', $html);
        $this->assertStringNotContainsString('alert alert-danger', $html);
    }
}
