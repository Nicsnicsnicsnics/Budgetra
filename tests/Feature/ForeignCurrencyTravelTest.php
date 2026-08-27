<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Trip;
use App\Models\User;
use App\Livewire\Traveler\TripPlannerWizard;
use App\Services\OcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A traveller whose home currency AND destination currency are both non-peso —
 * the Canada -> Japan case. Nothing here is specific to those two: the same
 * paths serve all 51 currencies and 154 destinations in PlaceCatalog.
 */
class ForeignCurrencyTravelTest extends TestCase
{
    use RefreshDatabase;

    private function japanTrip(User $user): Trip
    {
        return Trip::factory()->create([
            'user_id'              => $user->id,
            'destination'          => 'Tokyo',
            'destination_currency' => 'JPY',
            'budget_limit'         => 100000,
        ]);
    }

    public function test_a_yen_expense_is_stored_as_pesos_with_the_yen_kept(): void
    {
        config(['services.currency_converter.key' => 'test-key']);
        Http::fake(['api.twelvedata.com/*' => Http::response(['rate' => 0.388], 200)]);

        $user = User::factory()->create(['country' => 'Canada']);
        $trip = $this->japanTrip($user);

        $this->actingAs($user)->post(route('expenses.store'), [
            'trip_id'         => $trip->id,
            'amount'          => 3500,
            'amount_currency' => 'JPY',
            'category'        => 'Food',
            'expense_date'    => now()->toDateString(),
        ])->assertRedirect(route('expenses.index'));

        $expense = Expense::where('trip_id', $trip->id)->firstOrFail();

        $this->assertSame('JPY', $expense->amount_currency);
        $this->assertEqualsWithDelta(3500.0, (float) $expense->amount_original, 0.01);
        // 3500 * 0.388 = 1358 — emphatically NOT 3500 pesos, which is what the
        // peso-only column used to record.
        $this->assertEqualsWithDelta(1358.0, (float) $expense->amount, 1.0);
        $this->assertNotEqualsWithDelta(3500.0, (float) $expense->amount, 1.0);
    }

