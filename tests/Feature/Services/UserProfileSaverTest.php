<?php
namespace Tests\Feature\Services;

use App\Models\User;
use App\Models\UserProfile;
use App\Services\CurrencyConverterService;
use App\Services\UserProfileSaver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The currency rules here were lifted out of ProfileBuilder::persistProfile()
 * so the AI planner could save through the identical path. These cover the
 * service directly, so a drift shows up here as well as in the two callers.
 */
class UserProfileSaverTest extends TestCase
{
    use RefreshDatabase;

    private function attributes(array $overrides = []): array
    {
        return array_merge([
            'home_city'                => 'Manila',
            'daily_budget'             => 1500,
            'travel_style'             => 'Solo',
            'group_member_emails'      => [],
            'interests'                => ['Beach'],
            'sub_interests'            => [],
            'preferred_transportation' => 'Flight',
            'preferred_accommodation'  => 'Hotel',
        ], $overrides);
    }

    public function test_a_philippine_home_city_stores_pesos_and_leaves_the_local_columns_null(): void
    {
        $user = User::factory()->create(['country' => 'Philippines']);

        $result = (new UserProfileSaver())->save($user, $this->attributes(['daily_budget' => 50000]));

        $this->assertTrue($result['ok']);
        $profile = UserProfile::where('user_id', $user->id)->first();
        $this->assertSame(50000.0, $profile->daily_budget);
        $this->assertNull($profile->daily_budget_currency);
        $this->assertNull($profile->daily_budget_local);
    }

    public function test_a_foreign_home_city_converts_to_pesos_and_preserves_the_original(): void
    {
        $user = User::factory()->create(['country' => 'Japan']);
        Http::fake([
            'api.twelvedata.com/*' => Http::response(['symbol' => 'JPY/PHP', 'rate' => 0.38], 200),
        ]);

        $result = (new UserProfileSaver())->save($user, $this->attributes([
            'home_city' => 'Tokyo', 'daily_budget' => 50000,
        ]));

        $this->assertTrue($result['ok']);
        $profile = UserProfile::where('user_id', $user->id)->first();
        $this->assertSame(19000.0, $profile->daily_budget);
        $this->assertSame('JPY', $profile->daily_budget_currency);
        $this->assertSame(50000.0, $profile->daily_budget_local);
    }

    // Refuse rather than guess: a yen figure written into a peso column would
    // be silently wrong by the whole exchange rate.
    public function test_it_refuses_to_save_when_no_rate_is_available(): void
    {
        $user = User::factory()->create(['country' => 'Japan']);
        Http::fake([
            'api.twelvedata.com/*' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $result = (new UserProfileSaver())->save($user, $this->attributes([
            'home_city' => 'Tokyo', 'daily_budget' => 50000,
        ]));

        $this->assertFalse($result['ok']);
        $this->assertIsString($result['error']);
        $this->assertNotSame('', $result['error']);
        $this->assertNull(UserProfile::where('user_id', $user->id)->first());
    }

    // Same foreign amount as the previous save and the provider is down now:
    // keep the peso figure that WAS derived from a live rate, rather than
    // refusing or re-guessing.
    public function test_an_outage_reuses_the_previously_converted_figure(): void
    {
        $user = User::factory()->create(['country' => 'Canada']);

        Http::fakeSequence('api.twelvedata.com/*')
            ->push(['rate' => 44.49445], 200)
            ->pushStatus(500)->pushStatus(500)->pushStatus(500);

        $saver = new UserProfileSaver();
        $saver->save($user, $this->attributes(['home_city' => 'Vancouver', 'daily_budget' => 500]));

        Cache::flush();
        CurrencyConverterService::forgetMemo();
        $this->assertNull((new CurrencyConverterService())->rateToPhp('CAD'),
            'guard: the provider must really be failing for this test to mean anything');
        CurrencyConverterService::forgetMemo();
        Cache::flush();

        $result = $saver->save($user, $this->attributes(['home_city' => 'Vancouver', 'daily_budget' => 500]));

        $this->assertTrue($result['ok']);
        $profile = UserProfile::where('user_id', $user->id)->first();
        $this->assertSame('CAD', $profile->daily_budget_currency);
        $this->assertEqualsWithDelta(500.0, $profile->daily_budget_local, 0.5);
        $this->assertEqualsWithDelta(22247.23, $profile->daily_budget, 0.5);
    }

    public function test_group_emails_are_only_stored_for_a_group_travel_style(): void
    {
        $user = User::factory()->create(['country' => 'Philippines']);

        (new UserProfileSaver())->save($user, $this->attributes([
            'travel_style'        => 'Solo',
            'group_member_emails' => ['friend@example.com'],
        ]));

        $this->assertSame([], UserProfile::where('user_id', $user->id)->first()->group_member_emails);
    }

    public function test_newly_added_registered_companions_are_notified_once(): void
    {
        $user   = User::factory()->create(['country' => 'Philippines']);
        $friend = User::factory()->create(['email' => 'friend@example.com']);

        $saver = new UserProfileSaver();
        $attributes = $this->attributes([
            'travel_style'        => 'Group',
            'group_member_emails' => ['friend@example.com'],
        ]);

        $saver->save($user, $attributes);
        // Re-saving the same list must not notify the same person again.
        $saver->save($user, $attributes);

        $this->assertSame(1, \App\Models\Notification::where('user_id', $friend->id)
            ->where('type', 'group_member_added')->count());
    }

    public function test_budget_symbol_follows_the_home_city(): void
    {
        $this->assertSame('¥', UserProfileSaver::budgetSymbolForHomeCity('Tokyo'));
        $this->assertSame('₱', UserProfileSaver::budgetSymbolForHomeCity('Manila'));
        $this->assertSame('₱', UserProfileSaver::budgetSymbolForHomeCity(''));
    }
}
