<?php
namespace Tests\Feature\Expense;

use App\Models\Expense;
use App\Models\Trip;
use App\Models\TripBudget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_expense_index_loads(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/expenses')->assertStatus(200);
    }

    public function test_user_can_create_expense_and_budget_is_synced(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        TripBudget::create(['trip_id' => $trip->id, 'category' => 'Transportation', 'estimated_cost' => 5000, 'actual_spent' => 0]);

        $response = $this->actingAs($user)->post('/expenses', [
            'trip_id'      => $trip->id,
            'amount'       => 1500,
            'category'     => 'Transportation',
            'description'  => 'Bus fare',
            'expense_date' => '2026-08-03',
        ]);

        $response->assertRedirect(route('expenses.index'));
        $this->assertDatabaseHas('expenses', ['amount' => 1500, 'category' => 'Transportation']);
        $this->assertDatabaseHas('trip_budgets', ['trip_id' => $trip->id, 'category' => 'Transportation', 'actual_spent' => 1500]);
    }

    public function test_deleting_expense_reverses_budget_sync(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        TripBudget::create(['trip_id' => $trip->id, 'category' => 'Food', 'estimated_cost' => 3000, 'actual_spent' => 800]);
        $expense = Expense::create([
            'trip_id' => $trip->id, 'user_id' => $user->id,
            'amount' => 800, 'category' => 'Food',
            'expense_date' => '2026-08-02',
        ]);

        $this->actingAs($user)->delete("/expenses/{$expense->id}");

        $this->assertDatabaseHas('trip_budgets', ['trip_id' => $trip->id, 'category' => 'Food', 'actual_spent' => 0]);
        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }

    public function test_index_ignores_a_malformed_date_filter_instead_of_crashing(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        Expense::create(['trip_id' => $trip->id, 'user_id' => $user->id, 'amount' => 100, 'category' => 'Food', 'expense_date' => '2026-08-01']);

        $this->actingAs($user)
            ->get('/expenses?' . http_build_query(['trip_id' => $trip->id, 'date_from' => 'notarealdate', 'date_to' => 'alsobad']))
            ->assertStatus(200);
    }

    public function test_index_defaults_to_first_trip_with_multiple_trips_and_no_trip_id(): void
    {
        $user  = User::factory()->create();
        $trip1 = Trip::factory()->create(['user_id' => $user->id]);
        $trip2 = Trip::factory()->create(['user_id' => $user->id]);
        Expense::create(['trip_id' => $trip1->id, 'user_id' => $user->id, 'amount' => 100, 'category' => 'Food', 'expense_date' => '2026-08-01']);
        Expense::create(['trip_id' => $trip2->id, 'user_id' => $user->id, 'amount' => 200, 'category' => 'Food', 'expense_date' => '2026-08-01']);

        $response = $this->actingAs($user)->get('/expenses');

        $shown = $response->viewData('expenses');
        $this->assertEquals(1, $shown->total());
        $this->assertEquals($trip1->id, $shown->first()->trip_id);
    }

    public function test_updating_expense_amount_resyncs_budget(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        TripBudget::create(['trip_id' => $trip->id, 'category' => 'Food', 'estimated_cost' => 10000, 'actual_spent' => 0]);
        $expense = Expense::create([
            'trip_id' => $trip->id, 'user_id' => $user->id,
            'amount' => 500, 'category' => 'Food', 'expense_date' => '2026-08-01',
        ]);
        \App\Observers\ExpenseObserver::syncBudgetForExpense($expense);

        $this->actingAs($user)->put("/expenses/{$expense->id}", [
            'trip_id' => $trip->id, 'amount' => 5000, 'category' => 'Food', 'expense_date' => '2026-08-01',
        ]);

        $this->assertDatabaseHas('trip_budgets', ['trip_id' => $trip->id, 'category' => 'Food', 'actual_spent' => 5000]);
    }

    public function test_updating_expense_category_moves_budget_between_categories(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        TripBudget::create(['trip_id' => $trip->id, 'category' => 'Food',     'estimated_cost' => 10000, 'actual_spent' => 0]);
        TripBudget::create(['trip_id' => $trip->id, 'category' => 'Shopping', 'estimated_cost' => 10000, 'actual_spent' => 0]);
        $expense = Expense::create([
            'trip_id' => $trip->id, 'user_id' => $user->id,
            'amount' => 1200, 'category' => 'Food', 'expense_date' => '2026-08-01',
        ]);
        \App\Observers\ExpenseObserver::syncBudgetForExpense($expense);

        $this->actingAs($user)->put("/expenses/{$expense->id}", [
            'trip_id' => $trip->id, 'amount' => 1200, 'category' => 'Shopping', 'expense_date' => '2026-08-01',
        ]);

        $this->assertDatabaseHas('trip_budgets', ['trip_id' => $trip->id, 'category' => 'Food',     'actual_spent' => 0]);
        $this->assertDatabaseHas('trip_budgets', ['trip_id' => $trip->id, 'category' => 'Shopping', 'actual_spent' => 1200]);
    }

    public function test_user_cannot_reassign_expense_to_another_users_trip(): void
    {
        $attacker     = User::factory()->create();
        $victim       = User::factory()->create();
        $attackerTrip = Trip::factory()->create(['user_id' => $attacker->id]);
        $victimTrip   = Trip::factory()->create(['user_id' => $victim->id]);
        $expense = Expense::create([
            'trip_id' => $attackerTrip->id, 'user_id' => $attacker->id,
            'amount' => 100, 'category' => 'Food', 'expense_date' => '2026-08-01',
        ]);

        $this->actingAs($attacker)->put("/expenses/{$expense->id}", [
            'trip_id' => $victimTrip->id, 'amount' => 100, 'category' => 'Food', 'expense_date' => '2026-08-01',
        ])->assertStatus(403);

        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'trip_id' => $attackerTrip->id]);
    }

    public function test_user_cannot_delete_another_users_expense(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $trip  = Trip::factory()->create(['user_id' => $other->id]);
        $expense = Expense::create([
            'trip_id' => $trip->id, 'user_id' => $other->id,
            'amount' => 100, 'category' => 'Food', 'expense_date' => '2026-08-01',
        ]);

        $this->actingAs($user)->delete("/expenses/{$expense->id}")->assertStatus(403);
    }

    public function test_creating_expense_sends_a_notification(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id, 'destination' => 'Boracay']);

        $this->actingAs($user)->post('/expenses', [
            'trip_id'      => $trip->id,
            'amount'       => 250,
            'category'     => 'Food',
            'expense_date' => '2026-08-01',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'trip_id' => $trip->id,
            'type'    => 'expense_added',
            'is_read' => false,
        ]);
        $notif = \App\Models\Notification::where('type', 'expense_added')->first();
        $this->assertStringContainsString('250.00', $notif->message);
        $this->assertStringContainsString('Food', $notif->message);
        $this->assertStringContainsString('Boracay', $notif->message);
    }

    public function test_exceeding_overall_trip_budget_sends_a_notification(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id, 'destination' => 'Bohol', 'budget_limit' => 1000]);

        $this->actingAs($user)->post('/expenses', [
            'trip_id'      => $trip->id,
            'amount'       => 1200,
            'category'     => 'Food',
            'expense_date' => '2026-08-01',
        ]);

        $notif = \App\Models\Notification::where('type', 'budget_alert')
            ->where('message', 'like', '%exceeded your overall budget%')
            ->first();
        $this->assertNotNull($notif);
        $this->assertSame($trip->id, $notif->trip_id);
        $this->assertStringContainsString('Bohol', $notif->message);
        $this->assertStringContainsString('1,200.00', $notif->message);
        $this->assertStringContainsString('1,000.00', $notif->message);
    }

    public function test_overall_budget_notification_is_not_duplicated_on_further_expenses(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id, 'budget_limit' => 1000]);

        $this->actingAs($user)->post('/expenses', [
            'trip_id' => $trip->id, 'amount' => 1200, 'category' => 'Food', 'expense_date' => '2026-08-01',
        ]);
        $this->actingAs($user)->post('/expenses', [
            'trip_id' => $trip->id, 'amount' => 100, 'category' => 'Food', 'expense_date' => '2026-08-02',
        ]);

        $count = \App\Models\Notification::where('type', 'budget_alert')
            ->where('message', 'like', '%exceeded your overall budget%')
            ->count();
        $this->assertSame(1, $count);
    }

    public function test_staying_under_overall_budget_does_not_send_the_exceeded_notification(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id, 'budget_limit' => 5000]);

        $this->actingAs($user)->post('/expenses', [
            'trip_id' => $trip->id, 'amount' => 1200, 'category' => 'Food', 'expense_date' => '2026-08-01',
        ]);

        $notif = \App\Models\Notification::where('type', 'budget_alert')
            ->where('message', 'like', '%exceeded your overall budget%')
            ->first();
        $this->assertNull($notif);
    }

    public function test_deleting_expense_does_not_create_a_notification(): void
    {
        $user    = User::factory()->create();
        $trip    = Trip::factory()->create(['user_id' => $user->id]);
        $expense = Expense::create([
            'trip_id' => $trip->id, 'user_id' => $user->id,
            'amount' => 100, 'category' => 'Food', 'expense_date' => '2026-08-01',
        ]);
        // Creating it above already fired one 'expense_added' notification —
        // clear it so deletion's behavior can be asserted cleanly.
        \App\Models\Notification::query()->delete();

        $this->actingAs($user)->delete("/expenses/{$expense->id}");

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_store_validates_required_fields(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->post('/expenses', []);
        $response->assertSessionHasErrors(['trip_id', 'amount', 'category', 'expense_date']);
    }

    // The new dashboard's Add/Edit modal sends an always-present but empty
    // _method field in add mode (Alpine binds it to '' rather than omitting
    // it) — confirm Laravel's method-override falls back to the real POST
    // instead of erroring on the empty value.
    public function test_add_via_modal_shaped_payload_with_empty_method_field(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post('/expenses', [
            '_method'      => '',
            'trip_id'      => $trip->id,
            'amount'       => 777.50,
            'category'     => 'Food',
            'description'  => 'Modal add test',
            'expense_date' => '2026-08-01',
        ]);

        $response->assertRedirect(route('expenses.index'));
        $this->assertDatabaseHas('expenses', ['description' => 'Modal add test', 'amount' => 777.50]);
    }

    public function test_edit_via_modal_shaped_payload_with_method_put(): void
    {
        $user    = User::factory()->create();
        $trip    = Trip::factory()->create(['user_id' => $user->id]);
        $expense = Expense::create([
            'trip_id' => $trip->id, 'user_id' => $user->id,
            'amount' => 100, 'category' => 'Food', 'expense_date' => '2026-08-01',
        ]);

        $response = $this->actingAs($user)->post("/expenses/{$expense->id}", [
            '_method'      => 'PUT',
            'trip_id'      => $trip->id,
            'amount'       => 888.25,
            'category'     => 'Transportation',
            'description'  => 'Modal edit test',
            'expense_date' => '2026-08-02',
        ]);

        $response->assertRedirect(route('expenses.index'));
        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id, 'amount' => 888.25, 'category' => 'Transportation', 'description' => 'Modal edit test',
        ]);
    }
}
