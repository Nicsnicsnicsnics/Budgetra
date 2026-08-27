<?php

namespace Tests\Feature;

use App\Livewire\Traveler\TripPlannerWizard;
use App\Models\Expense;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Scenario: a traveller from SOUTH KOREA flying to ZURICH.
 *
 * Chosen to stress what the Canada -> Japan case never touched. Three currencies
 * are live at once and two of them sit four orders of magnitude apart: KRW is
 * worth about 0.045 pesos, CHF about 68. CHF also has no glyph, so Swiss receipts
 * print "CHF 45.00" and the scan has to read the ISO code rather than a symbol.
 *
 * The Vietnam -> Japan cases at the bottom cover currencies whose unit value is
 * so low that an ordinary trip budget needs more than seven digits.
 */
class ThirdCountryScenarioTest extends TestCase
{
    use RefreshDatabase;

    private function fakeOcrReturning(string $receiptText): void
    {
        Http::fake([
            'api.ocr.space/*' => Http::response([
                'OCRExitCode'   => 1,
                'ParsedResults' => [['ParsedText' => $receiptText]],
            ], 200),
            'api.twelvedata.com/*' => Http::response(['rate' => 68.0], 200),
        ]);
    }

    public function test_korean_traveller_plans_a_zurich_trip_in_won(): void
    {
        config(['services.currency_converter.key' => 'test-key']);
        Http::fake(['api.twelvedata.com/*' => Http::response(['rate' => 0.0447], 200)]);

        $user = User::factory()->create(['country' => 'South Korea']);

        Livewire::actingAs($user)->test(TripPlannerWizard::class)
            ->set('manualFrom', 'Seoul')
            ->set('manualTo', 'Zurich')
            ->set('startDate', '2027-05-01')
            ->set('endDate', '2027-05-10')
            ->set('manualBudgetMin', '3000000')
            ->set('manualBudgetMax', '3000000')
            ->call('saveItinerary');

        $trip = Trip::where('user_id', $user->id)->firstOrFail();

        // Planned in won...
        $this->assertSame('KRW', $trip->budget_currency);
        $this->assertEqualsWithDelta(3000000.0, (float) $trip->budget_local, 0.01);
        // ...banked in pesos: 3,000,000 * 0.0447 = 134,100.
        $this->assertEqualsWithDelta(134100.0, (float) $trip->budget_limit, 1.0);
        // ...and spent in francs.
        $this->assertSame('CHF', $trip->destination_currency);
    }

    public function test_a_swiss_receipt_is_read_from_its_printed_code_not_a_symbol(): void
    {
        config(['services.currency_converter.key' => 'test-key', 'services.ocr.key' => 'test-key']);
        $this->fakeOcrReturning("BAHNHOFSTRASSE CAFE\nZURICH\nTOTAL CHF 45.00\n2027-05-02");

        $user = User::factory()->create(['country' => 'South Korea']);
        $trip = Trip::factory()->create([
            'user_id'              => $user->id,
            'destination'          => 'Zurich',
            'destination_currency' => 'CHF',
        ]);

        $response = $this->actingAs($user)->post(route('expenses.ocr'), [
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
            'trip_id' => $trip->id,
        ])->assertOk();

        // No glyph anywhere on that receipt — the printed ISO code carried it.
        $response->assertJsonPath('currency', 'CHF');
        $this->assertEqualsWithDelta(45.0, (float) $response->json('amount'), 0.01);
    }

