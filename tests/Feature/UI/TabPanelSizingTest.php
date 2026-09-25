<?php
namespace Tests\Feature\UI;

use App\Models\Trip;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The browser-tab pages share one panel.
 *
 * Saved Trips, Saving Goals and Multi Trips each carried their own copy of
 * the same inline style, differing only in padding — so the three drifted
 * and none of them matched the notifications card they sit beside in the
 * sidebar. They now share .tab-panel, which carries a floor height so a
 * page holding one trip renders a proper panel rather than a stubby box
 * with its content pinned to the top edge.
 */
class TabPanelSizingTest extends TestCase
{
    use RefreshDatabase;

    private function traveler(): User
    {
        $user = User::factory()->create();
        UserProfile::create(['user_id' => $user->id]);

        return $user;
    }

    public static function pages(): array
    {
        return [
            'saved trips' => ['/saved-trips'],
            'saving goals' => ['/savings'],
            'multi trips' => ['/multi-trips'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function test_the_page_uses_the_shared_panel(string $url): void
    {
        $user = $this->traveler();
        Trip::factory()->create([
            'user_id'    => $user->id,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date'   => now()->addDays(14)->toDateString(),
        ]);

        $this->actingAs($user)->get($url)
            ->assertStatus(200)
            ->assertSee('class="tab-panel"', false);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function test_the_page_no_longer_inlines_its_own_copy(string $url): void
    {
        // The three inline copies are what let the pages drift apart.
        $user = $this->traveler();

        $this->actingAs($user)->get($url)
            ->assertDontSee('border-radius:0 16px 16px 16px', false);
    }

    public function test_the_panel_has_a_floor_height_and_centres_short_content(): void
    {
        // Both halves matter: min-height alone leaves a lone card stuck to the
        // top of a tall box, and centring alone does nothing to a box that
        // already hugs its content.
        $css = file_get_contents(public_path('css/style.css'));

        $this->assertMatchesRegularExpression(
            '/\.tab-panel \{[^}]*min-height: 680px;/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.tab-panel \{[^}]*justify-content: center;/s',
            $css
        );
        // min-height, not height — past the floor the panel must still grow.
        $this->assertDoesNotMatchRegularExpression(
            '/\.tab-panel \{[^}]*[^-]height: 680px;/s',
            $css
        );
    }

    public function test_the_floor_clears_a_row_of_trip_cards(): void
    {
        // A goal card runs ~406px (cover, progress bar, amounts, button). The
        // floor has to beat that by enough to leave visible air above and
        // below it, or the centring has nothing to centre and the panel is
        // back to hugging its content.
        $css = file_get_contents(public_path('css/style.css'));

        preg_match('/\.tab-panel \{[^}]*min-height: (\d+)px;/s', $css, $m);
        $this->assertNotEmpty($m, 'the panel lost its floor height');
        $this->assertGreaterThanOrEqual(
            600,
            (int) $m[1],
            'the floor no longer leaves room below a card row'
        );
    }

    public function test_the_panel_breathes_equally_top_and_bottom(): void
    {
        // Asymmetric padding is what made the old inline copies look
        // bottom-heavy; the shorthand keeps the two edges tied together.
        $css = file_get_contents(public_path('css/style.css'));

        $this->assertMatchesRegularExpression(
            '/\.tab-panel \{[^}]*padding: 44px 24px;/s',
            $css
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.tab-panel \{[^}]*padding-bottom:/s',
            $css
        );
    }

    public function test_the_tab_strip_corner_stays_square(): void
    {
        // The tab strip sits on the top-left corner; rounding it would show a
        // notch under the active tab.
        $css = file_get_contents(public_path('css/style.css'));

        $this->assertMatchesRegularExpression(
            '/\.tab-panel \{[^}]*border-radius: 0 16px 16px 16px;/s',
            $css
        );
    }
}
