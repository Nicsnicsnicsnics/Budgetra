<?php
namespace Tests\Feature\UI;

use App\Models\Trip;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The kebab menu on a trip card reads on both themes.
 *
 * Every colour in it used to be inline and written for the light theme. The
 * panel took --bg-white, which on nightflight is the colour of the card
 * underneath it, so the menu vanished into the card apart from a hairline.
 * Delete Trip was a hardcoded #ba1a1a on a #fff5f5 hover — a pale pink slab
 * on a dark card.
 */
class CardMenuThemingTest extends TestCase
{
    use RefreshDatabase;

    private function css(): string
    {
        return file_get_contents(public_path('css/style.css'));
    }

    public function test_the_menu_does_not_sit_on_the_same_colour_as_the_card(): void
    {
        // --bg-white is the card. A popover painted in it is invisible.
        $this->assertMatchesRegularExpression(
            '/\.card-menu \{[^}]*background: var\(--surface-raised\);/s',
            $this->css()
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.card-menu \{[^}]*background: var\(--bg-white\);/s',
            $this->css()
        );
    }

    public function test_the_dark_theme_lifts_the_surface_rather_than_relying_on_shadow(): void
    {
        // A shadow separates dark from dark not at all, so the surface itself
        // has to be lighter than the card it floats over.
        $css = $this->css();

        preg_match('/\[data-theme="nightflight"\] \{(.*?)\n\}/s', $css, $m);
        $this->assertNotEmpty($m, 'the nightflight block moved');

        preg_match('/--surface-raised: (#[0-9A-Fa-f]{6});/', $m[1], $raised);
        preg_match('/--bg-white: (#[0-9A-Fa-f]{6});/', $m[1], $card);
        $this->assertNotEmpty($raised, 'nightflight has no --surface-raised');
        $this->assertNotEmpty($card);

        $lum = fn ($hex) => array_sum(sscanf($hex, '#%2x%2x%2x'));
        $this->assertGreaterThan(
            $lum($card[1]),
            $lum($raised[1]),
            'the raised surface must be lighter than the card it sits on'
        );
    }

    public function test_the_menu_casts_a_shadow(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.card-menu \{[^}]*box-shadow: var\(--shadow-lg\);/s',
            $this->css()
        );
    }

    public function test_both_themes_define_every_token_the_menu_uses(): void
    {
        // A token missing from one theme resolves to nothing, and the rule is
        // dropped — silently, and only on that theme.
        $css = $this->css();

        foreach (['original', 'nightflight'] as $theme) {
            preg_match('/\[data-theme="' . $theme . '"\] \{(.*?)\n\}/s', $css, $m);
            $this->assertNotEmpty($m, "the {$theme} block moved");

            foreach (['--surface-raised', '--menu-hover', '--menu-hover-danger'] as $token) {
                $this->assertStringContainsString(
                    $token . ':',
                    $m[1],
                    "{$theme} is missing {$token}"
                );
            }
        }
    }

    public function test_the_delete_row_uses_the_themed_danger_colour(): void
    {
        // --danger is already tuned per theme (#DC2626 light, #F87171 dark,
        // the latter specifically because the darker red fails contrast on
        // that surface). A hardcoded red throws that away.
        $this->assertMatchesRegularExpression(
            '/\.card-menu-danger, \.card-menu-danger i \{ color: var\(--danger\); \}/',
            $this->css()
        );
    }

    public function test_no_hardcoded_light_colours_remain_in_the_card_menu(): void
    {
        $template = file_get_contents(
            resource_path('views/livewire/traveler/saved-trips.blade.php')
        );

        $this->assertStringNotContainsString('#fff5f5', $template);
        $this->assertStringNotContainsString('#ba1a1a', $template);
    }

    public function test_the_menu_still_renders_both_actions(): void
    {
        // The rewrite dropped a lot of inline markup; the buttons and their
        // wire actions have to have survived it.
        $user = User::factory()->create();
        UserProfile::create(['user_id' => $user->id]);
        $trip = Trip::factory()->create([
            'user_id'    => $user->id,
            'status'     => 'upcoming',
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date'   => now()->addDays(14)->toDateString(),
        ]);

        $html = $this->actingAs($user)->get('/saved-trips')->getContent();

        $this->assertStringContainsString('class="card-menu"', $html);
        $this->assertStringContainsString('card-menu-danger', $html);
        $this->assertStringContainsString("openEditName({$trip->id})", $html);
        $this->assertStringContainsString("confirmDelete({$trip->id})", $html);
        $this->assertStringContainsString('Edit Trip', $html);
        $this->assertStringContainsString('Delete Trip', $html);
    }

    public function test_the_divider_falls_between_items_not_after_the_last(): void
    {
        // The old markup put border-bottom on the first button, which meant
        // adding a third item would have left the second undivided.
        $css = $this->css();

        $this->assertStringContainsString(
            '.card-menu button + button { border-top: 1px solid var(--border); }',
            $css
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.card-menu button \{[^}]*border-bottom:/s',
            $css
        );
    }
}