    public function test_a_franc_expense_converts_to_pesos(): void
    {
        config(['services.currency_converter.key' => 'test-key']);
        Http::fake(['api.twelvedata.com/*' => Http::response(['rate' => 68.0], 200)]);

        $user = User::factory()->create(['country' => 'South Korea']);
        $trip = Trip::factory()->create([
            'user_id'              => $user->id,
            'destination'          => 'Zurich',
            'destination_currency' => 'CHF',
            'budget_limit'         => 134100,
        ]);

        $this->actingAs($user)->post(route('expenses.store'), [
            'trip_id'         => $trip->id,
            'amount'          => 45,
            'amount_currency' => 'CHF',
            'category'        => 'Food',
            'expense_date'    => now()->toDateString(),
        ])->assertRedirect(route('expenses.index'));

        $expense = Expense::where('trip_id', $trip->id)->firstOrFail();

        $this->assertSame('CHF', $expense->amount_currency);
        $this->assertEqualsWithDelta(45.0, (float) $expense->amount_original, 0.01);
        $this->assertEqualsWithDelta(3060.0, (float) $expense->amount, 1.0);   // 45 * 68
        $this->assertSame('CHF 45.00', $expense->originalAmountLabel());
    }

    /** Every glyph-less destination currency, not just the Swiss one. */
    public function test_glyphless_currencies_resolve_from_a_printed_code(): void
    {
        config(['services.ocr.key' => 'test-key']);
        $user = User::factory()->create();

        foreach (['CHF' => 'Zurich', 'SEK' => 'Stockholm', 'TWD' => 'Taipei'] as $code => $city) {
            $this->fakeOcrReturning("SHOP\n{$city}\nTOTAL {$code} 250.00");

            $trip = Trip::factory()->create([
                'user_id'              => $user->id,
                'destination'          => $city,
                'destination_currency' => $code,
            ]);

            $this->actingAs($user)->post(route('expenses.ocr'), [
                'receipt' => UploadedFile::fake()->image('r.jpg'),
                'trip_id' => $trip->id,
            ])->assertOk()->assertJsonPath('currency', $code);
        }
    }

    // The budget input allowed seven digits and truncated silently. Fine when it
    // held pesos; holding dong it caps a trip at roughly PHP 24,000, and a
    // 30,000,000 budget (about PHP 71,000, ordinary) became 3,000,000 in silence.
    public function test_a_dong_budget_is_not_truncated(): void
    {
        config(['services.currency_converter.key' => 'test-key']);
        Http::fake(['api.twelvedata.com/*' => Http::response(['rate' => 0.00238], 200)]);

        $user = User::factory()->create(['country' => 'Vietnam']);

        Livewire::actingAs($user)->test(TripPlannerWizard::class)
            ->set('manualFrom', 'Hanoi')
            ->set('manualTo', 'Tokyo')
            ->set('startDate', '2027-03-01')
            ->set('endDate', '2027-03-08')
            ->set('manualBudgetMin', '30000000')      // eight digits
            ->set('manualBudgetMax', '30000000')
            ->call('saveItinerary');

        $trip = Trip::where('user_id', $user->id)->firstOrFail();

        $this->assertSame('VND', $trip->budget_currency);
        $this->assertEqualsWithDelta(30000000.0, (float) $trip->budget_local, 0.01);
        $this->assertEqualsWithDelta(71400.0, (float) $trip->budget_limit, 1.0);
    }

    // trips.budget_limit is decimal(10,2). A converted budget can exceed it, and
    // that has to read as a sentence rather than a database exception.
    public function test_an_over_ceiling_budget_is_refused_with_a_message(): void
    {
        config(['services.currency_converter.key' => 'test-key']);
        Http::fake(['api.twelvedata.com/*' => Http::response(['rate' => 84.0], 200)]);

        $user = User::factory()->create(['country' => 'United Kingdom']);

        Livewire::actingAs($user)->test(TripPlannerWizard::class)
            ->set('manualFrom', 'London')
            ->set('manualTo', 'Tokyo')
            ->set('startDate', '2027-03-01')
            ->set('endDate', '2027-03-08')
            ->set('manualBudgetMin', '9999999')       // 9,999,999 * 84 = PHP 839,999,916
            ->set('manualBudgetMax', '9999999')
            ->call('saveItinerary')
            ->assertSet('emergencyError', fn ($e) => is_string($e) && $e !== '');

        $this->assertSame(0, Trip::where('user_id', $user->id)->count());
    }
}
