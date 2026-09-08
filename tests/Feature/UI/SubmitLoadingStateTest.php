<?php
namespace Tests\Feature\UI;

use App\Livewire\Traveler\SavingsGoalManager;
use App\Models\SavingsGoal;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Submit buttons that sit through a round-trip.
 *
 * Both of these do real work before they answer — the deposit revalidates
 * against a live exchange rate, the expense form uploads a receipt — so a
 * button that looks inert while it waits invites a second click. Each shows
 * a spinner in place of its label, never alongside it.
 */
class SubmitLoadingStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_savings_swaps_its_label_for_a_spinner(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id, 'total_cost' => 40000]);
        $goal = SavingsGoal::create([
            'user_id'         => $user->id,
            'trip_id'         => $trip->id,
            'goal_name'       => 'Cebu fund',
            'target_amount'   => 40000,
            'current_savings' => 1000,
            'deadline'        => now()->addMonths(3)->toDateString(),
        ]);

        $html = Livewire::actingAs($user)
            ->test(SavingsGoalManager::class, ['goal' => $goal])
            ->call('openDeposit')
            ->html();

        $this->assertStringContainsString(
            '<span wire:loading.remove wire:target="submitDeposit">Add Savings</span>',
            $html
        );
        $this->assertStringContainsString(
            '<i class="fa-solid fa-spinner fa-spin" wire:loading wire:target="submitDeposit"></i>',
            $html
        );
        // A second click while the first is in flight would deposit twice.
        $this->assertStringContainsString('wire:loading.attr="disabled"', $html);
    }

    public function test_the_expense_form_keeps_its_spinner_only_submit_state(): void
    {
        $src = file_get_contents(
            resource_path('views/traveler/expenses/create.blade.php')
        );

        // The label is replaced, not joined — no wording next to the spinner.
        $this->assertStringContainsString(
            "btn.innerHTML = '<i class=\"fa-solid fa-spinner fa-spin\"></i>'",
            $src
        );
        $this->assertStringContainsString('btn.disabled = true;', $src);
    }
}
