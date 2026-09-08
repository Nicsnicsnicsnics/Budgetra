<?php
namespace Tests\Feature\Livewire;

use App\Livewire\Traveler\ProfileBuilder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Step 4: what a card click does, and how the selection reaches the server.
 *
 * The card used to expand its sub-interest list AND toggle the interest on
 * the same click, so re-opening a list you had already picked from turned that
 * interest back off. Browsing the nine cards could end with nothing selected
 * while a card still looked ticked — and Next Step, which refuses an empty
 * step 4, then did nothing at all.
 *
 * The rule now: a card click only opens its list; an interest is ticked
 * because one of its sub-interests is.
 */
class ProfileBuilderInterestSelectionTest extends TestCase
{
    use RefreshDatabase;

    private function builder()
    {
        return Livewire::actingAs(User::factory()->create())->test(ProfileBuilder::class);
    }

    public function test_the_card_click_only_opens_the_list(): void
    {
        $html = $this->builder()->html();

        // The card's own handler must not touch the selection any more.
        $this->assertStringContainsString('x-on:click="expand(', $html);
        $this->assertStringNotContainsString("toggleInterest('", $html);
    }

    public function test_the_tick_badge_is_the_way_to_clear_an_interest(): void
    {
        // Without it an interest saved before this rule — ticked with no
        // sub-interests under it — could never be removed.
        $this->assertStringContainsString(
            'x-on:click.stop="clearInterest(',
            $this->builder()->html()
        );
    }

    public function test_sync_replaces_the_whole_selection_rather_than_toggling(): void
    {
        $this->builder()
            ->call('syncInterests', ['Beach'], ['Swimming'])
            ->assertSet('selectedInterests', ['Beach'])
            ->assertSet('selectedSubInterests', ['Swimming'])
            // Sending it again must not undo it: the point of an absolute
            // sync is that a repeat is harmless and a lost one self-repairs.
            ->call('syncInterests', ['Beach'], ['Swimming'])
            ->assertSet('selectedInterests', ['Beach'])
            ->call('syncInterests', ['Nature'], ['Waterfalls'])
            ->assertSet('selectedInterests', ['Nature'])
            ->assertSet('selectedSubInterests', ['Waterfalls']);
    }

    public function test_sync_drops_anything_that_is_not_a_real_interest(): void
    {
        $this->builder()
            ->call('syncInterests', ['Beach', 'Hacking'], ['Swimming', 'Arson'])
            ->assertSet('selectedInterests', ['Beach'])
            ->assertSet('selectedSubInterests', ['Swimming']);
    }

    public function test_a_sub_owned_by_two_interests_is_stored_once(): void
    {
        // Diving sits under both Beach and Adventure, so the flat list of
        // every sub-interest holds it twice and must be de-duplicated before
        // it is used to filter what the browser sent.
        $this->builder()
            ->call('syncInterests', ['Beach', 'Adventure'], ['Diving'])
            ->assertSet('selectedSubInterests', ['Diving']);
    }

    public function test_sync_imposes_a_stable_order(): void
    {
        // Two travelers who picked the same things store the same rows,
        // whatever order they clicked in.
        $a = $this->builder()->call('syncInterests', ['Nature', 'Beach'], [])->get('selectedInterests');
        $b = $this->builder()->call('syncInterests', ['Beach', 'Nature'], [])->get('selectedInterests');

        $this->assertSame($a, $b);
        $this->assertSame(['Beach', 'Nature'], $a);
    }

    public function test_next_step_advances_on_what_the_button_sends(): void
    {
        // The button posts the selection and asks to advance in one request,
        // in that order — so step 4 is judged on what is on screen rather
        // than on whatever survived the earlier round trips.
        $this->builder()
            ->set('step', 4)
            ->call('syncInterests', ['Beach'], ['Swimming'])
            ->call('nextStep')
            ->assertSet('step', 5);
    }

    public function test_step_four_still_refuses_an_empty_selection(): void
    {
        $this->builder()
            ->set('step', 4)
            ->call('syncInterests', [], [])
            ->call('nextStep')
            ->assertSet('step', 4)
            ->assertDispatched('profile-missing');
    }

    public function test_the_step_four_buttons_send_the_selection_before_moving_on(): void
    {
        $html = $this->builder()->set('step', 4)->html();

        $this->assertStringContainsString('$wire.syncInterests(', $html);
        $this->assertStringContainsString('$wire.nextStep()', $html);
    }
}
