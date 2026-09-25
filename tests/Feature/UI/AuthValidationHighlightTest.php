<?php
namespace Tests\Feature\UI;

use Tests\TestCase;

/**
 * How the auth forms say a field needs filling in, now that neither the
 * browser's bubble nor a shake does it.
 *
 * Two deliberate weights: a hint while you are in an empty required field,
 * and a heavier error state once a submit has actually been refused. The
 * second one is the whole replacement for "Please fill out this field", so it
 * has to carry the message on its own.
 */
class AuthValidationHighlightTest extends TestCase
{
    private function css(): string
    {
        return file_get_contents(public_path('css/style.css'));
    }

    private function js(): string
    {
        return file_get_contents(public_path('js/auth-form.js'));
    }

    public function test_nothing_shakes_any_more(): void
    {
        $this->assertStringNotContainsString('auth-field-shake', $this->css());
        $this->assertStringNotContainsString('field-shake', $this->js());

        foreach (['/login', '/register'] as $url) {
            $this->assertStringNotContainsString('field-shake', $this->get($url)->getContent());
        }
    }

    public function test_the_hint_is_the_border_and_nothing_else(): void
    {
        $css = $this->css();

        // Clicking into an empty required field is not an error yet, so the
        // label stays as it was — and nothing is drawn inside the box or
        // around it.
        $this->assertMatchesRegularExpression(
            '/\.auth-wrapper \.form-control:required:focus:invalid \{\s*border-color: var\(--danger\);\s*box-shadow: none;\s*\}/',
            $css
        );
        $this->assertDoesNotMatchRegularExpression(
            '/:has\(\.form-control:required:focus:invalid\) \.form-label/',
            $css
        );
        // The gutter icon lives inside the field, so it is left alone too.
        $this->assertStringNotContainsString(
            ':has(.form-control:required:focus:invalid) .input-icon',
            $css
        );
    }

    public function test_neither_weight_lets_the_default_focus_ring_through(): void
    {
        // This is why box-shadow:none is written out rather than simply
        // omitted. .form-control:focus puts a 3px brown ring on every focused
        // field in the app; both auth states out-specify it, so dropping the
        // declaration would leave a red border wearing a brown halo.
        $css = $this->css();

        $this->assertStringContainsString(
            '.form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px',
            $css,
            'the default focus ring this guards against has moved'
        );
        $this->assertSame(
            2,
            preg_match_all('/\s{2}box-shadow: none;/', $css),
            'both auth error states must cancel the default focus ring'
        );
    }

    public function test_a_refused_submit_marks_the_border_and_the_label(): void
    {
        $css = $this->css();

        // It used to paint the inside too — a wash behind the input, a halo
        // around it and a red gutter icon — which made an empty field look
        // filled in. The marking now stays outside the box: the border points
        // at the field, the label names it, the message says why.
        $this->assertStringContainsString('.auth-wrapper .form-group:has(.form-control.is-invalid) .form-label', $css);
        $this->assertMatchesRegularExpression(
            '/\.auth-wrapper \.form-control\.is-invalid,\s*\.auth-wrapper \.form-control\.is-invalid:focus \{\s*border-color: var\(--danger\);[^}]*box-shadow: none;\s*\}/s',
            $css
        );
        // No wash, no coloured ring.
        $this->assertDoesNotMatchRegularExpression(
            '/\.auth-wrapper \.form-control\.is-invalid[^{]*\{[^}]*background/s',
            $css
        );
        $this->assertStringNotContainsString(
            '.auth-wrapper .input-wrapper:has(.form-control.is-invalid) .input-icon',
            $css
        );
    }

    public function test_the_two_weights_are_still_distinguishable(): void
    {
        $css = $this->css();

        // Both draw the same red border now, so neither shadow depth nor a
        // tint separates them. What does: the hint needs focus and goes no
        // further than the border; the error holds with or without focus and
        // takes the label with it.
        $this->assertSame(
            0,
            preg_match_all('/box-shadow: 0 0 0 3px rgba\(220, 38, 38, [\d.]+\)/', $css),
            'no red ring should survive on the auth forms'
        );
        $this->assertStringContainsString(':has(.form-control.is-invalid) .form-label', $css);
        $this->assertDoesNotMatchRegularExpression(
            '/:has\(\.form-control:required:focus:invalid\) \.form-label/',
            $css
        );
    }

