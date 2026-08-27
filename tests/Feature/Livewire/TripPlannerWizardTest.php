<?php
namespace Tests\Feature\Livewire;

use App\Livewire\Traveler\TripPlannerWizard;
use App\Models\Destination;
use App\Models\Itinerary;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class TripPlannerWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_wizard_page_loads(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/trips/plan')->assertStatus(200);
    }

    public function test_wizard_starts_at_step_one(): void
    {
        $user = User::factory()->create();
        Livewire::actingAs($user)
            ->test(TripPlannerWizard::class)
            ->assertSet('step', 1);
    }

    public function test_can_advance_to_step_two(): void
    {
        $user = User::factory()->create();
        Livewire::actingAs($user)
            ->test(TripPlannerWizard::class)
            ->set('tripScope', 'international')
            ->call('nextStep')
            ->assertSet('step', 2);
    }

    public function test_wizard_creates_trip_on_confirm(): void
    {
        $user = User::factory()->create();
        $dest = Destination::create(['name' => 'Boracay', 'country' => 'Philippines', 'description' => 'Beautiful island']);

        Livewire::actingAs($user)
            ->test(TripPlannerWizard::class)
            ->set('step', 5)
            ->set('tripScope', 'local')
            ->set('destinationId', $dest->id)
            ->set('destinationName', 'Boracay, Philippines')
            ->set('startDate', '2027-01-10')
            ->set('endDate', '2027-01-15')
            ->set('travelType', 'Solo')
            ->set('budgetTier', 'Mid-range')
            ->set('budgetLimit', 50000)
            ->call('confirm')
            ->assertRedirect();

        $this->assertDatabaseHas('trips', [
            'user_id'     => $user->id,
            'destination' => 'Boracay, Philippines',
            'travel_type' => 'Solo',
        ]);
    }

    public function test_wizard_requires_auth(): void
    {
        $this->get('/trips/plan')->assertRedirect(route('login'));
    }

    private function fakeVenue(string $name, int $priceMin = 200, int $priceMax = 400): array
    {
        return ['name' => $name, 'cuisine' => 'Filipino', 'city' => 'Cebu', 'priceMin' => $priceMin, 'priceMax' => $priceMax];
    }

    private function fakeAttraction(string $name, bool $isFree = false, string $price = '150'): array
    {
        return ['name' => $name, 'type' => 'Landmark', 'city' => 'Cebu', 'isFree' => $isFree, 'price' => $isFree ? 'FREE' : $price];
    }

    public function test_select_venue_replaces_previous_pick_and_advances_to_attractions(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TripPlannerWizard::class)
            ->set('manualTo', 'Cebu')
            ->set('flightTripType', 'round_trip')
            ->set('venueResults', [$this->fakeVenue('Larsian BBQ'), $this->fakeVenue('Zubuchon')])
            ->call('selectVenue', 0)
            ->assertSet('selectedVenues.Larsian BBQ.name', 'Larsian BBQ')
            ->assertSet('step', 5);

        // Picking again from a fresh instance simulates going back and choosing differently —
        // selecting should replace, not add to, the previous pick.
        Livewire::actingAs($user)
            ->test(TripPlannerWizard::class)
            ->set('manualTo', 'Cebu')
            ->set('flightTripType', 'round_trip')
            ->set('venueResults', [$this->fakeVenue('Larsian BBQ'), $this->fakeVenue('Zubuchon')])
            ->call('selectVenue', 0)
            ->call('selectVenue', 1)
            ->assertSet('selectedVenues', ['Zubuchon' => $this->fakeVenue('Zubuchon')]);
    }

    public function test_select_attraction_replaces_previous_pick_and_advances_to_step_six(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TripPlannerWizard::class)
            ->set('flightTripType', 'round_trip')
            ->set('attractionResults', [$this->fakeAttraction('Magellan\'s Cross'), $this->fakeAttraction('Fort San Pedro')])
            ->call('selectAttraction', 0)
            ->assertSet('selectedAttractions.Magellan\'s Cross.name', 'Magellan\'s Cross')
            ->assertSet('step', 6)
            ->call('selectAttraction', 1)
            ->assertSet('selectedAttractions', ['Fort San Pedro' => $this->fakeAttraction('Fort San Pedro')]);
    }

    public function test_save_itinerary_creates_one_row_for_the_selected_venue_and_attraction(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TripPlannerWizard::class)
            ->set('manualFrom', 'Manila')
            ->set('manualTo', 'Cebu')
            ->set('startDate', '2027-02-01')
            ->set('endDate', '2027-02-08')
            ->set('manualBudgetMin', '20000')
            ->set('manualBudgetMax', '30000')
            ->set('venueResults', [$this->fakeVenue('Larsian BBQ')])
            ->call('selectVenue', 0)
            ->set('attractionResults', [$this->fakeAttraction('Fort San Pedro')])
            ->call('selectAttraction', 0)
            ->call('saveItinerary');

        $trip = Trip::where('user_id', $user->id)->first();
        $this->assertNotNull($trip);

        $this->assertSame(2, Itinerary::where('trip_id', $trip->id)->count());
        $this->assertTrue(str_contains($trip->summary_data['food']['detail'], 'Larsian BBQ'));
        $this->assertTrue(str_contains($trip->summary_data['attractions']['detail'], 'Fort San Pedro'));
        $this->assertSame(150, (int) $trip->summary_data['attractions']['cost']);
    }

    // Gap found live: the wizard's own currency-conversion modal only ever
    // affected what got SHOWN during planning — the saved Trip itself never
    // remembered the destination's currency at all, unlike TARA's version
    // of the same feature. Confirms saveItinerary() now persists it
    // independently, regardless of whether that modal was ever shown,
    // accepted, or declined.
    public function test_save_itinerary_persists_the_destination_currency_for_a_foreign_destination(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'api.twelvedata.com/*' => Http::response(['symbol' => 'JPY/PHP', 'rate' => 0.387], 200),
        ]);

        Livewire::actingAs($user)
            ->test(TripPlannerWizard::class)
            ->set('manualFrom', 'Cebu City')
            ->set('manualTo', 'Tokyo')
            ->set('startDate', '2027-02-01')
            ->set('endDate', '2027-02-08')
            ->set('manualBudgetMin', '60000')
            ->set('manualBudgetMax', '60000')
            ->call('saveItinerary');

        $trip = Trip::where('user_id', $user->id)->first();
        $this->assertNotNull($trip);
        $this->assertSame('JPY', $trip->destination_currency);
        $this->assertEquals(round(60000 / 0.387, 2), (float) $trip->destination_budget);
        $this->assertEquals(60000, (float) $trip->budget_limit);
    }

    public function test_save_itinerary_leaves_destination_currency_null_for_a_domestic_destination(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TripPlannerWizard::class)
            ->set('manualFrom', 'Manila')
            ->set('manualTo', 'Cebu')
            ->set('startDate', '2027-02-01')
            ->set('endDate', '2027-02-08')
            ->set('manualBudgetMin', '20000')
            ->set('manualBudgetMax', '30000')
            ->call('saveItinerary');

        $trip = Trip::where('user_id', $user->id)->first();
        $this->assertNotNull($trip);
        $this->assertNull($trip->destination_currency);
        $this->assertNull($trip->destination_budget);
    }

    public function test_add_custom_activity_requires_a_title(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TripPlannerWizard::class)
            ->set('customActivityTitle', '   ')
            ->call('addCustomActivity')
            ->assertSet('customActivities', [])
            ->assertSet('showCustomActivityModal', false);
    }

    public function test_add_custom_activity_adds_it_and_closes_modal(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TripPlannerWizard::class)
            ->call('openCustomActivityModal')
            ->assertSet('showCustomActivityModal', true)
            ->set('customActivityTitle', 'Souvenir Shopping')
            ->set('customActivityDay', '2')
            ->set('customActivityCost', '500')
            ->set('customActivityType', 'Shopping')
            ->call('addCustomActivity')
            ->assertSet('showCustomActivityModal', false)
            ->assertSet('customActivities', [[
                'day'         => 2,
                'title'       => 'Souvenir Shopping',
                'time'        => '09:00',
                'cost'        => 500.0,
                'type'        => 'Shopping',
                'description' => '',
            ]]);
    }

    public function test_remove_custom_activity_deletes_it(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TripPlannerWizard::class)
            ->set('customActivityTitle', 'Souvenir Shopping')
            ->call('addCustomActivity')
            ->call('removeCustomActivity', 0)
            ->assertSet('customActivities', []);
    }

    public function test_save_itinerary_persists_custom_activity_on_the_chosen_day(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TripPlannerWizard::class)
            ->set('manualFrom', 'Manila')
            ->set('manualTo', 'Cebu')
            ->set('startDate', '2027-02-01')
            ->set('endDate', '2027-02-08')
            ->set('manualBudgetMin', '20000')
            ->set('manualBudgetMax', '30000')
            ->set('customActivities', [
                ['day' => 3, 'title' => 'Souvenir Shopping', 'time' => '16:00', 'cost' => 500.0, 'type' => 'Shopping', 'description' => 'At the market'],
            ])
            ->call('saveItinerary');

        $trip = Trip::where('user_id', $user->id)->first();
        $this->assertNotNull($trip);

        $item = Itinerary::where('trip_id', $trip->id)->where('title', 'Souvenir Shopping')->first();
        $this->assertNotNull($item);
        $this->assertSame('2027-02-03 16:00:00', $item->start_datetime->format('Y-m-d H:i:s'));
        $this->assertSame('Activity', $item->type);
        $this->assertSame('At the market', $item->notes);
        $this->assertSame(500, (int) $trip->summary_data['attractions']['cost']);
    }

    public function test_back_to_attractions_returns_to_step_five_without_clearing_selection(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TripPlannerWizard::class)
            ->set('flightTripType', 'round_trip')
            ->set('attractionResults', [$this->fakeAttraction('Fort San Pedro')])
            ->call('selectAttraction', 0)
            ->set('step', 8)
            ->call('backToAttractions')
            ->assertSet('step', 5)
            ->assertSet('selectedAttractions.Fort San Pedro.name', 'Fort San Pedro');
    }

    // The exact bug reported live: a trip handed off from TARA in CAD
    // carries its budget over correctly (already converted to pesos), but
    // the Emergency Fund box had no idea the trip was ever in CAD — typing
    // "1000" meaning 1,000 CAD got treated as ₱1,000, barely denting the
    // budget instead of the ~₱40,000+ the traveler actually meant.
    public function test_emergency_fund_converts_using_the_trip_currency_from_the_ai_handoff(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'api.twelvedata.com/*' => Http::response(['symbol' => 'CAD/PHP', 'rate' => 41], 200),
        ]);

        $component = Livewire::actingAs($user)->test(TripPlannerWizard::class)
            ->set('tripCurrency', 'CAD')
            ->set('manualBudgetMax', '50000')
            ->set('emergency', 1000)
            ->call('confirmEmergencyFund');

        $component->assertSet('emergency', 41000.0);
        $component->assertSet('step', 7);
    }

    public function test_emergency_fund_stays_in_pesos_when_no_trip_currency_was_carried_over(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(TripPlannerWizard::class)
            ->set('tripCurrency', '')
            ->set('manualBudgetMax', '50000')
            ->set('emergency', 1000)
            ->call('confirmEmergencyFund');

        $component->assertSet('emergency', 1000.0);
        $component->assertSet('step', 7);
    }

    public function test_emergency_fund_blocks_with_a_message_when_the_live_rate_is_unavailable(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'api.twelvedata.com/*' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $component = Livewire::actingAs($user)->test(TripPlannerWizard::class)
            ->set('tripCurrency', 'CAD')
            ->set('manualBudgetMax', '50000')
            ->set('emergency', 1000)
            ->call('confirmEmergencyFund');

        $component->assertSet('emergency', 1000.0);
        $component->assertSet('step', 0);
        $lastError = $component->get('emergencyError');
        $this->assertStringContainsString("couldn't fetch the live exchange rate", $lastError);
    }

    // Re-typing a new amount must trigger a fresh conversion instead of
    // reusing a stale already-converted value from a previous attempt.
    public function test_emergency_fund_reconverts_after_the_amount_changes(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'api.twelvedata.com/*' => Http::response(['symbol' => 'CAD/PHP', 'rate' => 41], 200),
        ]);

        $component = Livewire::actingAs($user)->test(TripPlannerWizard::class)
            ->set('tripCurrency', 'CAD')
            ->set('manualBudgetMax', '90000')
            ->set('emergency', 1000)
            ->call('confirmEmergencyFund');

        $component->assertSet('emergency', 41000.0);

        $component->set('emergency', 500)->call('confirmEmergencyFund');

        $component->assertSet('emergency', 20500.0);
    }

    // Per the confirmed design, the emergency fund is money set aside
    // SEPARATELY from the trip budget (added on top), not carved out of
    // it — so it's never blocked for being close to, equal to, or even
    // larger than the trip budget itself.
    public function test_emergency_fund_is_not_blocked_even_when_it_exceeds_the_trip_budget(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(TripPlannerWizard::class)
            ->set('tripCurrency', '')
            ->set('manualBudgetMax', '30000')
            ->set('emergency', 35000)
            ->call('confirmEmergencyFund');

        $component->assertSet('emergencyError', '');
        $component->assertSet('step', 7);
    }

    // The exact scenario reported live: a 30,000 peso trip budget with a
    // 5,000 emergency fund should show as 35,000 total (added together),
    // not 30,000 (carved out of the same total).
    public function test_budget_range_top_end_adds_the_emergency_fund_on_top(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(TripPlannerWizard::class)
            ->set('planningMode', 'manual')
            ->set('tripCurrency', '')
            ->set('manualBudgetMin', '30000')
            ->set('manualBudgetMax', '30000')
            ->set('emergency', 5000)
            ->set('step', 7);

        $html = $component->html();
        $this->assertStringContainsString('Budget Range', $html);
        $this->assertStringContainsString('₱30,000', $html);
        $this->assertStringContainsString('₱35,000', $html);
    }

    public function test_budget_range_shows_just_the_trip_budget_when_no_emergency_fund_is_set(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(TripPlannerWizard::class)
            ->set('planningMode', 'manual')
            ->set('tripCurrency', '')
            ->set('manualBudgetMin', '30000')
            ->set('manualBudgetMax', '30000')
            ->set('emergency', 0)
            ->set('step', 7);

        $html = $component->html();
        $this->assertStringNotContainsString('₱35,000', $html);
    }

    // The exact scenario requested: Cebu -> Tokyo, ₱60,000 budget — the
    // destination's currency (JPY) differs from pesos, so confirming the
    // emergency fund step should show the conversion modal instead of
    // jumping straight to Step 7.
    public function test_confirming_emergency_fund_shows_the_conversion_modal_for_a_foreign_destination(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(TripPlannerWizard::class)
            ->set('planningMode', 'manual')
            ->set('manualFrom', 'Cebu City')
            ->set('manualTo', 'Tokyo')
            ->set('tripCurrency', '')
            ->set('manualBudgetMin', '60000')
            ->set('manualBudgetMax', '60000')
            ->call('confirmEmergencyFund');

        $component->assertSet('showCurrencyConvertModal', true);
        $component->assertSet('destinationCurrencyCode', 'JPY');
        $component->assertSet('step', 0);

        $html = $component->html();
        $this->assertStringContainsString('Philippine pesos (PHP)', $html);
        $this->assertStringContainsString('Japanese yen (JPY)', $html);
    }

    // The exact gap reported live: a Canada-based traveler's confirmation
    // modal was always saying "from Philippine pesos" no matter what their
    // real base currency was — it should name their actual base currency
    // (carried over as tripCurrency) instead of assuming everyone starts
    // in pesos.
    public function test_conversion_modal_names_the_travelers_real_base_currency_instead_of_always_pesos(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(TripPlannerWizard::class)
            ->set('planningMode', 'manual')
            ->set('manualFrom', 'Cebu City')
            ->set('manualTo', 'Tokyo')
            ->set('tripCurrency', 'CAD')
            ->set('manualBudgetMin', '60000')
            ->set('manualBudgetMax', '60000')
            ->call('confirmEmergencyFund');

        $html = $component->html();
        $this->assertStringContainsString('Canadian dollars (CAD)', $html);
        $this->assertStringNotContainsString('Philippine pesos (PHP)', $html);
        $this->assertStringContainsString('Japanese yen (JPY)', $html);
    }

    public function test_confirming_emergency_fund_skips_the_modal_for_a_domestic_destination(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(TripPlannerWizard::class)
            ->set('planningMode', 'manual')
            ->set('manualFrom', 'Manila')
            ->set('manualTo', 'Boracay')
            ->set('tripCurrency', '')
            ->set('manualBudgetMin', '30000')
            ->set('manualBudgetMax', '30000')
            ->call('confirmEmergencyFund');

        $component->assertSet('showCurrencyConvertModal', false);
        $component->assertSet('step', 7);
    }

    public function test_accepting_the_conversion_converts_the_budget_using_the_live_rate(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'api.twelvedata.com/*' => Http::response(['symbol' => 'JPY/PHP', 'rate' => 0.387], 200),
        ]);

        $component = Livewire::actingAs($user)->test(TripPlannerWizard::class)
            ->set('planningMode', 'manual')
            ->set('manualFrom', 'Cebu City')
            ->set('manualTo', 'Tokyo')
            ->set('tripCurrency', '')
            ->set('manualBudgetMin', '60000')
            ->set('manualBudgetMax', '60000')
            ->call('confirmEmergencyFund')
            ->call('acceptCurrencyConversion');

        $component->assertSet('showCurrencyConvertModal', false);
        $component->assertSet('step', 7);
        $component->assertSet('convertedBudget', round(60000 / 0.387, 2));

        $html = $component->html();
        $this->assertStringContainsString('in Tokyo', $html);
    }

    public function test_accepting_the_conversion_shows_an_error_when_the_live_rate_is_unavailable(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'api.twelvedata.com/*' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $component = Livewire::actingAs($user)->test(TripPlannerWizard::class)
            ->set('planningMode', 'manual')
            ->set('manualFrom', 'Cebu City')
            ->set('manualTo', 'Tokyo')
            ->set('tripCurrency', '')
            ->set('manualBudgetMin', '60000')
            ->set('manualBudgetMax', '60000')
            ->call('confirmEmergencyFund')
            ->call('acceptCurrencyConversion');

        $component->assertSet('showCurrencyConvertModal', true);
        $component->assertSet('convertedBudget', null);
        $component->assertSet('step', 0);
        $lastError = $component->get('currencyConvertError');
        $this->assertStringContainsString("couldn't fetch the live exchange rate", $lastError);
    }

    public function test_declining_the_conversion_proceeds_without_a_converted_budget(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(TripPlannerWizard::class)
            ->set('planningMode', 'manual')
            ->set('manualFrom', 'Cebu City')
            ->set('manualTo', 'Tokyo')
            ->set('tripCurrency', '')
            ->set('manualBudgetMin', '60000')
            ->set('manualBudgetMax', '60000')
            ->call('confirmEmergencyFund')
            ->call('declineCurrencyConversion');

        $component->assertSet('showCurrencyConvertModal', false);
        $component->assertSet('step', 7);
        $component->assertSet('convertedBudget', null);

        $html = $component->html();
        $this->assertStringNotContainsString('in Tokyo', $html);
    }

    // The peso budget must never change from accepting the conversion —
    // it's display-only, everything else in the app keeps working in pesos.
    public function test_the_underlying_peso_budget_is_unchanged_after_accepting_the_conversion(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'api.twelvedata.com/*' => Http::response(['symbol' => 'JPY/PHP', 'rate' => 0.387], 200),
        ]);

        $component = Livewire::actingAs($user)->test(TripPlannerWizard::class)
            ->set('planningMode', 'manual')
            ->set('manualFrom', 'Cebu City')
            ->set('manualTo', 'Tokyo')
            ->set('tripCurrency', '')
            ->set('manualBudgetMin', '60000')
            ->set('manualBudgetMax', '60000')
            ->call('confirmEmergencyFund')
            ->call('acceptCurrencyConversion');

        $component->assertSet('manualBudgetMax', '60000');
    }
}