    public function test_a_peso_expense_is_untouched(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id, 'destination_currency' => null]);

        $this->actingAs($user)->post(route('expenses.store'), [
            'trip_id'         => $trip->id,
            'amount'          => 500,
            'amount_currency' => 'PHP',
            'category'        => 'Food',
            'expense_date'    => now()->toDateString(),
        ])->assertRedirect(route('expenses.index'));

        $expense = Expense::where('trip_id', $trip->id)->firstOrFail();

        $this->assertEqualsWithDelta(500.0, (float) $expense->amount, 0.01);
        $this->assertSame('PHP', $expense->amount_currency);
        $this->assertNull($expense->amount_original);
        $this->assertFalse($expense->isForeign());
    }

    public function test_an_expense_is_refused_not_guessed_when_no_rate_is_available(): void
    {
        config(['services.currency_converter.key' => 'test-key']);
        Http::fake(['api.twelvedata.com/*' => Http::response([], 500)]);

        $user = User::factory()->create();
        $trip = $this->japanTrip($user);

        $this->actingAs($user)->post(route('expenses.store'), [
            'trip_id'         => $trip->id,
            'amount'          => 3500,
            'amount_currency' => 'JPY',
            'category'        => 'Food',
            'expense_date'    => now()->toDateString(),
        ])->assertSessionHasErrors('amount');

        // Nothing banked beats 3500 yen recorded as 3500 pesos.
        $this->assertSame(0, Expense::where('trip_id', $trip->id)->count());
    }

    public function test_editing_a_foreign_expense_does_not_double_convert(): void
    {
        config(['services.currency_converter.key' => 'test-key']);
        Http::fake(['api.twelvedata.com/*' => Http::response(['rate' => 0.388], 200)]);

        $user = User::factory()->create();
        $trip = $this->japanTrip($user);

        $this->actingAs($user)->post(route('expenses.store'), [
            'trip_id' => $trip->id, 'amount' => 3500, 'amount_currency' => 'JPY',
            'category' => 'Food', 'expense_date' => now()->toDateString(),
        ]);
        $expense = Expense::where('trip_id', $trip->id)->firstOrFail();

        // Re-submit the ORIGINAL yen figure, exactly as the edit form shows it.
        $this->actingAs($user)->put(route('expenses.update', $expense), [
            'trip_id' => $trip->id, 'amount' => 3500, 'amount_currency' => 'JPY',
            'category' => 'Food', 'expense_date' => now()->toDateString(),
        ]);

        $expense->refresh();
        $this->assertEqualsWithDelta(1358.0, (float) $expense->amount, 1.0);
        $this->assertEqualsWithDelta(3500.0, (float) $expense->amount_original, 0.01);
    }

    /** '¥' is both yen and yuan — the trip decides which. */
    public function test_an_ambiguous_receipt_symbol_resolves_against_the_destination(): void
    {
        $this->assertSame('JPY', OcrService::currencyFromSymbol('¥', 'JPY'));
        $this->assertSame('CNY', OcrService::currencyFromSymbol('¥', 'CNY'));
        // No destination to lean on: report nothing rather than flip a coin.
        $this->assertNull(OcrService::currencyFromSymbol('¥', null));
        $this->assertNull(OcrService::currencyFromSymbol('¥', 'THB'));

        // Unambiguous glyphs need no help.
        $this->assertSame('KRW', OcrService::currencyFromSymbol('₩', null));
        $this->assertSame('THB', OcrService::currencyFromSymbol('฿', 'JPY'));
    }

    public function test_the_budget_field_is_labelled_in_the_registration_countrys_currency(): void
    {
        $canadian = User::factory()->create(['country' => 'Canada']);
        $this->actingAs($canadian);
        $this->assertSame('CAD', home_currency());
        $this->assertSame('C$', home_currency_symbol());

        $japanese = User::factory()->create(['country' => 'Japan']);
        $this->actingAs($japanese);
        $this->assertSame('JPY', home_currency());

        // country is nullable at registration — pesos is the honest default.
        $unknown = User::factory()->create(['country' => null]);
        $this->actingAs($unknown);
        $this->assertSame('PHP', home_currency());
        $this->assertSame('₱', home_currency_symbol());
    }

    // The wizard's budget box used to be unlabelled and silently mean pesos, so a
    // Canadian typing 3,000 for CAD 3,000 got a ~1/41-sized trip.
    public function test_a_canadian_budget_is_converted_to_pesos_on_save(): void
    {
        config(['services.currency_converter.key' => 'test-key']);
        Http::fake(['api.twelvedata.com/*' => Http::response(['rate' => 44.5], 200)]);

        $user = User::factory()->create(['country' => 'Canada']);

        Livewire::actingAs($user)->test(TripPlannerWizard::class)
            ->set('manualFrom', 'Vancouver')
            ->set('manualTo', 'Tokyo')
            ->set('startDate', '2027-02-01')
            ->set('endDate', '2027-02-08')
            ->set('manualBudgetMin', '3000')
            ->set('manualBudgetMax', '3000')
            ->call('saveItinerary');

        $trip = Trip::where('user_id', $user->id)->firstOrFail();

        $this->assertSame('CAD', $trip->budget_currency);
        $this->assertEqualsWithDelta(3000.0, (float) $trip->budget_local, 0.01);
        $this->assertEqualsWithDelta(133500.0, (float) $trip->budget_limit, 1.0);  // 3000 * 44.5
        $this->assertNotEqualsWithDelta(3000.0, (float) $trip->budget_limit, 1.0);

        // The destination is still remembered independently of the home currency.
        $this->assertSame('JPY', $trip->destination_currency);
    }

    public function test_a_filipino_budget_is_stored_as_typed(): void
    {
        $user = User::factory()->create(['country' => 'Philippines']);

        Livewire::actingAs($user)->test(TripPlannerWizard::class)
            ->set('manualFrom', 'Cebu City')
            ->set('manualTo', 'Tokyo')
            ->set('startDate', '2027-02-01')
            ->set('endDate', '2027-02-08')
            ->set('manualBudgetMin', '60000')
            ->set('manualBudgetMax', '60000')
            ->call('saveItinerary');

        $trip = Trip::where('user_id', $user->id)->firstOrFail();

        $this->assertEqualsWithDelta(60000.0, (float) $trip->budget_limit, 0.01);
        $this->assertSame('PHP', $trip->budget_currency);
        $this->assertNull($trip->budget_local);   // nothing was converted
    }
}
