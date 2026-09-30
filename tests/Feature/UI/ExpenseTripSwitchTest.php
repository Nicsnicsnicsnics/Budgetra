<?php
namespace Tests\Feature\UI;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Switching trips on /expenses without a page load.
 *
 * The filters and the transaction list were already swapped in place from a
 * fetch of the same route. The trip picker was not — it sits above that block
 * and its options were plain links, so choosing a trip navigated the whole
 * page. It cannot simply move inside the existing region either: its label and
 * its highlighted row both depend on trip_id, so it has to be re-rendered by
 * the same response rather than left behind stale.
 */
class ExpenseTripSwitchTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, Trip, Trip} */
    private function travelerWithTwoTrips(): array
    {
        $user = User::factory()->create();

        $a = Trip::factory()->create([
            'user_id'     => $user->id,
            'trip_name'   => 'Manila to Tokyo',
            'destination' => 'Tokyo, Japan',
        ]);
        $b = Trip::factory()->create([
            'user_id'     => $user->id,
            'trip_name'   => 'Cebu City to Siargao',
            'destination' => 'Siargao, Philippines',
        ]);

        return [$user, $a, $b];
    }

    public function test_the_trip_picker_is_its_own_swappable_region(): void
    {
        [$user] = $this->travelerWithTwoTrips();

        $this->actingAs($user)->get('/expenses')
            ->assertStatus(200)
            ->assertSee('id="expenses-trip-region"', false)
            ->assertSee('id="expenses-region"', false);
    }

    public function test_both_regions_come_back_on_the_fetch_that_swaps_them(): void
    {
        [$user, , $b] = $this->travelerWithTwoTrips();

        // What ajaxNavigate() actually requests. Both ids have to be in the
        // response or it gives up and hard-navigates instead.
        $html = $this->actingAs($user)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get('/expenses?trip_id=' . $b->id)
            ->assertStatus(200)
            ->getContent();

        $this->assertStringContainsString('id="expenses-trip-region"', $html);
        $this->assertStringContainsString('id="expenses-region"', $html);
    }

    public function test_the_swapped_picker_carries_the_newly_chosen_trip(): void
    {
        [$user, $a, $b] = $this->travelerWithTwoTrips();

        $html = $this->actingAs($user)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get('/expenses?trip_id=' . $b->id)
            ->getContent();

        $region = $this->sliceRegion($html, 'expenses-trip-region');

        // Which row is highlighted is decided server-side from trip_id, so a
        // picker left un-swapped would keep highlighting the trip you left.
        $this->assertStringContainsString('var(--primary-light)', $this->sliceOption($region, $b->id));
        $this->assertStringContainsString('background:transparent', $this->sliceOption($region, $a->id));
        $this->assertStringContainsString($b->destination, $region, 'the chosen trip is named');
    }

    public function test_the_trip_links_stay_same_page_links_so_the_handler_catches_them(): void
    {
        [$user, , $b] = $this->travelerWithTwoTrips();

        $html   = $this->actingAs($user)->get('/expenses')->getContent();
        $region = $this->sliceRegion($html, 'expenses-trip-region');

        // ajaxNavigate's delegated listener only intercepts links whose
        // pathname matches the current page; an absolute URL elsewhere would
        // fall through to a real navigation.
        $this->assertStringContainsString(route('expenses.index') . '?', $region);
        $this->assertStringContainsString('trip_id=' . $b->id, $region);
    }

    public function test_the_click_handler_covers_the_trip_region_too(): void
    {
        [$user] = $this->travelerWithTwoTrips();

        $this->actingAs($user)->get('/expenses')
            ->assertSee("closest('#expenses-region a[href], #expenses-trip-region a[href]')", false);
    }

    public function test_a_single_trip_renders_no_picker_to_swap(): void
    {
        $user = User::factory()->create();
        Trip::factory()->create(['user_id' => $user->id, 'trip_name' => 'Manila to Tokyo']);

        $html = $this->actingAs($user)->get('/expenses')->getContent();

        // The wrapper is still there, but with nothing to choose between it
        // holds a plain label — ajaxNavigate copes either way.
        $this->assertStringContainsString('id="expenses-trip-region"', $html);
        $this->assertStringNotContainsString('trip_id=', $this->sliceRegion($html, 'expenses-trip-region'));
    }

    /** One option's markup, from its link to the end of that link. */
    private function sliceOption(string $region, int $tripId): string
    {
        $start = strpos($region, 'trip_id=' . $tripId . '"');
        $this->assertNotFalse($start, "no option for trip {$tripId}");

        $end = strpos($region, '</a>', $start);

        return substr($region, $start, $end - $start);
    }

    /**
     * The trip picker's markup: everything from its own id up to where the
     * filters region begins. The template's "/#expenses-trip-region" marker
     * is a Blade comment and so never reaches the browser, and the two
     * regions are adjacent siblings, which makes the next id the boundary.
     */
    private function sliceRegion(string $html, string $id): string
    {
        $start = strpos($html, 'id="' . $id . '"');
        $this->assertNotFalse($start, "region {$id} missing");

        $end = strpos($html, 'id="expenses-region"', $start);
        $this->assertNotFalse($end, 'the filters region should follow the picker');

        return substr($html, $start, $end - $start);
    }
}
