<?php
namespace Tests\Feature\UI;

use Tests\TestCase;

/**
 * A refused sign-in marks the field's border, not its inside.
 *
 * Submitting Login or Register with an empty required field fires the native
 * `invalid` event; auth-form.js swallows the browser's bubble and adds
 * .is-invalid so the page can say it instead. That state used to paint a red
 * wash behind the input and a red halo around it, which made an empty box
 * look filled in. The border carries it now.
 */
class AuthInvalidFieldTest extends TestCase
{
    private function css(): string
    {
        return file_get_contents(public_path('css/style.css'));
    }

    /** The .is-invalid rule body, wherever it is declared. */
    private function invalidRules(): array
    {
        preg_match_all(
            '/\.auth-wrapper \.form-control\.is-invalid[^{]*\{([^}]*)\}/s',
            $this->css(),
            $m
        );

        return $m[1];
    }

    public function test_the_error_state_is_declared_at_all(): void
    {
        // Guards every assertion below from passing because the rule vanished.
        $rules = $this->invalidRules();

        $this->assertNotEmpty($rules, '.is-invalid is no longer styled');
        $this->assertStringContainsString('border-color: var(--danger);', implode("\n", $rules));
    }

    public function test_nothing_is_painted_inside_the_field(): void
    {
        // The wash is the thing that made an empty field read as filled.
        foreach ($this->invalidRules() as $body) {
            $this->assertDoesNotMatchRegularExpression(
                '/(^|;)\s*background(-color)?\s*:/',
                $body,
                'the error state still fills the input'
            );
        }
    }

    public function test_no_halo_is_drawn_around_the_field(): void
    {
        // Border only: a 3px red glow reads as a second, softer border.
        foreach ($this->invalidRules() as $body) {
            $this->assertDoesNotMatchRegularExpression(
                '/(^|;)\s*box-shadow\s*:/',
                $body,
                'the error state still draws a halo'
            );
        }
    }

    public function test_the_color_mix_arm_does_not_put_the_wash_back(): void
    {
        // This is the trap: @supports (color: color-mix(...)) re-declared both
        // properties for every browser that supports it — which is all of
        // them — so removing them from the base rule alone would have changed
        // nothing on screen.
        $css = $this->css();

        $start = strpos($css, '@supports (color: color-mix(in srgb, red, blue))');
        $this->assertNotFalse($start, 'the @supports arm moved');

        $arm = substr($css, $start, 600);
        $this->assertStringNotContainsString('.is-invalid', $arm);
    }

    public function test_the_gutter_icon_is_left_alone_by_an_error(): void
    {
        // The icon sits inside the box, so it is part of the inside.
        $this->assertStringNotContainsString(
            '.auth-wrapper .input-wrapper:has(.form-control.is-invalid) .input-icon',
            $this->css()
        );
    }

    public function test_the_label_still_turns_red(): void
    {
        // Outside the field, and it is what names which field failed — the
        // border alone does not say "Email Address".
        $this->assertMatchesRegularExpression(
            '/\.auth-wrapper \.form-group:has\(\.form-control\.is-invalid\) \.form-label \{\s*color: var\(--danger\);/s',
            $this->css()
        );
    }

    public function test_the_typing_hint_is_border_only_too(): void
    {
        // A separate, lighter state: you clicked into a required field that is
        // still empty. Nothing has been refused yet, so the label stays as it
        // was — but the field itself is marked the same way, a red border and
        // nothing else.
        $css = $this->css();

        $this->assertMatchesRegularExpression(
            '/\.auth-wrapper \.form-control:required:focus:invalid \{\s*border-color: var\(--danger\);\s*box-shadow: none;\s*\}/s',
            $css
        );
        $this->assertStringNotContainsString(
            ':has(.form-control:required:focus:invalid) .input-icon',
            $css
        );
    }

    public function test_both_pages_still_mark_their_fields(): void
    {
        // The CSS is only reachable if the templates keep applying the class.
        // A fresh GET has no errors and so never renders it — the condition
        // that would is what has to be there.
        foreach (['login', 'register'] as $page) {
            $this->get("/{$page}")->assertStatus(200)->assertSee('auth-wrapper', false);

            $template = file_get_contents(resource_path("views/auth/{$page}.blade.php"));
            $this->assertStringContainsString("? 'is-invalid' : ''", $template, $page);
        }
    }

    public function test_a_rejected_submit_really_does_mark_the_field(): void
    {
        // The end of the chain: post nothing, and the page that comes back
        // carries the class the CSS above targets.
        $html = $this->followingRedirects()
            ->from('/login')
            ->post('/login', ['email' => '', 'password' => ''])
            ->assertStatus(200)
            ->getContent();

        $this->assertStringContainsString('is-invalid', $html);
    }

    public function test_the_script_that_applies_the_class_is_loaded(): void
    {
        // Without it the browser shows its own bubble and .is-invalid is never
        // added, so none of the styling above would ever be seen.
        $js = file_get_contents(public_path('js/auth-form.js'));

        $this->assertStringContainsString("classList.add('is-invalid')", $js);
    }
}
