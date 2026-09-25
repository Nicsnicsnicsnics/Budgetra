<?php
namespace Tests\Feature\UI;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The paper-plane mark is the favicon and the logo on both auth pages.
 *
 * It replaced budgetra.jpg in the tab and the large budgetraicon-modified.png
 * photo on the sign-in panels. It is a black line drawing on transparency,
 * which is the whole reason .brand-mark exists: every surface it lands on —
 * both auth panels and the sidebar header — is dark.
 */
class BrandIconTest extends TestCase
{
    use RefreshDatabase;

    /** Every page that sets a favicon. */
    public static function pages(): array
    {
        return [
            'login'     => ['/login'],
            'register'  => ['/register'],
            'dashboard' => ['/dashboard'],
        ];
    }

    private function visit(string $url): string
    {
        if ($url === '/dashboard') {
            $user = User::factory()->create();
            UserProfile::create(['user_id' => $user->id]);

            return $this->actingAs($user)->get($url)->assertStatus(200)->getContent();
        }

        return $this->get($url)->assertStatus(200)->getContent();
    }

    public function test_the_icon_is_served(): void
    {
        // It lives in the repo root's systemicons/ as well, which is not
        // web-reachable — only the public/ copy is.
        $this->assertFileExists(public_path('systemicons/budgetraicon.png'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function test_the_tab_icon_is_the_new_mark(string $url): void
    {
        $html = $this->visit($url);

        $this->assertMatchesRegularExpression(
            '/<link rel="icon" type="image\/png" href="[^"]*systemicons\/budgetraicon\.png\?v=\d+"/',
            $html,
            "{$url} does not point its favicon at the new mark"
        );

        // Scoped to the <link>, not the page: the signed-in sidebar still
        // uses budgetra.jpg as its own brand image, which is a different
        // thing and was not part of this change.
        preg_match('/<link rel="icon"[^>]*>/', $html, $link);
        $this->assertNotEmpty($link, "{$url} has no favicon link");
        $this->assertStringNotContainsString('budgetra.jpg', $link[0], $url);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function test_the_declared_type_matches_the_file(string $url): void
    {
        // It was type="image/jpeg" pointing at a .jpg. Swapping the file
        // without the type leaves the browser told one thing and handed
        // another.
        $this->assertStringNotContainsString('type="image/jpeg"', $this->visit($url), $url);
    }

    public function test_the_favicon_can_be_replaced_without_a_hard_refresh(): void
    {
        // Favicons are cached about as aggressively as anything on the web,
        // and this one had no version at all.
        $expected = filemtime(public_path('systemicons/budgetraicon.png'));

        $this->assertStringContainsString(
            "budgetraicon.png?v={$expected}",
            $this->get('/login')->getContent()
        );
    }

    public function test_both_auth_panels_show_the_mark(): void
    {
        foreach (['/login', '/register'] as $url) {
            $html = $this->get($url)->getContent();

            $this->assertStringContainsString('class="brand-mark"', $html, $url);
            $this->assertStringContainsString('alt="Budgetra"', $html, $url);
            // The old photo is gone from both.
            $this->assertStringNotContainsString('budgetraicon-modified.png', $html, $url);
        }
    }

    public function test_the_mark_is_painted_white_for_the_panel_behind_it(): void
    {
        // A black line drawing on a blurred photo under rgba(0,0,0,.25) is
        // barely a shape. brightness(0) flattens it to black whatever the
        // source colour, then invert(1) turns that white — alpha untouched.
        $css = file_get_contents(public_path('css/style.css'));

        $this->assertStringContainsString(
            '.brand-mark { filter: brightness(0) invert(1); }',
            $css
        );
    }

    public function test_the_sidebar_header_shows_the_same_mark(): void
    {
        $user = User::factory()->create();
        UserProfile::create(['user_id' => $user->id]);

        $html = $this->actingAs($user)->get('/dashboard')->assertStatus(200)->getContent();

        // Same file, same white treatment, versioned like everywhere else.
        $this->assertMatchesRegularExpression(
            '/<img src="[^"]*systemicons\/budgetraicon\.png\?v=\d+"\s+alt="Budgetra" class="brand-mark"/',
            $html
        );
        // The photo tile it replaced is gone from this sidebar.
        preg_match('/<div class="sidebar-brand".*?<\/div>/s', $html, $brand);
        $this->assertNotEmpty($brand, 'the sidebar brand block moved');
        $this->assertStringNotContainsString('budgetra.jpg', $brand[0]);
        // A transparent line mark has no tile corners to round.
        $this->assertStringNotContainsString('border-radius', $brand[0]);
    }

    public function test_one_class_serves_every_dark_surface(): void
    {
        // The nav icons drifted into three copies of their own colour rule
        // before being pulled back together; this keeps the brand mark from
        // going the same way.
        $css = file_get_contents(public_path('css/style.css'));

        $this->assertSame(
            1,
            preg_match_all('/filter: brightness\(0\) invert\(1\)/', $css),
            'the white treatment has been copied rather than shared'
        );
    }

    public function test_the_panel_it_sits_on_is_still_dark(): void
    {
        // The white treatment above is only correct while this holds. If the
        // scrim ever goes, a white-on-light mark disappears instead.
        foreach (['/login', '/register'] as $url) {
            $this->assertStringContainsString(
                'background:rgba(0,0,0,.25);',
                $this->get($url)->getContent(),
                "{$url} lost the scrim the white mark relies on"
            );
        }
    }

    public function test_the_mark_is_a_monochrome_cutout(): void
    {
        // If it is ever replaced with a full-colour logo, brightness(0)
        // invert(1) will flatten it to a white silhouette and throw the
        // colour away. This is the tripwire for that.
        $png  = file_get_contents(public_path('systemicons/budgetraicon.png'));
        $meta = unpack('Nwidth/Nheight/Cdepth/Ctype', substr($png, 16, 10));

        // Type 6 is RGBA — it has the alpha the transparent background needs.
        $this->assertSame(6, $meta['type'], 'the mark is no longer RGBA');
        $this->assertSame($meta['width'], $meta['height'], 'the mark is no longer square');
    }
}
