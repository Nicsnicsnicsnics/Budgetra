<?php
namespace Tests\Feature\UI;

use App\Livewire\Traveler\SavedTrips;
use App\Models\Expense;
use App\Models\GroupMember;
use App\Models\Itinerary;
use App\Models\Notification;
use App\Models\SavingsGoal;
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

    /**
     * Only past and draft trips, so the page still renders its stats and
     * charts but the active list has nothing in it.
     */
    private function userWhoseTripsAreAllDone(): User
    {
        $user = User::factory()->create();
        $this->trip($user, [
            'trip_name'  => 'Finished Baguio Trip',
            'start_date' => now()->subDays(20)->toDateString(),
            'end_date'   => now()->subDays(15)->toDateString(),
        ]);

        return $user;
    }

    public function test_no_active_trips_says_so_instead_of_vanishing(): void
    {
        $res = $this->actingAs($this->userWhoseTripsAreAllDone())->get('/dashboard');

        $res->assertStatus(200);
        // The heading used to disappear along with the list, leaving a gap
        // between the KPIs and the charts with nothing to explain it.
        $res->assertSee('Active Trips');
        $res->assertSee('No active trips yet');
        $res->assertSee('Plan a Trip');
        $res->assertSee(route('trips.plan'), false);
    }

    public function test_the_heading_is_not_duplicated_when_trips_are_active(): void
    {
        $user = User::factory()->create();
        $this->trip($user, ['trip_name' => 'Upcoming Boracay Trip']);

        $html = $this->actingAs($user)->get('/dashboard')->getContent();

        $this->assertStringNotContainsString('No active trips yet', $html);
        // The section heading moved out of the @if, so it must appear exactly
        // once and not have been copied into both branches. (The KPI label
        // next to it reads "<i></i> Active Trips" and does not match this.)
        $this->assertSame(1, substr_count($html, '>Active Trips<'));
    }

    public function test_monthly_spending_shows_an_empty_state_rather_than_flat_stubs(): void
    {
        // A trip but no expenses: every one of the six months is zero, which
        // used to draw six 2%-high bars with their amounts hidden.
        $user = User::factory()->create();
        $this->trip($user);

        $html = $this->actingAs($user)->get('/dashboard')->getContent();

        $this->assertStringContainsString('Monthly Spending', $html);
        $this->assertStringContainsString(
            'Log an expense to see how your spending moves month to month.',
            $html
        );
        $this->assertStringNotContainsString('height:2%', $html);
    }

    public function test_the_bars_come_back_as_soon_as_there_is_spending(): void
    {
        $user = User::factory()->create();
        $trip = $this->trip($user);
        Expense::create([
            'trip_id'      => $trip->id,
            'user_id'      => $user->id,
            'amount'       => 1200,
            'category'     => 'Food',
            'description'  => 'Dinner',
            'expense_date' => now()->toDateString(),
        ]);

        $html = $this->actingAs($user)->get('/dashboard')->getContent();

        $this->assertStringNotContainsString(
            'Log an expense to see how your spending moves month to month.',
            $html
        );
        // The current month is the tallest, so it is drawn full height.
        $this->assertStringContainsString('height:100%', $html);
    }

    // ── The panels that turned the page from a pile of totals into ──
    // ── something with activity on it. ──────────────────────────────

    public function test_an_unread_budget_alert_reaches_the_attention_panel(): void
    {
        $user = User::factory()->create();
        $trip = $this->trip($user);

        Notification::create([
            'trip_id' => $trip->id,
            'user_id' => $user->id,
            'type'    => 'budget_alert',
            'message' => 'You have exceeded your overall budget for Cebu.',
            'is_read' => false,
        ]);

        $this->actingAs($user)->get('/dashboard')
            ->assertSee('Needs your attention')
            // The title comes from notification_meta(), not from the row.
            ->assertSee('Budget exceeded')
            ->assertSee('You have exceeded your overall budget for Cebu.');
    }

    public function test_a_read_notification_is_not_still_asking_for_attention(): void
    {
        $user = User::factory()->create();
        $trip = $this->trip($user);

        Notification::create([
            'trip_id' => $trip->id,
            'user_id' => $user->id,
            'type'    => 'budget_alert',
            'message' => 'Already dealt with this one.',
            'is_read' => true,
        ]);

        $this->actingAs($user)->get('/dashboard')
            ->assertDontSee('Already dealt with this one.')
            // Nothing unread and actionable means no panel at all, rather
            // than a panel announcing that nothing needs attention.
            ->assertDontSee('Needs your attention');
    }

    public function test_a_confirmation_notification_does_not_count_as_attention(): void
    {
        // trip_created is a receipt for something the traveller just did.
        // Only the four ATTENTION_TYPES belong on a panel making this claim.
        $user = User::factory()->create();
        $trip = $this->trip($user);

        Notification::create([
            'trip_id' => $trip->id,
            'user_id' => $user->id,
            'type'    => 'trip_created',
            'message' => 'Your trip to Cebu was saved.',
            'is_read' => false,
        ]);

        $this->actingAs($user)->get('/dashboard')
            ->assertDontSee('Needs your attention')
            ->assertDontSee('Your trip to Cebu was saved.');
    }

    public function test_recent_expenses_include_a_trip_the_user_only_belongs_to(): void
    {
        // accessibleTrips() is the whole point: someone added to a group trip
        // sees its spending in the totals above, so the recent list has to
        // agree with them rather than showing only rows they logged.
        $user  = User::factory()->create();
        $owner = User::factory()->create();

        $this->trip($user);
        $shared = $this->trip($owner, ['trip_name' => 'Palawan With Friends']);
        GroupMember::create(['trip_id' => $shared->id, 'user_id' => $user->id]);

        Expense::create([
            'trip_id'      => $shared->id,
            'user_id'      => $owner->id,
            'amount'       => 2450,
            'category'     => 'Food',
            'description'  => 'Seafood grill',
            'expense_date' => now()->toDateString(),
        ]);

        $this->actingAs($user)->get('/dashboard')
            ->assertSee('Recent Expenses')
            ->assertSee('Seafood grill')
            ->assertSee('Palawan With Friends');
    }

    public function test_a_foreign_expense_shows_what_was_actually_paid(): void
    {
        $user = User::factory()->create();
        $trip = $this->trip($user);

        Expense::create([
            'trip_id'         => $trip->id,
            'user_id'         => $user->id,
            'amount'          => 7800,          // the peso ledger figure
            'amount_currency' => 'JPY',
            'amount_original' => 20000,         // what they handed over
            'category'        => 'Food',
            'description'     => 'Ramen in Osaka',
            'expense_date'    => now()->toDateString(),
        ]);

        $this->actingAs($user)->get('/dashboard')
            ->assertSee('20,000.00')
            ->assertDontSee('>' . currency_symbol() . '7,800.00<', false);
    }

    public function test_a_savings_goal_is_measured_against_the_trips_planned_cost(): void
    {
        // The trip has been priced at 50,000 since the goal was created with a
        // target of 10,000. SavingsGoalManager uses total_cost when there is
        // one, so 25,000 saved is 50% — not the 250% target_amount would give.
        $user = User::factory()->create();
        $trip = $this->trip($user, ['total_cost' => 50000]);

        SavingsGoal::create([
            'user_id'         => $user->id,
            'trip_id'         => $trip->id,
            'goal_name'       => 'Cebu fund',
            'target_amount'   => 10000,
            'current_savings' => 25000,
            'deadline'        => now()->addMonths(2)->toDateString(),
        ]);

        $this->actingAs($user)->get('/dashboard')
            ->assertSee('Savings Goals')
            ->assertSee('Cebu fund')
            // Anchored on the printed label: a bare '50%' would also match
            // the bar's own width:50% and pass no matter what was computed.
            ->assertSee('>50%<', false)
            ->assertSee('of ' . currency_symbol() . '50,000');
    }

    public function test_a_goal_at_three_quarters_is_not_painted_as_a_warning(): void
    {
        // meter_color()'s default 'spend' ramp would call 75% amber, because
        // for a budget that is most of it gone. For a savings goal it is
        // nearly there, so this bar has to use the 'progress' ramp.
        $user = User::factory()->create();
        $trip = $this->trip($user, ['total_cost' => 40000]);

        SavingsGoal::create([
            'user_id'         => $user->id,
            'trip_id'         => $trip->id,
            'goal_name'       => 'Cebu fund',
            'target_amount'   => 40000,
            'current_savings' => 30000,
            'deadline'        => now()->addMonth()->toDateString(),
        ]);

        $html = $this->actingAs($user)->get('/dashboard')->getContent();

        $this->assertStringContainsString(
            'width:75%;background:var(--meter-good)',
            $html
        );
    }

    public function test_todays_itinerary_item_is_separated_from_a_later_one(): void
    {
        $user = User::factory()->create();
        $trip = $this->trip($user, [
            'start_date' => now()->subDay()->toDateString(),
            'end_date'   => now()->addDays(5)->toDateString(),
            'status'     => 'active',
        ]);

        Itinerary::create([
            'trip_id'        => $trip->id,
            'title'          => 'Kawasan Falls canyoneering',
            'type'           => 'Activity',
            'start_datetime' => \Carbon\Carbon::today(display_tz())->setTime(9, 30),
        ]);
        Itinerary::create([
            'trip_id'        => $trip->id,
            'title'          => 'Ferry to Bohol',
            'type'           => 'Transportation',
            'start_datetime' => \Carbon\Carbon::today(display_tz())->addDays(2)->setTime(7, 0),
        ]);

        $html = $this->actingAs($user)->get('/dashboard')->getContent();

        $this->assertStringContainsString('Today &amp; Next Up', $html);
        $this->assertStringContainsString('Kawasan Falls canyoneering', $html);
        $this->assertStringContainsString('Ferry to Bohol', $html);

        // Today's item is under Today, the other under Coming up — the order
        // of the two headings is what says which is which.
        $this->assertLessThan(
            strpos($html, 'Coming up'),
            strpos($html, 'Kawasan Falls canyoneering')
        );
        $this->assertGreaterThan(
            strpos($html, 'Coming up'),
            strpos($html, 'Ferry to Bohol')
        );
    }

    public function test_an_itinerary_item_on_a_finished_trip_is_not_next_up(): void
    {
        $user = User::factory()->create();
        $past = $this->trip($user, [
            'trip_name'  => 'Last Year Baguio',
            'start_date' => now()->subYear()->toDateString(),
            'end_date'   => now()->subYear()->addDays(4)->toDateString(),
            'status'     => 'past',
        ]);
        $this->trip($user); // so the page renders its normal, non-empty form

        Itinerary::create([
            'trip_id'        => $past->id,
            'title'          => 'Burnham Park picnic',
            'type'           => 'Activity',
            'start_datetime' => \Carbon\Carbon::today(display_tz())->setTime(10, 0),
        ]);

        $this->actingAs($user)->get('/dashboard')
            ->assertDontSee('Burnham Park picnic');
    }

    public function test_a_quiet_today_still_shows_what_is_coming_up(): void
    {
        // The heading for a section with nothing in it must not render, and
        // "Coming up" has to become the first heading rather than keeping the
        // top margin that separates it from Today.
        $user = User::factory()->create();
        $trip = $this->trip($user, [
            'start_date' => now()->subDay()->toDateString(),
            'end_date'   => now()->addDays(5)->toDateString(),
            'status'     => 'active',
        ]);

        Itinerary::create([
            'trip_id'        => $trip->id,
            'title'          => 'Ferry to Bohol',
            'type'           => 'Transportation',
            'start_datetime' => \Carbon\Carbon::today(display_tz())->addDays(2)->setTime(7, 0),
        ]);

        $html = $this->actingAs($user)->get('/dashboard')->getContent();

        $this->assertStringContainsString('Ferry to Bohol', $html);
        $this->assertStringContainsString('Coming up', $html);
        $this->assertStringNotContainsString('>Today</div>', $html);
        $this->assertStringContainsString('margin:0 0 6px;">Coming up<', $html);
    }

    public function test_an_empty_panel_centres_its_message_in_the_space_it_has(): void
    {
        // Recent Expenses is a grid item, so it stretches to match the taller
        // right-hand column. Without both halves of this the message hangs off
        // the heading with a tall blank card beneath it.
        $user = User::factory()->create();
        $this->trip($user);

        $html = $this->actingAs($user)->get('/dashboard')->getContent();

        $this->assertStringContainsString('Nothing logged yet.', $html);
        // The card has to be a flex column for the empty state's flex:1 to
        // mean anything; asserting only one of the pair would pass on a
        // half-applied fix.
        $this->assertMatchesRegularExpression(
            '/\.dash-card \{[^}]*display: flex;\s*flex-direction: column;/s',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/\.dash-card-empty \{[^}]*flex: 1;/s',
            $html
        );
    }

    // ── The three defects fixed alongside the new panels ────────────

    public function test_only_three_stubs_render_but_every_active_trip_still_counts(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 4) as $n) {
            $this->trip($user, [
                'trip_name'  => "Trip Number {$n}",
                'start_date' => now()->addDays(10 + $n)->toDateString(),
                'end_date'   => now()->addDays(14 + $n)->toDateString(),
            ]);
        }

        $response = $this->actingAs($user)->get('/dashboard');
        $html     = $response->getContent();

        // Three boarding passes, and a line accounting for the fourth.
        $this->assertSame(3, substr_count($html, 'class="trip-stub"'));
        $this->assertStringContainsString('+1 more active trip', $html);

        // The KPI and the view data both keep the whole set — SavedTrips is
        // compared against viewData('activeTrips') by the test above.
        $this->assertCount(4, $response->viewData('activeTrips'));
        $this->assertStringContainsString('0 ongoing · 4 upcoming', $html);
    }

    public function test_three_active_trips_need_no_overflow_line(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 3) as $n) {
            $this->trip($user, ['trip_name' => "Trip Number {$n}"]);
        }

        $this->actingAs($user)->get('/dashboard')
            ->assertDontSee('more active trip');
    }

    public function test_the_charts_row_collapses_instead_of_scrolling_sideways(): void
    {
        // It was an inline grid with a 636px floor and no breakpoint, so the
        // page scrolled horizontally on a phone. An inline style cannot carry
        // a media query, which is why the rule had to move into the class.
        $user = User::factory()->create();
        $this->trip($user);

        $html = $this->actingAs($user)->get('/dashboard')->getContent();

        $this->assertStringContainsString('class="dash-charts-row"', $html);
        $this->assertStringContainsString(
            '@media (max-width: 900px) { .dash-charts-row { grid-template-columns: 1fr; } }',
            $html
        );
        $this->assertStringNotContainsString(
            'style="display:grid;grid-template-columns:minmax(280px,1fr)',
            $html
        );
    }

    public function test_the_dashboard_no_longer_runs_a_query_nothing_reads(): void
    {
        // Four attractions were fetched with a review count on every load and
        // never rendered.
        $user = User::factory()->create();
        $this->trip($user);

        $this->actingAs($user)->get('/dashboard')
            ->assertViewMissing('recommended');
    }
}
