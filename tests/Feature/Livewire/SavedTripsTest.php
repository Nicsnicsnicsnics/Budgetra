<?php
namespace Tests\Feature\Livewire;

use App\Livewire\Traveler\SavedTrips;
use App\Models\Expense;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class SavedTripsTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_trips_page_loads(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/saved-trips')->assertStatus(200);
    }

    public function test_trip_card_shows_zero_spend_with_no_expenses(): void
    {
        $user = User::factory()->create();
        Trip::factory()->create(['user_id' => $user->id, 'budget_limit' => 10000]);

        Livewire::actingAs($user)->test(SavedTrips::class)
            ->assertSee('0%');
    }

    public function test_trip_card_computes_actual_spent_and_percentage(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id, 'budget_limit' => 10000]);
        Expense::create(['trip_id' => $trip->id, 'user_id' => $user->id, 'amount' => 3000, 'category' => 'Food', 'expense_date' => '2026-08-01']);
        Expense::create(['trip_id' => $trip->id, 'user_id' => $user->id, 'amount' => 2000, 'category' => 'Transportation', 'expense_date' => '2026-08-02']);

        // 5000 / 10000 = 50%
        Livewire::actingAs($user)->test(SavedTrips::class)
            ->assertSee('50%')
            ->assertSee('5,000');
    }

    public function test_spend_percentage_caps_at_100_when_over_budget(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id, 'budget_limit' => 1000]);
        Expense::create(['trip_id' => $trip->id, 'user_id' => $user->id, 'amount' => 5000, 'category' => 'Food', 'expense_date' => '2026-08-01']);

        Livewire::actingAs($user)->test(SavedTrips::class)
            ->assertSee('100%');
    }

    public function test_actual_spent_only_counts_the_matching_trip(): void
    {
        $user  = User::factory()->create();
        $trip1 = Trip::factory()->create(['user_id' => $user->id, 'budget_limit' => 10000]);
        $trip2 = Trip::factory()->create(['user_id' => $user->id, 'budget_limit' => 10000]);
        Expense::create(['trip_id' => $trip1->id, 'user_id' => $user->id, 'amount' => 4000, 'category' => 'Food', 'expense_date' => '2026-08-01']);
        Expense::create(['trip_id' => $trip2->id, 'user_id' => $user->id, 'amount' => 1000, 'category' => 'Food', 'expense_date' => '2026-08-01']);

        $component = Livewire::actingAs($user)->test(SavedTrips::class);
        $trips     = collect($component->viewData('trips'))->keyBy('id');

        $this->assertSame(4000.0, $trips[$trip1->id]->actual_spent);
        $this->assertSame(1000.0, $trips[$trip2->id]->actual_spent);
    }

    // Ongoing trip, foreign destination_currency on file, live rate reachable
    // — the card should show yen, not pesos.
    public function test_ongoing_trip_shows_the_destination_currency_when_the_live_rate_is_reachable(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'api.twelvedata.com/*' => Http::response(['symbol' => 'JPY/PHP', 'rate' => 0.387], 200),
        ]);
        Trip::factory()->create([
            'user_id'              => $user->id,
            'status'                => 'active',
            'budget_limit'          => 60000,
            'destination_currency'  => 'JPY',
        ]);

        $html = Livewire::actingAs($user)->test(SavedTrips::class)->html();

        $this->assertStringContainsString('JPY', $html);
        $this->assertStringContainsString(number_format(round(60000 / 0.387)), $html);
    }

    // Same Ongoing + foreign-currency trip, but the live rate lookup fails —
    // the card must fall back to pesos instead of showing nothing or erroring.
    public function test_ongoing_trip_falls_back_to_pesos_when_the_live_rate_is_unavailable(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'api.twelvedata.com/*' => Http::response(['message' => 'Unauthorized'], 401),
        ]);
        Trip::factory()->create([
            'user_id'              => $user->id,
            'status'                => 'active',
            'budget_limit'          => 60000,
            'destination_currency'  => 'JPY',
        ]);

        $html = Livewire::actingAs($user)->test(SavedTrips::class)->html();

        $this->assertStringContainsString('60,000', $html);
        $this->assertStringNotContainsString('JPY', $html);
    }

    // An Upcoming trip with a destination_currency on file now converts too
    // — not just Ongoing ones. Only Draft and Past stay peso-only.
    public function test_upcoming_trip_shows_the_destination_currency_when_the_live_rate_is_reachable(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'api.twelvedata.com/*' => Http::response(['symbol' => 'JPY/PHP', 'rate' => 0.387], 200),
        ]);
        Trip::factory()->create([
            'user_id'              => $user->id,
            'status'                => 'upcoming',
            'budget_limit'          => 60000,
            'destination_currency'  => 'JPY',
        ]);

        $html = Livewire::actingAs($user)->test(SavedTrips::class)->html();

        $this->assertStringContainsString('JPY', $html);
        $this->assertStringContainsString(number_format(round(60000 / 0.387)), $html);
    }

    public function test_upcoming_trip_falls_back_to_pesos_when_the_live_rate_is_unavailable(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'api.twelvedata.com/*' => Http::response(['message' => 'Unauthorized'], 401),
        ]);
        Trip::factory()->create([
            'user_id'              => $user->id,
            'status'                => 'upcoming',
            'budget_limit'          => 60000,
            'destination_currency'  => 'JPY',
        ]);

        $html = Livewire::actingAs($user)->test(SavedTrips::class)->html();

        $this->assertStringContainsString('60,000', $html);
        $this->assertStringNotContainsString('JPY', $html);
    }

    // An Ongoing trip with no destination_currency on file (e.g. a trip
    // saved before that data started being captured) must still show pesos.
    public function test_ongoing_trip_without_a_destination_currency_shows_pesos(): void
    {
        $user = User::factory()->create();
        Trip::factory()->create([
            'user_id'      => $user->id,
            'status'        => 'active',
            'budget_limit'  => 60000,
        ]);

        $html = Livewire::actingAs($user)->test(SavedTrips::class)->html();

        $this->assertStringContainsString('60,000', $html);
    }

    // Draft and Past trips are unaffected by any of this — pesos always.
    public function test_draft_trip_with_a_destination_currency_still_shows_pesos(): void
    {
        $user = User::factory()->create();
        Trip::factory()->create([
            'user_id'              => $user->id,
            'status'                => 'draft',
            'destination'           => 'Draft',
            'budget_limit'          => 60000,
            'destination_currency'  => 'JPY',
        ]);

        $html = Livewire::actingAs($user)->test(SavedTrips::class)->html();

        $this->assertStringNotContainsString('JPY', $html);
    }

    public function test_past_trip_with_a_destination_currency_still_shows_pesos(): void
    {
        $user = User::factory()->create();
        Trip::factory()->create([
            'user_id'              => $user->id,
            'status'                => 'past',
            'budget_limit'          => 60000,
            'destination_currency'  => 'JPY',
        ]);

        $html = Livewire::actingAs($user)->test(SavedTrips::class)->html();

        $this->assertStringContainsString('60,000', $html);
        $this->assertStringNotContainsString('JPY', $html);
    }
}
