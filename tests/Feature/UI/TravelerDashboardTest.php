<?php
namespace Tests\Feature\UI;

use App\Livewire\Traveler\SavedTrips;
use App\Models\Expense;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The traveler dashboard's four KPIs and its active-trip list.
 *
 * The two things worth pinning down here are that "Total Costs" is the
 * planned cost rather than the budget cap (they differ, and only one of
 * them is what the planner priced), and that the trip list is genuinely
 * scoped to what the traveller can still act on.
 */
class TravelerDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function trip(User $user, array $attrs = []): Trip
    {
        return Trip::factory()->create(array_merge([
            'user_id'     => $user->id,
            'trip_name'   => 'Cebu Heritage Run',
            'destination' => 'Cebu, Philippines',
            'start_date'  => now()->addDays(10)->toDateString(),
            'end_date'    => now()->addDays(14)->toDateString(),
        ], $attrs));
    }

    public function test_the_four_kpis_are_on_the_page(): void
    {
        $user = User::factory()->create();
        $this->trip($user);

        $this->actingAs($user)->get('/dashboard')
            ->assertStatus(200)
            ->assertSee('Total Costs')
            ->assertSee('Total Spent')
            ->assertSee('Active Trips')
            ->assertSee('Next Departure');
    }

    public function test_total_costs_prefers_the_planned_cost_over_the_budget(): void
    {
        $user = User::factory()->create();
        $this->trip($user, ['budget_limit' => 20000, 'total_cost' => 31500]);

        // 31,500 is the planned cost; 20,000 is the cap it must NOT show.
        $this->actingAs($user)->get('/dashboard')
            ->assertSee('31,500')
            ->assertDontSee('>' . currency_symbol() . '20,000<', false);
    }

    public function test_total_costs_falls_back_to_the_budget_when_no_cost_was_planned(): void
    {
        $user = User::factory()->create();
        // A hand-created trip never went through the planner, so total_cost is
        // null and its budget is the only cost figure that exists.
        $this->trip($user, ['budget_limit' => 20000, 'total_cost' => null]);
        $this->trip($user, ['budget_limit' => 5000,  'total_cost' => 12000]);

        $this->actingAs($user)->get('/dashboard')->assertSee('32,000');
    }

    public function test_past_trips_stay_out_of_the_active_list(): void
    {
        $user = User::factory()->create();
        $this->trip($user, [
            'trip_name'  => 'Finished Baguio Trip',
            'start_date' => now()->subDays(20)->toDateString(),
            'end_date'   => now()->subDays(15)->toDateString(),
        ]);
        $this->trip($user, ['trip_name' => 'Upcoming Boracay Trip']);

        $res = $this->actingAs($user)->get('/dashboard');

        $res->assertSee('Upcoming Boracay Trip');
        // The past trip still counts toward the totals, but gets no stub.
        $res->assertDontSee('Finished Baguio Trip');
        $res->assertSee('0 ongoing · 1 upcoming', false)->assertStatus(200);
    }

    public function test_next_departure_picks_the_soonest_upcoming_trip(): void
    {
        $user = User::factory()->create();
        $this->trip($user, [
            'trip_name'  => 'Far Off Trip',
            'start_date' => now()->addDays(60)->toDateString(),
            'end_date'   => now()->addDays(65)->toDateString(),
        ]);
        $this->trip($user, [
            'trip_name'  => 'Soonest Trip',
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date'   => now()->addDays(6)->toDateString(),
        ]);

        $this->actingAs($user)->get('/dashboard')->assertSee('3d');
    }

    public function test_an_ongoing_trip_gets_a_day_counter(): void
    {
        $user = User::factory()->create();
        // Day 3 of an 8-day trip that started two days ago.
        $this->trip($user, [
            'start_date' => now()->subDays(2)->toDateString(),
            'end_date'   => now()->addDays(5)->toDateString(),
        ]);

        $this->actingAs($user)->get('/dashboard')
            ->assertSee('D3')
            ->assertSee('of 8')
            ->assertSee('Ongoing');
    }

    public function test_the_stub_carries_the_spend_bar_that_replaced_budget_usage_by_trip(): void
    {
        $user = User::factory()->create();
        $trip = $this->trip($user, ['budget_limit' => 10000]);
        Expense::create([
            'trip_id'      => $trip->id,
            'user_id'      => $user->id,
            'amount'       => 9500,
            'category'     => 'Food',
            'description'  => 'Dinner',
            'expense_date' => now()->toDateString(),
        ]);

        $res = $this->actingAs($user)->get('/dashboard');

        // The standalone section is gone; its bar now lives in the stub, on
        // the same ramp as every other spend meter.
        $res->assertDontSee('Budget Usage by Trip');
        $res->assertSee('trip-stub', false);
        $res->assertSee('var(--meter-bad)', false);   // 95% used
        $res->assertSee('Food', false);               // category chip
    }

    public function test_the_report_button_spins_for_the_length_of_the_real_request(): void
    {
        $user = User::factory()->create();
        $this->trip($user);

        $res = $this->actingAs($user)->get('/dashboard');

        $res->assertSee('budgetraDownloadReport($el.href)', false);
        $res->assertSee('window.budgetraDownloadReport', false);
        $res->assertSee('fa-spinner fa-spin', false);
        // The old version guessed at a fixed duration that could end while the
        // server was still rendering the PDF.
        $res->assertDontSee('setTimeout(() => loading = false, 3000)', false);
        // Spinner only while it runs — no swapped-in status wording.
        $res->assertDontSee('Generating report');
    }

    public function test_the_secondary_button_is_not_outlined_in_the_invisible_border_token(): void
    {
        $user = User::factory()->create();
        $this->trip($user);

        $res = $this->actingAs($user)->get('/dashboard');

        // --border is #2A2F3D on a #1C202B surface under nightflight — 1.2:1,
        // so the outline vanished. --primary is a brand colour on every theme.
        $res->assertSee('.trip-stub-btn.is-ghost         { background: transparent; color: var(--primary); border-color: var(--primary); }', false);
    }

    public function test_view_trip_goes_to_saved_trips(): void
    {
        $user = User::factory()->create();
        $trip = $this->trip($user);

        $res = $this->actingAs($user)->get('/dashboard');

        // The per-trip dashboard is a read-only summary; renaming, sharing,
        // adding members and deleting all live on the Saved Trips card, which
        // is what a traveler actually came here to reach.
        $res->assertSee('href="' . route('saved-trips') . '" class="trip-stub-btn is-ghost"', false);
        $res->assertSee('View Trip');
        // And the old target is genuinely gone, not merely joined by a second
        // link — the per-trip dashboard was the only thing this button reached.
        $res->assertDontSee(route('trips.dashboard', $trip), false);
    }

    public function test_the_stub_uses_the_name_the_traveller_gave_the_trip(): void
    {
        $user = User::factory()->create();
        $this->trip($user, ['trip_name' => 'Taipei With The Cousins', 'destination' => 'Taipei']);

        // Renaming happens in Saved Trips, so a name set there wins here too.
        $this->actingAs($user)->get('/dashboard')->assertSee('Taipei With The Cousins');
    }

    public function test_an_unnamed_trip_falls_back_to_its_destination(): void
    {
        $user = User::factory()->create();
        $this->trip($user, ['trip_name' => null, 'destination' => 'Taipei']);

        // Asserted on the title element itself: the location line below it
        // still says "Taipei, Taiwan", which is the point — the country moved
        // off the heading, it did not disappear from the card.
        $html = $this->actingAs($user)->get('/dashboard')->getContent();
        preg_match('/line-height:1\.3;margin-bottom:3px;">\s*([^<]+)/', $html, $m);

        $this->assertSame('Taipei', trim($m[1] ?? ''));
        $this->assertStringContainsString('Taipei, Taiwan', $html);
    }

    public function test_amounts_stay_in_pesos_when_no_rate_is_reachable(): void
    {
        config(['services.currency_converter.key' => '']);

        $user = User::factory()->create();
        $this->trip($user, [
            'budget_limit'         => 175000,
            'destination_currency' => 'TWD',
        ]);

        // No key means no live rate, and an unconvertible trip must never be
        // labelled in a currency it was not converted into.
        $res = $this->actingAs($user)->get('/dashboard');
        $res->assertSee('₱175,000');
        $res->assertDontSee('TWD 175,000');
    }

    public function test_the_stub_converts_exactly_as_a_saved_trips_card_does(): void
    {
        $user = User::factory()->create();
        $trip = $this->trip($user, [
            'budget_limit'         => 175000,
            'destination_currency' => 'TWD',
        ]);

        // Both views go through the same helper, so agreeing here is what
        // stops the dashboard and Saved Trips from drifting apart.
        $trip->setAttribute('status', 'upcoming');
        $trip->setAttribute('display_currency_code', 'TWD');
        $trip->setAttribute('display_rate', 1.968);

        $this->assertSame('TWD 88,923', trip_amount($trip, 175000));
        $this->assertSame(
            trip_amount($trip, 175000),
            (new \App\Livewire\Traveler\SavedTrips())->displayAmount($trip, 175000)
        );

        // Without a rate both fall back to pesos rather than guessing one.
        $trip->setAttribute('display_rate', null);
        $this->assertSame('₱175,000', trip_amount($trip, 175000));
    }

    public function test_next_departure_is_an_em_dash_even_while_a_trip_is_under_way(): void
    {
        $user = User::factory()->create();
        // Running right now, with nothing booked after it.
        $this->trip($user, [
            'trip_name'  => 'Ongoing Cebu Trip',
            'start_date' => now()->subDays(2)->toDateString(),
            'end_date'   => now()->addDays(2)->toDateString(),
        ]);

        $res = $this->actingAs($user)->get('/dashboard');

        // The card answers "what's next", and a trip already under way is not
        // an answer to that — Active Trips next door reports the ongoing one.
        $res->assertDontSee('In progress');
        $res->assertSee('>—</div>', false);
        $res->assertSee('No upcoming trips booked');
        $res->assertSee('1 ongoing · 0 upcoming', false);
    }

    public function test_the_scroll_container_cannot_shrink_below_its_content(): void
    {
        $css = file_get_contents(public_path('css/style.css'));

        // `flex: 1` here meant shrink factor 1, and the explicit min-height
        // replaced the flex item's default `min-height: auto`. A page taller
        // than the viewport was squashed back to 100%, its content spilled out
        // of the box, and this rule's own padding-bottom sat above that spill
        // — so scrolling to the end of the dashboard left no gap at all.
        $this->assertStringContainsString(
            '.dash-content { padding: 28px 32px; flex: 1 0 auto;',
            $css
        );
        $this->assertStringNotContainsString(
            '.dash-content { padding: 28px 32px; flex: 1;',
            $css
        );
    }

    public function test_a_status_the_traveller_set_is_not_overruled_by_the_dates(): void
    {
        $user = User::factory()->create();
        // Dates say upcoming; the traveller marked it Ongoing from Saved Trips.
        // The dashboard used to recompute from the dates and disagree.
        $this->trip($user, [
            'trip_name'  => 'Marked Ongoing',
            'status'     => 'active',
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date'   => now()->addDays(14)->toDateString(),
        ]);

        $this->actingAs($user)->get('/dashboard')
            ->assertSee('Ongoing')
            ->assertDontSee('>Upcoming<', false);
    }

    public function test_the_active_list_holds_exactly_what_saved_trips_calls_active(): void
    {
        $user = User::factory()->create();

        $this->trip($user, ['trip_name' => 'Upcoming one']);
        $this->trip($user, [
            'trip_name'  => 'Ongoing one',
            'start_date' => now()->subDays(2)->toDateString(),
            'end_date'   => now()->addDays(2)->toDateString(),
        ]);
        $this->trip($user, [
            'trip_name'  => 'Finished one',
            'start_date' => now()->subDays(20)->toDateString(),
            'end_date'   => now()->subDays(15)->toDateString(),
        ]);
        $this->trip($user, ['trip_name' => 'Draft one', 'status' => 'draft']);
        $this->trip($user, ['trip_name' => 'Marked ongoing', 'status' => 'active']);

        $dashboard = $this->actingAs($user)->get('/dashboard')
            ->viewData('activeTrips')->pluck('id')->sort()->values()->all();

        // Saved Trips' own definition of its Active Trips tab, applied to the
        // trips that page actually renders.
        $savedTrips = Livewire::actingAs($user)->test(SavedTrips::class)
            ->viewData('trips')
            ->whereNotIn('status', ['past', 'draft'])
            ->pluck('id')->sort()->values()->all();

        $this->assertNotEmpty($dashboard);
        $this->assertSame($savedTrips, $dashboard);
    }
}
