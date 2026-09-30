<?php
namespace Tests\Feature\Livewire;

use App\Livewire\Traveler\TripPlannerWizard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Emergency Fund step's way back.
 *
 * Every other step of the wizard carries a back link built on
 * backFromEdit(N - 1) — steps 2, 3, 4, 5, 7 and 8 all have one. Step 6 was
 * the single exception, so a traveler who wanted to revisit their attractions
 * had no way out of it short of restarting the wizard.
 */
class WizardEmergencyFundBackTest extends TestCase
{
    use RefreshDatabase;

    private function atEmergencyFund(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::actingAs(User::factory()->create())
            ->test(TripPlannerWizard::class)
            ->set('planningMode', 'manual')
            ->set('step', 6);
    }

    public function test_the_emergency_fund_step_offers_a_way_back(): void
    {
        $this->atEmergencyFund()
            ->assertSee('Emergency Fund')
            ->assertSee('Back to Select Attractions')
            ->assertSee('wire:click="backFromEdit(5)"', false);
    }

    public function test_it_lands_on_the_attractions_step(): void
    {
        $this->atEmergencyFund()
            ->call('backFromEdit', 5)
            ->assertSet('step', 5)
            ->assertSee('Select Attractions');
    }

    public function test_an_ai_edit_returns_to_the_ai_planner_instead(): void
    {
        // backFromEdit() already had this branch; the new button inherits it
        // rather than hard-coding a jump to step 5.
        session(['ai_edit_section' => 'emergency', 'ai_edit_return' => 'somewhere']);

        $this->atEmergencyFund()
            ->call('backFromEdit', 5)
            ->assertRedirect(route('trips.plan.ai'));

        $this->assertNull(session('ai_edit_section'));
    }

    public function test_every_step_from_two_onwards_now_has_one(): void
    {
        $html = $this->atEmergencyFund()->html();
        $this->assertStringContainsString('backFromEdit(5)', $html);

        // Guards the gap itself: the back affordances are sequential, so a
        // missing one shows up as a hole in this list.
        $template = file_get_contents(
            resource_path('views/livewire/traveler/trip-planner-wizard.blade.php')
        );
        foreach ([1, 2, 3, 4, 5, 6, 7] as $target) {
            $this->assertStringContainsString(
                'backFromEdit(' . $target . ')',
                $template,
                "step " . ($target + 1) . " has no way back to step {$target}"
            );
        }
    }

    // ── Where the button sits, not just that it exists ──────────────

    public function test_the_emergency_fund_link_sits_in_the_top_left_corner(): void
    {
        $html = $this->atEmergencyFund()->html();

        // Its own row above the centred block, not inside it: a button inside
        // a justify-content:center column rides down the page with the card.
        $this->assertStringContainsString('<div style="padding:20px 24px 0;">', $html);

        $back    = strpos($html, 'backFromEdit(5)');
        $centred = strpos($html, 'justify-content:center;min-height:calc(100vh - 160px)');

        $this->assertNotFalse($centred, 'the centred block lost the height the back row took from it');
        $this->assertLessThan($centred, $back, 'the back link slipped back inside the centred block');

        // The card keeps its width; the button no longer borrows it.
        $this->assertStringContainsString('max-width:680px', $html);
    }

    public function test_the_itinerary_back_link_leads_the_left_column(): void
    {
        // Steps 8 and 9 need far more wizard state than this class sets up, so
        // these two read the template the way test_every_step_from_two_onwards
        // already does. Placement is what is being pinned, and placement is
        // what the template holds.
        $template = $this->template();

        $left  = strpos($template, 'class="itin8-left"');
        $back  = strpos($template, 'Back to Generate Itinerary');
        $meta  = strpos($template, 'class="itin8-meta"');
        $right = strpos($template, 'class="itin8-right"');

        $this->assertNotFalse($left);
        $this->assertNotFalse($back);
        $this->assertNotFalse($meta);
        $this->assertNotFalse($right);

        // First thing in the left column, above the date line — and well
        // clear of the right column, which holds the cost card.
        $this->assertGreaterThan($left, $back, 'the back link left the left column');
        $this->assertLessThan($meta, $back, 'the back link slipped below the date line');
        $this->assertLessThan($right, $back, 'the back link drifted into the right column');
    }

