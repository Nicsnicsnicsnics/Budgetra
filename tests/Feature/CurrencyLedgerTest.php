<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use App\Services\CurrencyConverterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
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
    public function test_amount_inputs_take_their_currency_from_the_trip(): void
    {
        // There is no account preference left to ignore — the columns that
        // held it are dropped (see the 2026_09_26 migration), which is the
        // structural version of this guarantee. What is still worth pinning
        // is where the forms DO get their currency: the trip.
        $user = User::factory()->create();
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

    public function test_the_ledger_helpers_always_say_pesos(): void
    {
        // Everything is STORED in pesos, whatever country the traveller
        // registered with. These two report that ledger, and they are not the
        // traveller's own currency — home_currency() is, and the test below
        // keeps the two from being confused again.
        $this->actingAs(User::factory()->create(['country' => 'United States']));

        $this->assertSame('₱', currency_symbol());
        $this->assertSame('PHP', currency_code());
    }

    public function test_the_travellers_own_currency_comes_from_their_country(): void
    {
        // The pair above reading PHP for an American account is correct and
        // was mistaken for a bug when it sat in a database column next to
        // "United States". This is the value that actually answers "what
        // currency is this traveller in", and it is derived, never stored.
        $this->actingAs(User::factory()->create(['country' => 'United States']));
        $this->assertSame('USD', home_currency());

        $this->actingAs(User::factory()->create(['country' => 'Japan']));
        $this->assertSame('JPY', home_currency());

        // country is nullable at registration, so PHP is the honest default.
        $this->actingAs(User::factory()->create(['country' => null]));
        $this->assertSame('PHP', home_currency());
    }

    public function test_the_account_currency_columns_are_gone(): void
    {
        // They never converted anything — they relabelled peso figures,
        // including amount INPUTS, so a traveller typing 100 for a $100
        // dinner stored 100 pesos. Nothing may start reading them again.
        $this->assertFalse(Schema::hasColumn('users', 'currency_code'));
        $this->assertFalse(Schema::hasColumn('users', 'currency_symbol'));
        $this->assertNotContains('currency_code', (new User())->getFillable());
        $this->assertNotContains('currency_symbol', (new User())->getFillable());
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
