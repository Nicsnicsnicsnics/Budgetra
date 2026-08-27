<?php

namespace Tests\Feature;

use App\Livewire\Traveler\TripPlannerWizard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The wizard's currency journey for a traveller from abroad:
 *
 *   plan in THEIR currency  ->  Save Itinerary  ->  "convert your budget?"
 *   ->  summary and estimated cost in the DESTINATION's currency.
 *
 * A Canadian planning Tokyo prices flights and dining in CAD, is asked once at
 * save time whether to switch to JPY, and reads the finished plan in whichever
 * they chose. Pesos remain the stored ledger throughout.
 */
class PlannerCurrencyFlowTest extends TestCase
{
    use RefreshDatabase;

    private function canadianPlanningTokyo(): \Livewire\Features\SupportTesting\Testable
    {
        config(['services.currency_converter.key' => 'test-key']);

        $user = User::factory()->create(['country' => 'Canada']);

        return Livewire::actingAs($user)->test(TripPlannerWizard::class)
            ->set('manualFrom', 'Toronto')
            ->set('manualTo', 'Tokyo')
            ->set('startDate', '2027-05-01')
            ->set('endDate', '2027-05-10')
            ->set('manualBudgetMin', '3000')
            ->set('manualBudgetMax', '3000');
    }

    public function test_planning_is_priced_in_the_travellers_own_currency(): void
    {
        Http::fake(['api.twelvedata.com/*' => Http::response(['rate' => 44.5], 200)]);

        $c = $this->canadianPlanningTokyo();

        // Before any conversion, amounts read in CAD — not pesos.
        $this->assertSame('CAD', $c->instance()->displayCurrency());
        $this->assertStringContainsString('C$', $c->instance()->tripDisplayAmount(44500));
        // 44,500 pesos / 44.5 = C$1,000.
        $this->assertStringContainsString('1,000', $c->instance()->tripDisplayAmount(44500));
    }

    public function test_a_filipino_traveller_still_sees_pesos(): void
    {
        $user = User::factory()->create(['country' => 'Philippines']);

        $c = Livewire::actingAs($user)->test(TripPlannerWizard::class)
            ->set('manualTo', 'Tokyo');

        $this->assertSame('PHP', $c->instance()->displayCurrency());
        $this->assertStringContainsString('₱', $c->instance()->tripDisplayAmount(50000));
    }

    public function test_saving_asks_about_the_destination_currency(): void
    {
        Http::fake(['api.twelvedata.com/*' => Http::response(['rate' => 44.5], 200)]);

        $c = $this->canadianPlanningTokyo()
            ->set('itineraryLeg', 1)
            ->set('flightTripType', 'round_trip')
            ->call('continueItinerary');

        // The prompt appears at save time, not back at the emergency-fund step.
        $c->assertSet('showCurrencyConvertModal', true)
          ->assertSet('destinationCurrencyCode', 'JPY');
    }

    public function test_accepting_switches_the_summary_to_the_destination_currency(): void
    {
        // CAD -> PHP -> JPY: the typed budget goes through the peso ledger.
        Http::fake([
            'api.twelvedata.com/exchange_rate?symbol=CAD*' => Http::response(['rate' => 44.5], 200),
            'api.twelvedata.com/*'                         => Http::response(['rate' => 0.38], 200),
        ]);

        $c = $this->canadianPlanningTokyo()
            ->set('flightTripType', 'round_trip')
            ->call('continueItinerary')
            ->call('acceptCurrencyConversion');

        $c->assertSet('showCurrencyConvertModal', false)
          ->assertSet('step', 9);                       // landed on the summary

        // C$3,000 -> PHP 133,500 -> JPY 351,315 at 0.38.
        $this->assertEqualsWithDelta(351315.0, (float) $c->get('convertedBudget'), 50.0);

        // ...and everything on the summary now reads in yen.
        $this->assertSame('JPY', $c->instance()->displayCurrency());
        $this->assertStringContainsString('¥', $c->instance()->tripDisplayAmount(133500));
    }

    public function test_declining_keeps_the_travellers_own_currency(): void
    {
        Http::fake(['api.twelvedata.com/*' => Http::response(['rate' => 44.5], 200)]);

        $c = $this->canadianPlanningTokyo()
            ->set('flightTripType', 'round_trip')
            ->call('continueItinerary')
            ->call('declineCurrencyConversion');

        $c->assertSet('showCurrencyConvertModal', false)
          ->assertSet('step', 9)
          ->assertSet('convertedBudget', null);

        // Declining is an answer: stay in CAD, and don't ask again.
        $this->assertSame('CAD', $c->instance()->displayCurrency());
        $c->assertSet('currencyConversionAsked', true);
    }

    public function test_the_prompt_is_not_repeated_once_answered(): void
    {
        Http::fake(['api.twelvedata.com/*' => Http::response(['rate' => 44.5], 200)]);

        $c = $this->canadianPlanningTokyo()
            ->set('flightTripType', 'round_trip')
            ->call('continueItinerary')
            ->call('declineCurrencyConversion')
            ->call('continueItinerary');

        $c->assertSet('showCurrencyConvertModal', false)
          ->assertSet('step', 9);
    }

    public function test_a_domestic_trip_is_never_asked(): void
    {
        $user = User::factory()->create(['country' => 'Philippines']);

        Livewire::actingAs($user)->test(TripPlannerWizard::class)
            ->set('manualTo', 'Cebu City')          // no foreign currency involved
            ->set('flightTripType', 'round_trip')
            ->call('continueItinerary')
            ->assertSet('showCurrencyConvertModal', false)
            ->assertSet('step', 9);
    }

    public function test_conversion_is_refused_not_guessed_without_a_rate(): void
    {
        Http::fake(['api.twelvedata.com/*' => Http::response([], 500)]);

        $c = $this->canadianPlanningTokyo()
            ->set('flightTripType', 'round_trip')
            ->call('continueItinerary')
            ->call('acceptCurrencyConversion');

        // Still on the modal, with a message — never a made-up figure.
        $c->assertSet('showCurrencyConvertModal', true)
          ->assertSet('convertedBudget', null);
        $this->assertNotSame('', $c->get('currencyConvertError'));
    }
}
