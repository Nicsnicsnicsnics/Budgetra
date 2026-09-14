<?php
namespace Tests\Feature\UI;

use Tests\TestCase;

/**
 * A required field that is still empty says so while you're in it.
 *
 * Clicking any field on Register gave the same brand-coloured focus ring
 * whether or not it still needed filling in, so nothing marked a field as
 * required until the server rejected the submit. :invalid carries it: a
 * required field matches while empty and stops matching once there is
 * something valid in it, so the ring returns to normal as you type.
 */
class AuthRequiredFieldTest extends TestCase
{
    private function css(): string
    {
        return file_get_contents(public_path('css/style.css'));
    }

    public function test_an_empty_required_field_turns_red_while_focused(): void
    {
        $css = $this->css();

        $this->assertStringContainsString(
            '.auth-wrapper .form-control:required:focus:invalid {',
            $css
        );
        // The same red as the error text under the field, not a second one.
        $this->assertMatchesRegularExpression(
            '/\.auth-wrapper \.form-control:required:focus:invalid \{\s*border-color: var\(--danger\);/',
            $css
        );
    }

    public function test_it_does_not_reach_the_rest_of_the_app(): void
    {
        // Every form in Budgetra uses .form-control, and turning all of their
        // required fields red on focus is a separate decision.
        $this->assertStringNotContainsString(
            "\n.form-control:required:focus:invalid",
            $this->css()
        );
    }

    public function test_the_register_fields_it_applies_to_are_marked_required(): void
    {
        // The selector is inert without these, so they are part of the
        // behaviour rather than incidental markup.
        $html = $this->get('/register')->assertStatus(200)->getContent();

        foreach (['first_name', 'last_name', 'email', 'password', 'password_confirmation'] as $field) {
            $this->assertMatchesRegularExpression(
                '/name="' . $field . '"[^>]*required|required[^>]*name="' . $field . '"/s',
                $html,
                "{$field} is not marked required"
            );
        }
    }

    public function test_country_is_left_alone_because_it_is_optional(): void
    {
        // RegisterController validates it as nullable, so a red "required"
        // border on it would be telling the traveler something untrue.
        $html = $this->get('/register')->getContent();

        preg_match('/<select name="country".*?>/s', $html, $m);
        $this->assertNotEmpty($m, 'country select not found');
        $this->assertStringNotContainsString('required', $m[0]);
    }

    public function test_login_gets_it_too(): void
    {
        // Same wrapper, same two required fields.
        $this->get('/login')->assertStatus(200)->assertSee('auth-wrapper', false);
    }
}
