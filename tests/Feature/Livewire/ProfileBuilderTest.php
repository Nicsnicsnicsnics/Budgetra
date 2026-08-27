<?php
namespace Tests\Feature\Livewire;

use App\Livewire\Traveler\ProfileBuilder;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\CurrencyConverterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_suggests_philippine_cities_when_user_has_no_saved_country(): void
    {
        $user = User::factory()->create(['country' => null]);

        $component = Livewire::actingAs($user)->test(ProfileBuilder::class);

        $component->assertViewHas('suggested', ['Manila', 'Cebu City', 'Davao City']);
        $component->assertViewHas('localDestinations', fn ($cities) => count($cities) === 12
            && $cities['MNL'] === 'Manila' && $cities['IAO'] === 'Siargao');
    }

    // The exact bug reported live: Philippines used to exist as TWO separate
    // lists (a 12-city one in ProfileBuilder.php, a 5-city one in
    // country_cities.php) — a traveler who explicitly picked "Philippines"
    // at registration saw fewer cities than one who left it blank. Now
    // there's only one Philippines list, so both cases must match exactly.
    public function test_explicitly_picking_philippines_shows_the_same_full_list_as_not_picking_a_country(): void
    {
        $noCountry = User::factory()->create(['country' => null]);
        $pickedPhilippines = User::factory()->create(['country' => 'Philippines']);

        $a = Livewire::actingAs($noCountry)->test(ProfileBuilder::class);
        $b = Livewire::actingAs($pickedPhilippines)->test(ProfileBuilder::class);

        $this->assertSame($a->viewData('localDestinations'), $b->viewData('localDestinations'));
        $this->assertCount(12, $b->viewData('localDestinations'));
    }

    public function test_suggests_cities_from_the_users_saved_country(): void
    {
        $user = User::factory()->create(['country' => 'Canada']);

        $component = Livewire::actingAs($user)->test(ProfileBuilder::class);

        $component->assertViewHas('suggested', ['Toronto', 'Vancouver', 'Montreal']);
        $component->assertViewHas('localDestinations', fn ($cities) => $cities === [
            'YYZ' => 'Toronto', 'YVR' => 'Vancouver', 'YUL' => 'Montreal',
        ]);
    }

    public function test_suggests_cities_for_a_different_country(): void
    {
        $user = User::factory()->create(['country' => 'Japan']);

        $component = Livewire::actingAs($user)->test(ProfileBuilder::class);

        $component->assertViewHas('suggested', ['Tokyo', 'Osaka', 'Sapporo']);
    }

    public function test_falls_back_to_philippine_cities_for_an_unrecognized_country(): void
    {
        $user = User::factory()->create(['country' => 'Atlantis']);

        $component = Livewire::actingAs($user)->test(ProfileBuilder::class);

        $component->assertViewHas('suggested', ['Manila', 'Cebu City', 'Davao City']);
    }

    // The plan agreed on: a budget typed while the traveler's starting
    // point is a foreign city gets converted to pesos for daily_budget
    // (so Admin/Savings Goals/TARA keep working), but the raw number and
    // its real currency are ALSO preserved untouched in separate columns —
    // never silently overwritten, per the "don't be biased" concern raised.
    public function test_confirm_profile_converts_a_foreign_home_city_budget_to_pesos_and_preserves_the_original(): void
    {
        $user = User::factory()->create(['country' => 'Japan']);
        Http::fake([
            'api.twelvedata.com/*' => Http::response(['symbol' => 'JPY/PHP', 'rate' => 0.38], 200),
        ]);

        Livewire::actingAs($user)->test(ProfileBuilder::class)
            ->set('homeCity', 'Tokyo')
            ->set('dailyBudget', 50000)
            ->call('confirmProfile');

        $profile = UserProfile::where('user_id', $user->id)->first();
        $this->assertSame(19000.0, $profile->daily_budget);
        $this->assertSame('JPY', $profile->daily_budget_currency);
        $this->assertSame(50000.0, $profile->daily_budget_local);
    }

    public function test_confirm_profile_leaves_local_currency_fields_null_for_a_philippine_home_city(): void
    {
        $user = User::factory()->create(['country' => 'Philippines']);

        Livewire::actingAs($user)->test(ProfileBuilder::class)
            ->set('homeCity', 'Manila')
            ->set('dailyBudget', 50000)
            ->call('confirmProfile');

        $profile = UserProfile::where('user_id', $user->id)->first();
        $this->assertSame(50000.0, $profile->daily_budget);
        $this->assertNull($profile->daily_budget_currency);
        $this->assertNull($profile->daily_budget_local);
    }

    // No hardcoded rates: if the live rate can't be fetched there is nothing
    // honest to store, so the save is refused and the traveller is told.
    //
    // This replaces an earlier test that asserted the raw number was kept as-is
    // (daily_budget = 50000.0, currency null). That assertion described the bug
    // rather than the policy: ¥50,000 sat in a peso column marked as pesos,
    // silently wrong by the whole exchange rate, with the currency discarded so
    // it could never be corrected. Refusing honours "don't guess" without
    // writing a number the app will read as pesos.
    public function test_confirm_profile_refuses_to_save_when_no_rate_is_available(): void
    {
        $user = User::factory()->create(['country' => 'Japan']);
        Http::fake([
            'api.twelvedata.com/*' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $component = Livewire::actingAs($user)->test(ProfileBuilder::class)
            ->set('homeCity', 'Tokyo')
            ->set('dailyBudget', 50000)
            ->call('confirmProfile');

        // Told, not silently mis-saved — and kept on the page rather than
        // redirected away as though it had worked.
        $component->assertSet('saveError', fn ($e) => is_string($e) && $e !== '');
        $component->assertNoRedirect();

        // Nothing written at all beats a yen figure recorded as pesos.
        $this->assertNull(UserProfile::where('user_id', $user->id)->first());
    }

    public function test_review_screen_shows_the_currency_symbol_matching_the_home_city(): void
    {
        $user = User::factory()->create(['country' => 'Japan']);

        $component = Livewire::actingAs($user)->test(ProfileBuilder::class)
            ->set('homeCity', 'Tokyo');

        $component->assertViewHas('budgetSymbol', '¥');
    }

    public function test_review_screen_shows_peso_symbol_when_home_city_has_no_known_currency(): void
    {
        $user = User::factory()->create(['country' => null]);

        $component = Livewire::actingAs($user)->test(ProfileBuilder::class)
            ->set('homeCity', 'Manila');

        $component->assertViewHas('budgetSymbol', '₱');
    }

    // The exact bug reported live: reopening the profile after a foreign
    // home-city budget was already converted pre-filled the edit field
    // with the PESO ledger number instead of the raw local number, so an
    // untouched re-save re-ran it through the CAD conversion a second
    // time (₱22,247 became ₱989,878). Re-mounting must show the original
    // local number back, not the already-converted peso one.
    public function test_reopening_the_profile_prefills_the_local_budget_not_the_peso_ledger_value(): void
    {
        $user = User::factory()->create(['country' => 'Canada']);
        Http::fake([
            'api.twelvedata.com/*' => Http::response(['symbol' => 'CAD/PHP', 'rate' => 44.49445], 200),
        ]);

        Livewire::actingAs($user)->test(ProfileBuilder::class)
            ->set('homeCity', 'Vancouver')
            ->set('dailyBudget', 500)
            ->call('confirmProfile');

        // A fresh lookup, not the same in-memory $user — matches a real
        // page reload, where auth()->user() is resolved from scratch and
        // can't be carrying a stale cached "no profile yet" relation from
        // before the save above.
        $reopened = Livewire::actingAs(User::find($user->id))->test(ProfileBuilder::class);
        $reopened->assertSet('dailyBudget', 500.0);
    }

    // Re-saving without touching the budget field must be idempotent —
    // the peso ledger value must not keep growing on every save.
    public function test_resaving_the_profile_unchanged_does_not_double_convert_the_budget(): void
    {
        $user = User::factory()->create(['country' => 'Canada']);
        Http::fake([
            'api.twelvedata.com/*' => Http::response(['symbol' => 'CAD/PHP', 'rate' => 44.49445], 200),
        ]);

        Livewire::actingAs($user)->test(ProfileBuilder::class)
            ->set('homeCity', 'Vancouver')
            ->set('dailyBudget', 500)
            ->call('confirmProfile');

        Livewire::actingAs(User::find($user->id))->test(ProfileBuilder::class)
            ->set('homeCity', 'Vancouver')
            ->call('confirmProfile');

        $profile = UserProfile::where('user_id', $user->id)->first();
        $this->assertEqualsWithDelta(22247.23, $profile->daily_budget, 0.5);
        $this->assertEqualsWithDelta(500.0, $profile->daily_budget_local, 0.5);
    }

    public function test_clearing_the_home_city_stays_cleared(): void
    {
        $user = User::factory()->create();

        // The field used to refill itself a second or two after being cleared:
        // Alpine held its own copy of homeCity alongside the Livewire property,
        // and a re-render resolved the disagreement in favour of the stale one.
        Livewire::actingAs($user)->test(ProfileBuilder::class)
            ->set('homeCity', 'Cebu City')
            ->set('homeCity', '')
            ->assertSet('homeCity', '')
            ->call('nextStep')
            ->assertSet('step', 1)          // an empty city must not advance
            ->assertSet('homeCity', '');    // and must not come back
    }

    public function test_step_indicator_has_a_connector_between_every_step(): void
    {
        $user = User::factory()->create();

        $html = Livewire::actingAs($user)->test(ProfileBuilder::class)->html();

        $dots       = substr_count($html, 'width:32px;height:32px;border-radius:50%');
        $connectors = substr_count($html, 'flex:1;height:3px;background:');

        // 7 dots need 6 connectors. It rendered 5, so 6 and 7 sat flush.
        $this->assertSame(7, $dots);
        $this->assertSame(6, $connectors);
    }

    // A provider outage used to rewrite a correct peso budget with the raw
    // foreign number and erase the currency metadata needed to recover it.
    public function test_a_rate_outage_does_not_overwrite_an_already_converted_budget(): void
    {
        $user = User::factory()->create(['country' => 'Canada']);

        Http::fakeSequence('api.twelvedata.com/*')
            ->push(['rate' => 44.49445], 200)
            ->pushStatus(500)->pushStatus(500)->pushStatus(500);

        Livewire::actingAs($user)->test(ProfileBuilder::class)
            ->set('homeCity', 'Vancouver')
            ->set('dailyBudget', 500)
            ->call('confirmProfile');

        // Both layers, or the second save silently reuses the rate the first one
        // fetched and this stops testing an outage at all.
        Cache::flush();
        CurrencyConverterService::forgetMemo();
        $this->assertNull((new CurrencyConverterService())->rateToPhp('CAD'),
            'guard: the provider must really be failing for this test to mean anything');
        CurrencyConverterService::forgetMemo();
        Cache::flush();

        Livewire::actingAs(User::find($user->id))->test(ProfileBuilder::class)
            ->call('confirmProfile');

        $profile = UserProfile::where('user_id', $user->id)->first();

        $this->assertSame('CAD', $profile->daily_budget_currency);
        $this->assertEqualsWithDelta(500.0, $profile->daily_budget_local, 0.5);
        $this->assertEqualsWithDelta(22247.23, $profile->daily_budget, 0.5);
    }

}