    public function test_the_trip_summary_back_link_sits_opposite_confirm_trip(): void
    {
        $template = $this->template();

        // space-between is what pins the two to opposite margins; collapsing
        // it to flex-end is exactly how they end up clustered on the right.
        $this->assertStringContainsString(
            'justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;',
            $template
        );

        $step9 = strpos($template, 'STEP 9');
        $back  = strpos($template, 'Back to Suggested Itineraries', $step9);
        // The button's markup, not the bare label: a comment naming Confirm
        // Trip would otherwise satisfy this.
        $confirm = strpos($template, 'saveItinerary">Confirm Trip', $step9);

        $this->assertNotFalse($back);
        $this->assertNotFalse($confirm);
        $this->assertLessThan($confirm, $back, 'the back link should read before Confirm Trip');
    }

    public function test_the_generate_itinerary_link_is_out_of_the_centred_block_too(): void
    {
        // Steps 6 and 7 are the only two centred steps in the wizard, so they
        // are the only two where a back link can sink down the page. Fixing
        // one and not the other is the likely half-done version of this.
        $template = $this->template();
        $step7    = strpos($template, 'STEP 7');

        $back    = strpos($template, 'backFromEdit(6)', $step7);
        $centred = strpos($template, 'justify-content:center;min-height:calc(100vh - 160px)', $step7);

        $this->assertNotFalse($back);
        $this->assertNotFalse($centred, 'step 7 lost the height its back row took from it');
        $this->assertLessThan($centred, $back, 'step 7 back link slipped inside the centred block');
    }

    public function test_the_trip_summary_is_not_centred_away_from_the_left_edge(): void
    {
        // max-width + auto margins centred the whole step past 1200px, which
        // pushed this back link inwards on a wide monitor while step 8's,
        // which has no max-width, stayed put. The two pages sit next to each
        // other in the flow and disagreed only on large screens.
        $template = $this->template();
        $step9    = substr($template, strpos($template, 'STEP 9'));

        $this->assertStringNotContainsString('max-width:1200px;margin:0 auto', $step9);
    }

    public function test_every_search_button_shows_the_spinner_alone(): void
    {
        // Four steps each have one, and they are edited independently, so the
        // likely regression is three of them changing and one keeping its
        // "Searching" label.
        $template = $this->template();

        $this->assertStringNotContainsString('Searching</span>', $template);

        foreach ([
            'searchManualFlights',
            'searchAccommodations',
            'searchVenues',
            'searchAttractionsList',
        ] as $action) {
            $this->assertStringContainsString(
                'wire:loading.class="is-searching" wire:target="' . $action . '"',
                $template,
                "{$action} has no searching state"
            );
        }

        // Four buttons, four overlaid spinners, each announcing itself since
        // the visible label is hidden while it spins.
        $this->assertSame(4, substr_count($template, 'class="wiz-search-spinner" aria-label="Searching"'));
        $this->assertSame(4, substr_count($template, 'class="wiz-search-btn"'));
    }

    public function test_the_searching_button_keeps_its_width(): void
    {
        // The label is hidden, not removed: dropping to display:none would
        // collapse the button to spinner-width and shift the row it sits in
        // the moment it is clicked.
        $css = file_get_contents(public_path('css/style.css'));

        $this->assertStringContainsString(
            '.wiz-search-btn.is-searching > :not(.wiz-search-spinner) { visibility: hidden; }',
            $css
        );
        $this->assertStringContainsString('.wiz-search-btn { position: relative; }', $css);
    }

    private function template(): string
    {
        return file_get_contents(
            resource_path('views/livewire/traveler/trip-planner-wizard.blade.php')
        );
    }
}
