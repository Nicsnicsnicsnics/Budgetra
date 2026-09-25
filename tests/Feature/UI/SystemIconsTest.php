<?php
namespace Tests\Feature\UI;

use App\Models\Trip;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The sidebar and the page-level empty states draw from public/systemicons.
 *
 * The PNGs are black silhouettes on transparency. Dropped in as plain <img>
 * they would be invisible on the sidebar's dark background, so .app-icon uses
 * each one as a mask and fills it with currentColor — which is also what lets
 * the logout row keep its red and the empty-state squares keep their white.
 */
class SystemIconsTest extends TestCase
{
    use RefreshDatabase;

    /** Every icon the sidebar asks for, in nav order. */
    private const SIDEBAR_ICONS = [
        'dashboard', 'trip-planner', 'destinations', 'attractions',
        'saved-trips', 'saving-goals', 'itinerary', 'expenses',
        'notifications', 'multi-trips', 'moments', 'settings', 'logout',
    ];

    private function traveler(): User
    {
        $user = User::factory()->create();
        UserProfile::create(['user_id' => $user->id]);

        return $user;
    }

    public static function sidebarIcons(): array
    {
        return array_map(fn ($n) => [$n], array_combine(self::SIDEBAR_ICONS, self::SIDEBAR_ICONS));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('sidebarIcons')]
    public function test_the_sidebar_asks_for_each_icon(string $name): void
    {
        $html = $this->actingAs($this->traveler())->get('/dashboard')->getContent();

        $this->assertStringContainsString("systemicons/{$name}.png", $html);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('sidebarIcons')]
    public function test_each_icon_is_actually_served(string $name): void
    {
        // The repo has TWO systemicons folders — one at the project root and
        // the one under public/ that is actually web-reachable. New icons
        // landed in the root copy, where asset() cannot see them: the page
        // renders fine, the mask URL 404s, and every icon silently vanishes.
        // Nothing but this check would have caught it.
        $this->assertFileExists(
            public_path("systemicons/{$name}.png"),
            "systemicons/{$name}.png is referenced but not served from public/"
        );
    }

