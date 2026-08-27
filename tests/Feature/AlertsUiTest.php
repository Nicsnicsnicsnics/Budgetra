<?php
namespace Tests\Feature;

use App\Livewire\Traveler\NotificationBadge;
use App\Models\Expense;
use App\Models\Notification;
use App\Models\Trip;
use App\Models\TripBudget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AlertsUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_alerts_page_loads(): void
    {
        $user = User::factory()->create();
        \App\Models\UserProfile::create(['user_id' => $user->id]);
        Trip::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user)->get('/alerts')->assertStatus(200)->assertSee('Alerts');
    }

    public function test_badge_shows_unread_count(): void
    {
        $user = User::factory()->create();
        Notification::create([
            'user_id' => $user->id, 'trip_id' => null,
            'type' => 'budget_warning', 'message' => 'Test alert', 'is_read' => false,
        ]);
        Livewire::actingAs($user)
            ->test(NotificationBadge::class)
            ->assertSee('1');
    }

    public function test_badge_hidden_when_no_unread(): void
    {
        $user = User::factory()->create();
        $html = Livewire::actingAs($user)
            ->test(NotificationBadge::class)
            ->html();
        $this->assertStringNotContainsString('notif-badge', $html);
    }

    public function test_50_percent_threshold_fires_notification(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        TripBudget::create([
            'trip_id'        => $trip->id,
            'category'       => 'Food',
            'estimated_cost' => 1000,
            'actual_spent'   => 0,
        ]);

        $expense = Expense::create([
            'trip_id'      => $trip->id,
            'user_id'      => $user->id,
            'amount'       => 500,
            'category'     => 'Food',
            'description'  => 'Lunch',
            'expense_date' => now()->toDateString(),
        ]);

        \App\Observers\ExpenseObserver::syncBudgetForExpense($expense);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'trip_id' => $trip->id,
            'type'    => 'budget_warning',
        ]);
    }

    public function test_80_percent_threshold_still_fires(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        TripBudget::create([
            'trip_id'        => $trip->id,
            'category'       => 'Food',
            'estimated_cost' => 1000,
            'actual_spent'   => 0,
        ]);

        $expense = Expense::create([
            'trip_id'      => $trip->id,
            'user_id'      => $user->id,
            'amount'       => 800,
            'category'     => 'Food',
            'description'  => 'Dinner',
            'expense_date' => now()->toDateString(),
        ]);

        \App\Observers\ExpenseObserver::syncBudgetForExpense($expense);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type'    => 'budget_alert',
        ]);
    }
}