    public function test_nothing_is_tinted_so_nothing_can_drift_from_the_token(): void
    {
        $css = $this->css();

        // Every red on these forms is var(--danger) straight now — no blends,
        // no hardcoded #DC2626 washes that a theme could leave behind when it
        // moves the token. The @supports arm that derived those tints went
        // with them.
        $this->assertStringNotContainsString('color-mix(in srgb, var(--danger) 14%', $css);
        $this->assertStringNotContainsString('color-mix(in srgb, var(--danger) 5%', $css);
        $this->assertStringNotContainsString('rgba(220, 38, 38,', $css);
    }

    public function test_the_validation_message_is_finally_styled(): void
    {
        // Both templates print messages as .error, which was not defined
        // anywhere in style.css — every one rendered as unstyled body text.
        $this->assertStringContainsString('.auth-wrapper .error {', $this->css());
        $this->assertStringContainsString('color: var(--danger); font-size: 12.5px;', $this->css());

        // Driven through a real refused submit rather than a clean GET: the
        // markup is inside @error, so it only exists once there is something
        // to say.
        $login = $this->from('/login')->followingRedirects()
            ->post('/login', ['email' => 'not-an-email', 'password' => ''])
            ->getContent();
        $this->assertStringContainsString('class="error"', $login, '/login prints no .error');

        $register = $this->from('/register')->followingRedirects()
            ->post('/register', ['first_name' => '', 'email' => 'not-an-email'])
            ->getContent();
        $this->assertStringContainsString('class="error"', $register, '/register prints no .error');
    }

    public function test_the_browsers_bubble_stays_suppressed(): void
    {
        $js = $this->js();

        // preventDefault on `invalid` cancels only the popup; the submit stays
        // blocked. Capture phase because `invalid` does not bubble.
        $this->assertStringContainsString("addEventListener('invalid', function (e) {", $js);
        $this->assertStringContainsString('e.preventDefault();', $js);
        $this->assertStringContainsString('}, true);', $js);
        $this->assertStringContainsString("e.target.classList.add('is-invalid');", $js);
    }

    public function test_typing_clears_it_and_the_cursor_lands_on_the_first_one(): void
    {
        $js = $this->js();

        $this->assertStringContainsString("form.addEventListener('input', function (e) {", $js);
        $this->assertStringContainsString("e.target.classList.remove('is-invalid');", $js);
        // Cancelling the bubble can take the browser's own focus with it.
        $this->assertStringContainsString('e.target.focus();', $js);
    }

    public function test_both_auth_pages_load_the_same_script(): void
    {
        foreach (['/login', '/register'] as $url) {
            $this->get($url)->assertStatus(200)->assertSee('js/auth-form.js', false);
        }
    }

    public function test_login_and_register_hang_the_styling_on_the_same_hooks(): void
    {
        // Every rule here is written against .auth-wrapper, .form-group,
        // .input-wrapper and .form-control. If one page stops nesting them the
        // same way, its highlights quietly stop working while the other's
        // carry on.
        foreach (['/login', '/register'] as $url) {
            $html = $this->get($url)->assertStatus(200)->getContent();

            $this->assertMatchesRegularExpression('/<div class="auth-wrapper">/', $html, $url);
            $this->assertMatchesRegularExpression(
                '/<div class="form-group">\s*<label class="form-label"[^>]*>.*?<div class="input-wrapper">.*?class="form-control[^"]*"[^>]*required/s',
                $html,
                "{$url} does not nest label / wrapper / required control the expected way"
            );
        }
    }

    public function test_the_auth_pages_do_not_serve_a_stale_stylesheet(): void
    {
        // They linked style.css with no version at all while the app layouts
        // versioned theirs, so the browser kept handing back whatever copy it
        // already had and none of this styling showed up without a hard
        // refresh.
        foreach (['/login', '/register'] as $url) {
            $this->assertMatchesRegularExpression(
                '/css\/style\.css\?v=\d+/',
                $this->get($url)->getContent(),
                "{$url} links an unversioned stylesheet"
            );
        }
    }

    public function test_logins_email_field_closes_its_own_wrapper(): void
    {
        // It did not: the error message rendered inside .input-wrapper and
        // .form-group stayed open, so the password group nested inside the
        // email one — which would have put the label highlight on the wrong
        // group entirely.
        $blade = file_get_contents(resource_path('views/auth/login.blade.php'));

        $this->assertSame(
            substr_count($blade, '<div'),
            substr_count($blade, '</div>'),
            'login.blade.php has unbalanced divs'
        );
    }
}
