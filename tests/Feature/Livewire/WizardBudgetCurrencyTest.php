<?php
namespace Tests\Feature\Livewire;

use App\Livewire\Traveler\TripPlannerWizard;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\CurrencyConverterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Generate Itinerary shows the budget in the traveller's own currency.
 *
 * The card converted on $tripCurrency alone, which is only set when a trip is
 * handed over from TARA. Plan manually and it was empty, so the range fell
 * through to a peso sign whatever country the account was registered with —
 * while the budget field back on step 1, labelled from budgetCurrency(), had
 * been asking for that same figure in CAD.
 */
class WizardBudgetCurrencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1 CAD = $rate PHP, fixed, so the arithmetic below is not at the mercy of
     * a live lookup and nothing here reaches the network.
     */
    private function fakeRate(?float $rate): void
    {
        // Called exactly once per test: Http::fake() APPENDS stubs rather than
        // replacing them, so a second call leaves the first one still matching
        // and the rate silently unchanged.
        config(['services.currency_converter.key' => 'test-key']);
        Http::preventStrayRequests();
        Http::fake([
            'api.twelvedata.com/*' => $rate === null
                ? Http::response([], 500)
                : Http::response(['rate' => $rate], 200),
        ]);

        // The service caches rates, and misses, for hours — and memoises them
        // in a STATIC that outlives the test. Both have to go or the second
        // test in this file quietly reuses the first one's answer.
        cache()->flush();
        $memo = new \ReflectionProperty(CurrencyConverterService::class, 'memo');
        $memo->setAccessible(true);
        $memo->setValue(null, []);
    }

    private function traveler(?string $country): User
    {
        $user = User::factory()->create(['country' => $country]);
        UserProfile::create(['user_id' => $user->id]);

        return $user;
    }

    public function test_the_helper_follows_the_country_from_registration(): void
    {
        $this->fakeRate(40.0);

        $this->actingAs($this->traveler('Canada'));

        $component = Livewire::test(TripPlannerWizard::class);

        $this->assertSame('CAD', $component->instance()->budgetCurrency());
        $this->assertStringStartsWith('C$', $component->instance()->budgetDisplayAmount(40000));
    }

    public function test_a_philippine_traveler_still_sees_pesos(): void
    {
        $this->actingAs($this->traveler('Philippines'));

        $component = Livewire::test(TripPlannerWizard::class);

        $this->assertSame('PHP', $component->instance()->budgetCurrency());
        $this->assertSame('₱40,000', $component->instance()->budgetDisplayAmount(40000));
    }

    public function test_an_account_with_no_country_falls_back_to_pesos(): void
    {
        // country is nullable at registration, so this is a real state.
        $this->actingAs($this->traveler(null));

        $component = Livewire::test(TripPlannerWizard::class);

        $this->assertSame('₱40,000', $component->instance()->budgetDisplayAmount(40000));
    }

    public function test_the_stored_pesos_are_converted_not_just_relabelled(): void
    {
        $this->fakeRate(40.0);

        // The bug this guards against is subtler than a wrong symbol: showing
        // "C$200,000" against a figure that is really 200,000 pesos overstates
        // the budget by the exchange rate.
        $this->actingAs($this->traveler('Canada'));

        $shown = Livewire::test(TripPlannerWizard::class)->instance()->budgetDisplayAmount(40000);

        $this->assertSame('C$1,000', $shown, '40,000 PHP at 40/CAD is C$1,000');
    }

    public function test_a_trip_carried_over_from_tara_keeps_its_own_currency(): void
    {
        // budgetCurrency() prefers tripCurrency when there is one — a trip
        // planned with TARA in JPY must not be relabelled into the home
        // currency just because this now has a fallback.
        $this->actingAs($this->traveler('Canada'));

        $component = Livewire::test(TripPlannerWizard::class)->set('tripCurrency', 'JPY');

        $this->assertSame('JPY', $component->instance()->budgetCurrency());
    }

    public function test_a_failed_rate_lookup_keeps_the_peso_sign(): void
    {
        // If the figure cannot be converted it is still pesos, so it must
        // still say pesos. Labelling an unconverted amount C$ is the exact
        // mistake this whole area exists to avoid.
        $this->fakeRate(null);

        $this->actingAs($this->traveler('Canada'));

        $shown = Livewire::test(TripPlannerWizard::class)->instance()->budgetDisplayAmount(40000);

        $this->assertStringStartsWith('₱', $shown);
    }

    public function test_the_card_no_longer_rolls_its_own_conversion(): void
    {
        // The template had a copy of this logic that checked only
        // tripCurrency. Two implementations of one rule is how they drifted.
        $template = file_get_contents(
            resource_path('views/livewire/traveler/trip-planner-wizard.blade.php')
        );

        $this->assertStringNotContainsString('$budDivisor', $template);
        $this->assertStringNotContainsString('$budSymbol', $template);
        $this->assertStringContainsString('$this->budgetDisplayAmount(', $template);
    }

    public function test_the_budget_range_is_not_the_destination_conversion(): void
    {
        $this->fakeRate(40.0);

        // The card prints the destination figure on its own line underneath.
        // If the range used tripDisplayAmount() it would switch to the
        // destination currency once a conversion was accepted, and the
        // traveller's own currency would be nowhere on screen.
        $this->actingAs($this->traveler('Canada'));

        $component = Livewire::test(TripPlannerWizard::class)
            ->set('convertedBudget', 5000)
            ->set('destinationCurrencyCode', 'JPY');

        $instance = $component->instance();

        $this->assertSame('JPY', $instance->displayCurrency(), 'setup no longer exercises this');
        $this->assertSame('CAD', $instance->budgetCurrency());
        $this->assertStringStartsWith('C$', $instance->budgetDisplayAmount(40000));
    }
}
