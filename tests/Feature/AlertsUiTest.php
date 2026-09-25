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

    public function test_alerts_page_lists_a_waiting_notification(): void
    {
        $user = User::factory()->create();
        \App\Models\UserProfile::create(['user_id' => $user->id]);
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        Notification::create([
            'user_id' => $user->id, 'trip_id' => $trip->id,
            'type' => 'budget_warning', 'message' => 'Food is at 50% of its budget', 'is_read' => false,
        ]);

        $this->actingAs($user)->get('/notifications')
            ->assertStatus(200)
            ->assertSee('Food is at 50% of its budget');
    }

    public function test_alerts_page_shows_the_all_clear_when_nothing_is_waiting(): void
    {
        $user = User::factory()->create();
        \App\Models\UserProfile::create(['user_id' => $user->id]);
        Trip::factory()->create(['user_id' => $user->id]);
        // Saving a trip congratulates the traveller, so the all-clear state
        // only exists once those have been cleared out.
        Notification::where('user_id', $user->id)->delete();

        $this->actingAs($user)->get('/notifications')
            ->assertStatus(200)
            ->assertSee('All caught up!');
    }

    /**
     * This page was /alerts until the URL was brought in line with the name the
     * sidebar, the model and the notifications table have always used.
     */
    public function test_the_old_alerts_url_still_gets_you_there(): void
    {
        // Bookmarks, and one thing specific to this app: the sidebar sits
        // inside @persist, so a tab that was already open keeps the frozen
        // /alerts href until a full page reload.
        $this->actingAs(User::factory()->create())
            ->get('/alerts')
            ->assertRedirect('/notifications');
    }

    public function test_the_sidebar_still_carries_the_unread_badge(): void
    {
        // The badge is gated on the sidebar link's 'key', which the rename
        // changed. Nothing else asserts that gate, and if the key and the
        // comparison drift apart the badge just quietly stops rendering.
        $user = User::factory()->create();
        \App\Models\UserProfile::create(['user_id' => $user->id]);
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        Notification::create([
            'user_id' => $user->id, 'trip_id' => $trip->id,
            'type' => 'budget_warning', 'message' => 'Unread one', 'is_read' => false,
        ]);

        $html = $this->actingAs($user)->get('/notifications')->getContent();

        $this->assertStringContainsString('href="' . url('/notifications') . '"', $html);
        $this->assertStringContainsString('data-segment="notifications"', $html);
        $this->assertStringContainsString('sidebar-badge', $html);
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
