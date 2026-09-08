<?php
namespace Tests\Feature\Livewire;

use App\Livewire\Traveler\ProfileBuilder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The picture cards' border in each state, and the wait on Next Step.
 *
 * The red "you missed this" ring used to be an outer box-shadow, and the row
 * it sits in is overflow-x:auto — which clips vertically as well, so the ring
 * was sliced off along the top of every card. Selected cards had no ring at
 * all, only the small corner badge.
 */
class ProfileBuilderCardChromeTest extends TestCase
{
    use RefreshDatabase;

    private function html(int $step = 1): string
    {
        return Livewire::actingAs(User::factory()->create())
            ->test(ProfileBuilder::class)->set('step', $step)->html();
    }

    public function test_the_ring_is_painted_inside_the_card_not_around_it(): void
    {
        $css = $this->html();

        $this->assertStringContainsString('.int-card-img-bg{box-sizing:border-box;border:2px solid', $css);
        // The clipped outer ring is gone.
        $this->assertStringNotContainsString('.int-card-img.is-bad{box-shadow:0 0 0 2px', $css);
    }

    public function test_resting_selected_and_invalid_each_get_their_own_border(): void
    {
        $css = $this->html();

        $this->assertStringContainsString('.int-card-img:hover .int-card-img-bg{border-color:', $css);
        $this->assertStringContainsString('.int-card-img.active .int-card-img-bg{border-color:var(--primary);}', $css);
        $this->assertStringContainsString('.int-card-img.is-bad .int-card-img-bg{border-color:#FF3B3B;}', $css);

        // Red has to outrank the selected ring, and they carry equal
        // specificity — so the only thing deciding it is source order.
        $this->assertGreaterThan(
            strpos($css, '.int-card-img.active .int-card-img-bg'),
            strpos($css, '.int-card-img.is-bad .int-card-img-bg')
        );
    }

    public function test_the_row_leaves_room_for_the_hover_lift(): void
    {
        // The cards rise 2px on hover, into space the scroll container was
        // cutting off.
        $this->assertStringContainsString('.int-scroll{padding-top:4px;', $this->html());
    }

    public function test_next_step_swaps_its_label_for_a_spinner(): void
    {
        $html = $this->html(1);

        $this->assertStringContainsString('<span wire:loading wire:target="nextStep"><i class="fa-solid fa-spinner fa-spin"></i></span>', $html);
        $this->assertStringContainsString('wire:loading.remove wire:target="nextStep"', $html);
        $this->assertStringContainsString('wire:loading.attr="disabled" wire:target="nextStep"', $html);
    }

    public function test_step_four_spins_on_the_advance_and_not_on_every_tick(): void
    {
        $html = $this->html(4);

        // Step 4 posts the selection on every card and sub-interest click, so
        // an untargeted wire:loading would spin and disable the button each
        // time something was ticked.
        $this->assertStringContainsString('wire:loading.attr="disabled" wire:target="nextStep"', $html);
        $this->assertStringNotContainsString('wire:target="syncInterests', $html);
    }

    public function test_the_quick_edit_save_button_waits_past_its_own_call(): void
    {
        $html = Livewire::actingAs(User::factory()->create())
            ->test(ProfileBuilder::class, ['returnTo' => 'profile.edit'])
            ->set('step', 4)->html();

        // wire:loading is not enough on this one: it saves and then navigates
        // away, and the navigation outlasts the commit. See
        // ProfileBuilderSaveSpinnerTest for the rest of that story.
        $this->assertStringContainsString('x-data="{ busy: false }" :disabled="busy"', $html);
        $this->assertStringNotContainsString('wire:target="saveAndReturn"', $html);
    }
}
