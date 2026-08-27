<?php

namespace Tests\Feature;

use App\Livewire\Traveler\TripPlannerWizard;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The background draft save must agree with the real save. The budget field is
 * typed in the traveller's own currency, and autosaveDraft() used to bank the
 * raw number as pesos — so a Canadian's C$3,000 draft read as ₱3,000 while
 * saveItinerary() correctly recorded ₱133,500 for the same input.
 */
class DraftBudgetCurrencyTest extends TestCase
{
    use RefreshDatabase;

    private function plan(User $user): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::actingAs($user)->test(TripPlannerWizard::class)
            ->set('manualFrom', 'Toronto')
            ->set('manualTo', 'Tokyo')
            ->set('manualBudgetMin', '3000')
            ->set('manualBudgetMax', '3000')
            ->set('startDate', '2027-05-01')
            ->set('endDate', '2027-05-10');
    }

    public function test_a_draft_stores_the_budget_in_pesos_with_the_original_kept(): void
    {
        config(['services.currency_converter.key' => 'test-key']);
        Http::fake(['api.twelvedata.com/*' => Http::response(['rate' => 44.5], 200)]);

        $user = User::factory()->create(['country' => 'Canada']);
        $this->plan($user)->call('autosaveDraft');

        $draft = Trip::where('user_id', $user->id)->where('status', 'draft')->firstOrFail();

        $this->assertSame('CAD', $draft->budget_currency);
        $this->assertEqualsWithDelta(3000.0, (float) $draft->budget_local, 0.01);
        $this->assertEqualsWithDelta(133500.0, (float) $draft->budget_limit, 1.0);
        // The bug: 3000 banked as pesos.
        $this->assertNotEqualsWithDelta(3000.0, (float) $draft->budget_limit, 1.0);
    }

    public function test_the_draft_and_the_real_save_agree(): void
    {
        config(['services.currency_converter.key' => 'test-key']);
        Http::fake(['api.twelvedata.com/*' => Http::response(['rate' => 44.5], 200)]);

        $user = User::factory()->create(['country' => 'Canada']);
        $this->plan($user)->call('autosaveDraft');
        $draft = Trip::where('user_id', $user->id)->where('status', 'draft')->firstOrFail();

        $other = User::factory()->create(['country' => 'Canada']);
        $this->plan($other)->call('saveItinerary');
        $saved = Trip::where('user_id', $other->id)->firstOrFail();

        $this->assertEqualsWithDelta((float) $saved->budget_limit, (float) $draft->budget_limit, 1.0);
        $this->assertSame($saved->budget_currency, $draft->budget_currency);
    }

    public function test_a_filipino_draft_is_unchanged(): void
    {
        $user = User::factory()->create(['country' => 'Philippines']);

        Livewire::actingAs($user)->test(TripPlannerWizard::class)
            ->set('manualFrom', 'Manila')
            ->set('manualTo', 'Cebu City')
            ->set('manualBudgetMin', '30000')
            ->set('manualBudgetMax', '30000')
            ->set('startDate', '2027-05-01')
            ->set('endDate', '2027-05-10')
            ->call('autosaveDraft');

        $draft = Trip::where('user_id', $user->id)->where('status', 'draft')->firstOrFail();

        $this->assertEqualsWithDelta(30000.0, (float) $draft->budget_limit, 0.01);
        $this->assertSame('PHP', $draft->budget_currency);
        $this->assertNull($draft->budget_local);   // nothing was converted
    }

    public function test_no_draft_is_written_when_the_rate_is_unreachable(): void
    {
        config(['services.currency_converter.key' => 'test-key']);
        Http::fake(['api.twelvedata.com/*' => Http::response([], 500)]);

        $user = User::factory()->create(['country' => 'Canada']);
        $this->plan($user)->call('autosaveDraft');

        // Skipping a background draft beats banking a foreign number as pesos.
        // The real save still refuses loudly, with a message.
        $this->assertSame(0, Trip::where('user_id', $user->id)->count());
    }
}
