<?php
namespace Tests\Feature\Alert;

use App\Models\Notification;
use App\Models\Trip;
use App\Models\TripBudget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_alerts_page_loads(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/alerts')->assertStatus(200);
    }

    public function test_alert_is_created_when_budget_threshold_exceeded(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        TripBudget::create(['trip_id' => $trip->id, 'category' => 'Food', 'estimated_cost' => 1000, 'actual_spent' => 0]);

        // Add expense that pushes actual_spent to 800 (80%)
        $this->actingAs($user)->post('/expenses', [
            'trip_id'      => $trip->id,
            'amount'       => 800,
            'category'     => 'Food',
            'expense_date' => '2026-08-01',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type'    => 'budget_alert',
        ]);
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        $notif = Notification::create([
            'user_id' => $user->id, 'trip_id' => $trip->id,
            'type' => 'budget_alert', 'message' => 'Test alert', 'is_read' => false,
        ]);

        $this->actingAs($user)
            ->from(route('alerts.index'))
            ->patch("/alerts/{$notif->id}/read")
            ->assertRedirect(route('alerts.index'));
        $this->assertDatabaseHas('notifications', ['id' => $notif->id, 'is_read' => true]);
    }

    // Marking read/all-read used to always bounce back to the default
    // (most-recent) trip's alerts page, even if you were viewing a different
    // trip — this confirms it now stays on whichever trip page you came from.
    public function test_marking_read_stays_on_the_trip_you_were_viewing(): void
    {
        $user   = User::factory()->create();
        $recent = Trip::factory()->create(['user_id' => $user->id, 'start_date' => now()->addDays(30)]);
        $older  = Trip::factory()->create(['user_id' => $user->id, 'start_date' => now()->addDays(5)]);
        $notif  = Notification::create([
            'user_id' => $user->id, 'trip_id' => $older->id,
            'type' => 'expense_added', 'message' => 'Test alert', 'is_read' => false,
        ]);

        $referer = route('alerts.index', ['trip_id' => $older->id]);

        $this->actingAs($user)
            ->from($referer)
            ->patch("/alerts/{$notif->id}/read")
            ->assertRedirect($referer);
    }

    public function test_marking_all_read_stays_on_the_trip_you_were_viewing(): void
    {
        $user   = User::factory()->create();
        $recent = Trip::factory()->create(['user_id' => $user->id, 'start_date' => now()->addDays(30)]);
        $older  = Trip::factory()->create(['user_id' => $user->id, 'start_date' => now()->addDays(5)]);
        Notification::create([
            'user_id' => $user->id, 'trip_id' => $older->id,
            'type' => 'expense_added', 'message' => 'Test alert', 'is_read' => false,
        ]);

        $referer = route('alerts.index', ['trip_id' => $older->id]);

        $this->actingAs($user)
            ->from($referer)
            ->patch('/alerts/read-all')
            ->assertRedirect($referer);
    }
}
