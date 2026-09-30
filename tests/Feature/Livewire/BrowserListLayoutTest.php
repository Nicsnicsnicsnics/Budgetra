<?php
namespace Tests\Feature\Livewire;

use App\Livewire\Traveler\AttractionBrowser;
use App\Livewire\Traveler\DestinationBrowser;
use App\Models\Attraction;
use App\Models\Destination;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Attractions and Destinations browse pages, as lists.
 *
 * Both used to render a card grid, `repeat(auto-fill, minmax(260px, 1fr))`,
 * where most of each card's height was a 4:3 photo — and most rows have no
 * photo, so the pages were largely "Photo coming soon" placeholders. They are
 * now one row per result.
 *
 * Neither component had a single test before this. The filtering was never
 * covered, so these pin that down as well as the layout: the conversion had to
 * leave search and the dropdowns working, and nothing would have said so.
 */
class BrowserListLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function attraction(array $attrs = []): Attraction
    {
        return Attraction::create(array_merge([
            'name'        => 'Kawasan Falls',
            'destination' => 'Cebu',
            'category'    => 'Nature',
            'description' => 'A waterfall.',
            'rating'      => 4.5,
        ], $attrs));
    }

    private function browser()
    {
        return Livewire::actingAs(User::factory()->create())->test(AttractionBrowser::class);
    }

    // ── Attractions ─────────────────────────────────────────────────

    public function test_results_render_as_rows_not_a_card_grid(): void
    {
        $this->attraction();

        $this->browser()
            ->assertSee('attr-list', false)
            ->assertSee('attr-row', false)
            // The grid is gone, not merely hidden.
            ->assertDontSee('attr-grid', false)
            ->assertDontSee('class="attr-card"', false);
    }

    public function test_a_row_carries_the_name_place_category_and_rating(): void
    {
        $this->attraction(['name' => 'Kawasan Falls', 'destination' => 'Cebu', 'rating' => 4.5]);

        $html = $this->browser()->html();

        $this->assertStringContainsString('Kawasan Falls', $html);
        $this->assertStringContainsString('Cebu', $html);
        $this->assertStringContainsString('Nature', $html);
        $this->assertStringContainsString('4.5', $html);
    }

    public function test_an_attraction_with_no_photo_still_gets_a_row(): void
    {
        // The common case by a wide margin — most attractions have no image,
        // and the placeholder must not disappear along with the tall card.
        $this->attraction(['name' => 'Bantay Bell Tower', 'image' => null]);

        $this->browser()
            ->assertSee('Bantay Bell Tower')
            ->assertSee('attr-row-noimg', false)
            ->assertSee('Photo coming soon');
    }

    public function test_search_still_filters_the_rows(): void
    {
        $this->attraction(['name' => 'Kawasan Falls']);
        $this->attraction(['name' => 'Chocolate Hills', 'destination' => 'Bohol']);

        $this->browser()
            ->set('search', 'Kawasan')
            ->assertSee('Kawasan Falls')
            ->assertDontSee('Chocolate Hills');
    }

    public function test_the_destination_dropdown_still_filters_the_rows(): void
    {
        $this->attraction(['name' => 'Kawasan Falls', 'destination' => 'Cebu']);
        $this->attraction(['name' => 'Chocolate Hills', 'destination' => 'Bohol']);

        $this->browser()
            ->set('destination', 'Bohol')
            ->assertSee('Chocolate Hills')
            ->assertDontSee('Kawasan Falls');
    }

    public function test_no_matches_shows_the_empty_state_not_an_empty_list(): void
    {
        $this->attraction(['name' => 'Kawasan Falls']);

        // Matched on the container element, not the bare class name: the
        // stylesheet is emitted on every render and defines .attr-list whether
        // or not any rows exist.
        $this->browser()
            ->set('search', 'nothing matches this')
            ->assertSee('No attractions found')
            ->assertDontSee('<div class="attr-list">', false);
    }

    public function test_the_rows_collapse_on_a_narrow_screen(): void
    {
        // The grid got this free from auto-fill; a fixed three-column row does
        // not, so the breakpoint is load-bearing and easy to drop in a later
        // edit without noticing.
        $this->attraction();

        $this->browser()->assertSee('@media (max-width: 560px)', false);
    }

    // ── Destinations, the same page with a country chip ─────────────

    public function test_destinations_render_as_rows_too(): void
    {
        // The two pages are maintained as a pair and read as one component;
        // converting only one would visibly break that.
        Destination::create(['name' => 'Siquijor', 'country' => 'Philippines']);

        Livewire::actingAs(User::factory()->create())->test(DestinationBrowser::class)
            ->assertSee('dst-list', false)
            ->assertSee('dst-row', false)
            ->assertDontSee('dst-grid', false)
            ->assertSee('Siquijor')
            ->assertSee('Philippines');
    }

    public function test_a_destination_search_still_filters(): void
    {
        Destination::create(['name' => 'Siquijor', 'country' => 'Philippines']);
        Destination::create(['name' => 'Tokyo', 'country' => 'Japan']);

        Livewire::actingAs(User::factory()->create())->test(DestinationBrowser::class)
            ->set('search', 'Tokyo')
            ->assertSee('Tokyo')
            ->assertDontSee('Siquijor');
    }
}
