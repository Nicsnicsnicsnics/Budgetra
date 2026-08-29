<?php
namespace Tests\Feature\Livewire;

use App\Livewire\Traveler\SavingsGoalManager;
use App\Models\SavingsGoal;
use App\Models\Trip;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SavingsGoalManagerTest extends TestCase
{
    use RefreshDatabase;

    private function makeGoal(User $user, array $attrs = []): SavingsGoal
    {
        return SavingsGoal::create(array_merge([
            'user_id'         => $user->id,
            'trip_id'         => Trip::factory()->create(['user_id' => $user->id])->id,
            'goal_name'       => 'Test Fund',
            'target_amount'   => 10000,
            'current_savings' => 1000,
            'deadline'        => '2030-12-31',
        ], $attrs));
    }

    private function userWithProfile(): User
    {
        $user = User::factory()->create();
        UserProfile::create(['user_id' => $user->id]);
        return $user;
    }

    public function test_savings_index_loads(): void
    {
        $user = $this->userWithProfile();
        $this->actingAs($user)->get('/savings')->assertStatus(200)->assertSee('No savings goals yet');
    }

    public function test_deposit_modal_opens(): void
    {
        $user = User::factory()->create();
        $goal = $this->makeGoal($user);
        Livewire::actingAs($user)
            ->test(SavingsGoalManager::class, ['goal' => $goal])
            ->call('openDeposit')
            ->assertSet('showDeposit', true);
    }

    public function test_deposit_adds_to_savings(): void
    {
        $user = User::factory()->create();
        $goal = $this->makeGoal($user);
        Livewire::actingAs($user)
            ->test(SavingsGoalManager::class, ['goal' => $goal])
            ->set('depositAmount', 500)
            ->call('submitDeposit')
            ->assertSet('showDeposit', false);

        $this->assertDatabaseHas('savings_goals', ['id' => $goal->id, 'current_savings' => 1500]);
    }

    public function test_projection_modal_opens(): void
    {
        $user = User::factory()->create();
        $goal = $this->makeGoal($user);
        Livewire::actingAs($user)
            ->test(SavingsGoalManager::class, ['goal' => $goal])
            ->call('openProjection')
            ->assertSet('showProjection', true);
    }

    public function test_completed_goal_shows_on_index(): void
    {
        $user = $this->userWithProfile();
        $this->makeGoal($user, ['current_savings' => 10000, 'target_amount' => 10000]);
        $this->actingAs($user)->get('/savings')->assertStatus(200)->assertSee('Goal Reached!');
    }

    public function test_deposit_that_reaches_the_goal_sends_a_congratulations_notification(): void
    {
        $user = User::factory()->create();
        $goal = $this->makeGoal($user, ['goal_name' => 'Boracay Fund', 'target_amount' => 10000, 'current_savings' => 9500]);

        Livewire::actingAs($user)
            ->test(SavingsGoalManager::class, ['goal' => $goal])
            ->set('depositAmount', 500)
            ->call('submitDeposit');

        $notif = \App\Models\Notification::where('type', 'savings_goal_reached')->first();
        $this->assertNotNull($notif);
        $this->assertSame($user->id, $notif->user_id);
        $this->assertStringContainsString('Boracay Fund', $notif->message);
    }

    public function test_deposit_that_does_not_reach_the_goal_sends_no_notification(): void
    {
        $user = User::factory()->create();
        $goal = $this->makeGoal($user, ['target_amount' => 10000, 'current_savings' => 1000]);

        Livewire::actingAs($user)
            ->test(SavingsGoalManager::class, ['goal' => $goal])
            ->set('depositAmount', 500)
            ->call('submitDeposit');

        $this->assertSame(0, \App\Models\Notification::where('type', 'savings_goal_reached')->count());
    }

    public function test_further_deposits_past_the_goal_do_not_repeat_the_notification(): void
    {
        $user = User::factory()->create();
        $goal = $this->makeGoal($user, ['target_amount' => 10000, 'current_savings' => 9500]);

        // First deposit reaches the goal.
        Livewire::actingAs($user)->test(SavingsGoalManager::class, ['goal' => $goal])
            ->set('depositAmount', 500)->call('submitDeposit');

        // A second, separate deposit (fresh component mount, matching how a new
        // page load would see the already-updated goal) shouldn't re-notify.
        Livewire::actingAs($user)->test(SavingsGoalManager::class, ['goal' => $goal->fresh()])
            ->set('depositAmount', 100)->call('submitDeposit');

        $this->assertSame(1, \App\Models\Notification::where('type', 'savings_goal_reached')->count());
    }

    public function test_savings_goal_shows_destination_currency_conversion_for_foreign_trip(): void
    {
        config(['services.currency_converter.key' => 'test-key']);
        \Illuminate\Support\Facades\Http::fake([
            'api.twelvedata.com/*' => \Illuminate\Support\Facades\Http::response(['rate' => 0.38], 200),
        ]);

        $user = User::factory()->create(['country' => 'Philippines']);
        $trip = Trip::factory()->create([
            'user_id'              => $user->id,
            'destination'          => 'Tokyo',
            'destination_currency' => 'JPY',
            'total_cost'           => 133000,
        ]);
        $goal = SavingsGoal::create([
            'user_id'         => $user->id,
            'trip_id'         => $trip->id,
            'goal_name'       => 'Tokyo Trip',
            'target_amount'   => 133000,
            'current_savings' => 38000,
            'deadline'        => '2030-12-31',
        ]);

        $component = Livewire::actingAs($user)->test(SavingsGoalManager::class, ['goal' => $goal]);

        $this->assertTrue($component->instance()->hasCurrencyConversion());
        $this->assertSame('JPY', $component->instance()->destinationCurrency());
        $this->assertSame('PHP', $component->instance()->homeCurrency());

        // Target and saved displayed in PHP (home) with JPY destination conversion
        $this->assertSame('₱133,000.00', $component->instance()->displayHomeAmount(133000));
        $this->assertSame('¥350,000.00', $component->instance()->displayDestinationAmount(133000));
        $this->assertSame('¥100,000.00', $component->instance()->displayDestinationAmount(38000));

        $component->assertSee('₱133,000.00')
                  ->assertSee('¥350,000.00')
                  ->assertSee('1 JPY ≈ ₱0.38 PHP');
    }

    public function test_savings_goal_converts_total_cost_from_destination_to_canadian_traveler_currency(): void
    {
        config(['services.currency_converter.key' => 'test-key']);
        \Illuminate\Support\Facades\Http::fake([
            'api.twelvedata.com/exchange_rate?symbol=CAD*' => \Illuminate\Support\Facades\Http::response(['rate' => 44.5], 200),
            'api.twelvedata.com/*'                         => \Illuminate\Support\Facades\Http::response(['rate' => 0.38], 200),
        ]);

        $user = User::factory()->create(['country' => 'Canada']);
        $trip = Trip::factory()->create([
            'user_id'              => $user->id,
            'destination'          => 'Tokyo',
            'destination_currency' => 'JPY',
            'total_cost'           => 133500, // 3,000 CAD * 44.5 = 133,500 PHP -> 351,315.79 JPY
        ]);
        $goal = SavingsGoal::create([
            'user_id'         => $user->id,
            'trip_id'         => $trip->id,
            'goal_name'       => 'Tokyo Trip',
            'target_amount'   => 133500,
            'current_savings' => 44500,  // 1,000 CAD
            'deadline'        => '2030-12-31',
        ]);

        $component = Livewire::actingAs($user)->test(SavingsGoalManager::class, ['goal' => $goal]);

        $this->assertSame('CAD', $component->instance()->homeCurrency());
        $this->assertSame('JPY', $component->instance()->destinationCurrency());

        // Target and Saved in CAD
        $this->assertSame('C$3,000.00', $component->instance()->displayHomeAmount(133500));
        $this->assertSame('C$1,000.00', $component->instance()->displayHomeAmount(44500));

        // Destination equivalent in JPY
        $this->assertSame('¥351,315.79', $component->instance()->displayDestinationAmount(133500));

        $component->assertSee('C$3,000.00')
                  ->assertSee('C$1,000.00')
                  ->assertSee('¥351,315.79')
                  ->assertSee('1 JPY ≈ C$0.0085 CAD');
    }

    public function test_savings_goal_deposit_in_foreign_home_currency_converts_to_pesos(): void
    {
        config(['services.currency_converter.key' => 'test-key']);
        \Illuminate\Support\Facades\Http::fake([
            'api.twelvedata.com/exchange_rate?symbol=CAD*' => \Illuminate\Support\Facades\Http::response(['rate' => 44.5], 200),
            'api.twelvedata.com/*'                         => \Illuminate\Support\Facades\Http::response(['rate' => 0.38], 200),
        ]);

        $user = User::factory()->create(['country' => 'Canada']);
        $trip = Trip::factory()->create([
            'user_id'              => $user->id,
            'destination'          => 'Tokyo',
            'destination_currency' => 'JPY',
            'total_cost'           => 133500, // 3,000 CAD
        ]);
        $goal = SavingsGoal::create([
            'user_id'         => $user->id,
            'trip_id'         => $trip->id,
            'goal_name'       => 'Tokyo Trip',
            'target_amount'   => 133500,
            'current_savings' => 0,
            'deadline'        => '2030-12-31',
        ]);

        // Depositing 500 CAD (500 * 44.5 = 22,250 PHP)
        Livewire::actingAs($user)
            ->test(SavingsGoalManager::class, ['goal' => $goal])
            ->set('depositAmount', 500)
            ->call('submitDeposit');

        $this->assertDatabaseHas('savings_goals', [
            'id'              => $goal->id,
            'current_savings' => 22250,
        ]);
    }

    public function test_savings_goal_domestic_trip_has_no_redundant_conversion(): void
    {
        $user = User::factory()->create(['country' => 'Philippines']);
        $trip = Trip::factory()->create([
            'user_id'              => $user->id,
            'destination'          => 'Boracay',
            'destination_currency' => null,
            'total_cost'           => 10000,
        ]);
        $goal = SavingsGoal::create([
            'user_id'         => $user->id,
            'trip_id'         => $trip->id,
            'goal_name'       => 'Boracay Trip',
            'target_amount'   => 10000,
            'current_savings' => 2000,
            'deadline'        => '2030-12-31',
        ]);

        $component = Livewire::actingAs($user)->test(SavingsGoalManager::class, ['goal' => $goal]);

        $this->assertFalse($component->instance()->hasCurrencyConversion());
        $this->assertNull($component->instance()->conversionRateLabel());
        $component->assertSee('₱10,000.00')->assertSee('₱2,000.00');
    }
}

