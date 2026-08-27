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
}
