<?php
namespace Tests\Feature\Expense;

use App\Models\Expense;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The amount field groups thousands as you type, so the posted value can carry
 * separators. The browser strips them on submit; the controller strips them
 * again so a JS-less post is not rejected by `numeric` for an amount the
 * traveller entered correctly.
 */
class ExpenseAmountFormatTest extends TestCase
{
    use RefreshDatabase;

    private function payload(Trip $trip, string $amount): array
    {
        return [
            'trip_id'      => $trip->id,
            'amount'       => $amount,
            'category'     => 'Food',
            'expense_date' => now()->toDateString(),
            'description'  => 'Lunch',
        ];
    }

    public function test_an_amount_with_separators_is_accepted(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post('/expenses', $this->payload($trip, '232,423.50'))
            ->assertSessionHasNoErrors();

        $this->assertSame('232423.50', (string) Expense::first()->amount);
    }

    public function test_a_plain_amount_still_works(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post('/expenses', $this->payload($trip, '1500'))
            ->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(1500, (float) Expense::first()->amount, 0.001);
    }

    public function test_a_non_numeric_amount_is_still_rejected(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post('/expenses', $this->payload($trip, 'abc'))
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, Expense::count());
    }

    public function test_the_amount_field_is_not_a_number_input(): void
    {
        $user = User::factory()->create();
        Trip::factory()->create(['user_id' => $user->id]);

        $html = $this->actingAs($user)->get('/expenses/create')->assertStatus(200)->getContent();

        // A number input rejects the separators outright, so grouping while
        // typing is only possible on a text input.
        $this->assertStringContainsString('id="amount"', $html);
        $this->assertStringNotContainsString('type="number" id="amount"', $html);
        $this->assertStringContainsString('inputmode="decimal"', $html);
    }
}