    public static function chevrons(): array
    {
        return ['left' => ['left-chevron'], 'right' => ['right-chevron']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('chevrons')]
    public function test_both_chevrons_are_served(string $name): void
    {
        $this->assertFileExists(public_path("systemicons/{$name}.png"));
    }

    public function test_the_toggle_carries_both_chevrons(): void
    {
        // Both have to be on the element, because CSS — not JS — chooses
        // between them, and it can only choose from what is there.
        $html = $this->actingAs($this->traveler())->get('/dashboard')->getContent();

        $this->assertMatchesRegularExpression(
            '/id="sidebarToggleIcon"\s+style="--chevron-left:url\([^)]*left-chevron\.png[^)]*\);--chevron-right:url\([^)]*right-chevron\.png[^)]*\)"/s',
            $html
        );
        $this->assertStringContainsString('class="app-icon" id="sidebarToggleIcon"', $html);
    }

    public function test_the_chevron_turns_with_the_sidebar(): void
    {
        $css = file_get_contents(public_path('css/style.css'));

        $this->assertStringContainsString(
            '#sidebarToggleIcon { --app-icon: var(--chevron-left); }',
            $css
        );
        $this->assertStringContainsString(
            '.sidebar-collapsed #sidebarToggleIcon { --app-icon: var(--chevron-right); }',
            $css
        );
    }

    public function test_no_script_reassigns_the_toggle_icons_class(): void
    {
        // This is the regression the CSS approach exists to prevent. The
        // direction used to be set by assigning className in three places —
        // twice in the sidebar, once in the collapse_sidebar block — and the
        // third was missed when the icons last changed. Any such assignment
        // now also strips .app-icon and empties the button outright.
        //
        // Matched case-sensitively so the admin sidebar's own
        // adminSidebarToggleIcon, which is still a Font Awesome glyph, does
        // not trip this.
        foreach ([
            resource_path('views/components/sidebar.blade.php'),
            resource_path('views/layouts/app.blade.php'),
        ] as $file) {
            $contents = file_get_contents($file);

            $this->assertDoesNotMatchRegularExpression(
                '/\bicon\.className\s*=/',
                $contents,
                basename($file) . ' still assigns the toggle icon a class'
            );
            $this->assertStringNotContainsString('fa-angle', $contents, basename($file));
        }
    }

    public function test_the_helper_can_name_its_own_custom_property(): void
    {
        // The chevrons need two properties on one element, so the default
        // name has to be overridable — and the default must not change.
        $this->assertStringStartsWith('--app-icon:url(', system_icon('dashboard'));
        $this->assertStringStartsWith('--chevron-left:url(', system_icon('left-chevron', 'chevron-left'));
        $this->assertStringContainsString('left-chevron.png?v=', system_icon('left-chevron', 'chevron-left'));
    }

    public function test_the_mask_is_what_makes_the_icons_visible(): void
    {
        // background-color + mask, not <img>: without the fill the icons are
        // black-on-black in the sidebar, and without contain/no-repeat a
        // non-square PNG tiles.
        $css = file_get_contents(public_path('css/style.css'));

        $this->assertMatchesRegularExpression(
            '/\.app-icon \{[^}]*background-color: currentColor;/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.app-icon \{[^}]*-webkit-mask: var\(--app-icon\) center \/ contain no-repeat;/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.app-icon \{[^}]*[^-]mask: var\(--app-icon\) center \/ contain no-repeat;/s',
            $css
        );
    }

    public function test_the_icons_size_in_em_so_they_swap_with_a_glyph(): void
    {
        // The 18px gutter, the 15px/16px sidebar sizes and the inline 28px on
        // each empty state were all written for a font icon. Sizing in em is
        // what lets those keep working unchanged.
        $css = file_get_contents(public_path('css/style.css'));

        $this->assertMatchesRegularExpression('/\.app-icon \{[^}]*width: 1em; height: 1em;/s', $css);
        $this->assertDoesNotMatchRegularExpression('/\.app-icon \{[^}]*width: \d+px;/s', $css);
    }

    public function test_a_browser_without_mask_support_shows_nothing_not_a_block(): void
    {
        // The @supports arm matters: currentColor with no mask paints a solid
        // square exactly where an icon belongs.
        $css = file_get_contents(public_path('css/style.css'));

        $this->assertStringContainsString(
            '@supports not ((-webkit-mask: none) or (mask: none)) {',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/@supports not \(\(-webkit-mask: none\) or \(mask: none\)\) \{\s*\.app-icon \{ background-color: transparent; \}/s',
            $css
        );
    }

    public function test_the_helper_busts_the_cache_when_an_icon_is_redrawn(): void
    {
        // These are hand-drawn and get redrawn. Without the version the
        // replacement never reaches anyone who has already loaded the page.
        $declaration = system_icon('dashboard');

        $this->assertStringStartsWith('--app-icon:url(', $declaration);
        $this->assertStringContainsString('systemicons/dashboard.png?v=', $declaration);
        $this->assertStringContainsString(
            (string) filemtime(public_path('systemicons/dashboard.png')),
            $declaration
        );
    }

    public function test_the_helper_does_not_invent_a_version_for_a_missing_file(): void
    {
        // filemtime() on a missing path warns and returns false, which would
        // render "?v=" and defeat caching for good.
        $this->assertSame(
            '--app-icon:url(' . asset('systemicons/not-a-real-icon.png') . ')',
            system_icon('not-a-real-icon')
        );
    }

    public function test_the_component_still_draws_a_font_awesome_value(): void
    {
        // The Profile row keeps an fa- value, the chevron and search magnifier
        // are still glyphs, and the component has to pass those through rather
        // than look for a PNG named "fa-regular fa-user-circle".
        $html = $this->blade('<x-nav-icon icon="fa-regular fa-user-circle" />');

        $this->assertStringContainsString('class="fa-regular fa-user-circle"', $html);
        $this->assertStringNotContainsString('app-icon', $html);
        $this->assertStringNotContainsString('systemicons', $html);
    }

    public function test_the_component_keeps_the_callers_own_style(): void
    {
        // Each empty state passes its own font-size and colour; losing them
        // would leave a 1em icon in the middle of a 64px square.
        $html = $this->blade('<x-nav-icon icon="dashboard" style="font-size:28px;color:#fff;" />');

        $this->assertStringContainsString('class="app-icon"', $html);
        $this->assertStringContainsString('--app-icon:url(', $html);
        $this->assertStringContainsString('font-size:28px;color:#fff;', $html);
    }

    public static function emptyStates(): array
    {
        return [
            'dashboard'   => ['/dashboard',   'dashboard'],
            'saving goals'=> ['/savings',     'saving-goals'],
            'expenses'    => ['/expenses',    'expenses'],
            'notifications' => ['/notifications', 'notifications'],
            'saved trips' => ['/saved-trips', 'saved-trips'],
            'multi trips' => ['/multi-trips', 'multi-trips'],
            'itinerary'   => ['/itinerary',   'itinerary'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('emptyStates')]
    public function test_each_empty_state_uses_its_own_icon(string $url, string $icon): void
    {
        // No trips, so every one of these pages renders its empty state.
        $html = $this->actingAs($this->traveler())->get($url)->getContent();

        $this->assertStringContainsString("systemicons/{$icon}.png", $html);
    }

    public function test_an_empty_state_icon_is_painted_white_on_the_primary_square(): void
    {
        // The square is var(--primary); currentColor is what carries the
        // white through the mask, so the inline colour has to survive.
        $html = $this->actingAs($this->traveler())->get('/savings')->getContent();

        $this->assertMatchesRegularExpression(
            '/class="app-icon" style="--app-icon:url\([^)]*saving-goals\.png[^)]*\);font-size:28px;color:#fff;"/',
            $html
        );
    }

    public function test_a_page_with_data_does_not_render_the_empty_icon(): void
    {
        // Guards the pair above from passing on a page that always shows the
        // empty state regardless of what is in the database.
        $user = $this->traveler();
        Trip::factory()->create([
            'user_id'    => $user->id,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date'   => now()->addDays(14)->toDateString(),
        ]);

        $html = $this->actingAs($user)->get('/saved-trips')->getContent();

        // The sidebar's own Saved Trips link uses this very PNG, and the same
        // markup, so the icon name alone proves nothing here. Only the
        // empty-state copy carries the 28px/white inline style.
        $this->assertMatchesRegularExpression(
            '/class="app-icon" style="--app-icon:url\([^)]*saved-trips\.png[^)]*\);font-size:28px;color:#fff;"/',
            $this->actingAs($this->traveler())->get('/saved-trips')->getContent(),
            'a traveler with no trips should see the empty state'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/class="app-icon" style="--app-icon:url\([^)]*saved-trips\.png[^)]*\);font-size:28px;color:#fff;"/',
            $html,
            'the empty state rendered even though the traveler has a trip'
        );
    }
}
