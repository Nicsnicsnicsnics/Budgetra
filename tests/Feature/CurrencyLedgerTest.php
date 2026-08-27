<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use App\Services\CurrencyConverterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Budgetra's ledger is pesos. These cover the two ways that used to go wrong:
 * amounts getting labelled with a currency nothing converted to, and a failing
 * rate provider being hammered once per displayed amount.
 */
class CurrencyLedgerTest extends TestCase
{
    use RefreshDatabase;

    // The account currency preference defaulted to USD and converted nothing, so
    // its symbol landed on the expense and savings amount INPUTS — a traveller
    // typing 100 for a $100 dinner stored ₱100. It must not drive any amount field. The expense
    // form now carries a real currency selector (a traveller in Japan picks ¥),
    // so what matters is that it defaults from the TRIP, never from the account's
    // leftover USD setting. Savings are still peso-only.
    public function test_amount_inputs_ignore_a_stale_usd_account_preference(): void
    {
        $user = User::factory()->create(['currency_code' => 'USD', 'currency_symbol' => '$']);
        Trip::factory()->create(['user_id' => $user->id, 'destination_currency' => null]);

        $this->actingAs($user)->get(route('expenses.create'))
            ->assertOk()
            ->assertDontSee('Amount ($)')
            // A peso trip defaults the selector to PHP, not to the account's USD.
            ->assertSee('value="PHP" selected', false);

        $this->actingAs($user)->get(route('savings.create'))
            ->assertOk()
            ->assertSee('Target Amount (₱)')
            ->assertDontSee('Target Amount ($)');
    }

    // The same form on a foreign trip is ready for that destination's currency.
    public function test_the_expense_form_defaults_to_the_destinations_currency(): void
    {
        $user = User::factory()->create();
        Trip::factory()->create([
            'user_id'              => $user->id,
            'destination'          => 'Tokyo',
            'destination_currency' => 'JPY',
        ]);

        $this->actingAs($user)->get(route('expenses.create'))
            ->assertOk()
            ->assertSee('value="JPY" selected', false);
    }

    public function test_currency_helpers_ignore_a_stale_account_preference(): void
    {
        $user = User::factory()->create(['currency_code' => 'USD', 'currency_symbol' => '$']);
        $this->actingAs($user);

        $this->assertSame('₱', currency_symbol());
        $this->assertSame('PHP', currency_code());
    }

    // A failing provider used to be retried on every single call — 26 times on
    // one trip-planner render, each with a 10s timeout, on a tier that allows
    // 8 requests a minute.
    public function test_a_failing_rate_lookup_is_only_attempted_once(): void
    {
        config(['services.currency_converter.key' => 'test-key']);
        Http::fake(['api.twelvedata.com/*' => Http::response([], 500)]);

        $service = new CurrencyConverterService();
        for ($i = 0; $i < 10; $i++) {
            $this->assertNull($service->rateToPhp('JPY'));
        }

        Http::assertSentCount(1);
    }

    public function test_a_successful_rate_is_only_fetched_once(): void
    {
        config(['services.currency_converter.key' => 'test-key']);
        Http::fake(['api.twelvedata.com/*' => Http::response(['rate' => 0.38], 200)]);

        $service = new CurrencyConverterService();
        for ($i = 0; $i < 10; $i++) {
            $this->assertEqualsWithDelta(0.38, $service->rateToPhp('JPY'), 0.0001);
        }

        Http::assertSentCount(1);
    }

    // Separate currencies must not share a negative-cache entry.
    public function test_one_currency_failing_does_not_block_another(): void
    {
        config(['services.currency_converter.key' => 'test-key']);
        Http::fake([
            'api.twelvedata.com/exchange_rate?symbol=JPY*' => Http::response([], 500),
            'api.twelvedata.com/*'                         => Http::response(['rate' => 44.5], 200),
        ]);

        $service = new CurrencyConverterService();
        $this->assertNull($service->rateToPhp('JPY'));
        $this->assertEqualsWithDelta(44.5, $service->rateToPhp('CAD'), 0.0001);
    }
}
