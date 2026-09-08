<?php
namespace Tests\Feature\Livewire;

use App\Livewire\Traveler\ProfileBuilder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Step 4 lets you search all 72 sub-interests instead of opening panels.
 *
 * The nine interest cards scroll sideways and each keeps its eight
 * sub-interests behind a click, so finding one specific thing meant hunting.
 * Picking a result has to tick the interest that owns it — and two names
 * (Diving, Night Markets) are owned by two interests each, which the flat
 * sub_interests column cannot distinguish.
 */
class ProfileBuilderInterestSearchTest extends TestCase
{
    use RefreshDatabase;

    private function builder()
    {
        return Livewire::actingAs(User::factory()->create())->test(ProfileBuilder::class);
    }

    public function test_the_search_box_and_pill_list_are_rendered(): void
    {
        $html = $this->builder()->html();

        $this->assertStringContainsString('int-search-wrap', $html);
        $this->assertStringContainsString('x-for="r in results"', $html);
        $this->assertStringContainsString('int-pill', $html);
        $this->assertStringContainsString('removeSub(sub)', $html);
    }

    public function test_every_sub_interest_is_searchable(): void
    {
        $html = $this->builder()->html();

        // The filter runs over interestSubs, so the whole map has to reach the
        // browser — all nine interests and their eight sub-interests each.
        $all = collect(ProfileBuilder::INTERESTS)->flatten();
        $this->assertSame(72, $all->count());

        foreach (['Swimming', 'Ziplining', 'Karaoke', 'Archaeological Sites'] as $sub) {
            $this->assertStringContainsString($sub, $html);
        }
    }

    public function test_selecting_a_sub_interest_stores_it(): void
    {
        $this->builder()
            ->call('toggleSubInterest', 'Swimming')
            ->assertSet('selectedSubInterests', ['Swimming']);
    }

    public function test_removing_a_sub_interest_via_its_pill_clears_it(): void
    {
        // The pill's x calls the same server action the panel does.
        $this->builder()
            ->call('toggleSubInterest', 'Swimming')
            ->call('toggleSubInterest', 'Swimming')
            ->assertSet('selectedSubInterests', []);
    }

    public function test_the_parent_interest_is_ticked_with_its_sub(): void
    {
        $this->builder()
            ->call('toggleSubInterest', 'Swimming')
            ->call('toggleInterest', 'Beach')
            ->assertSet('selectedInterests', ['Beach'])
            ->assertSet('selectedSubInterests', ['Swimming']);
    }

    public function test_two_sub_interests_are_owned_by_two_interests_each(): void
    {
        $owners = [];
        foreach (ProfileBuilder::INTERESTS as $interest => $subs) {
            foreach ($subs as $sub) {
                $owners[$sub][] = $interest;
            }
        }
        $shared = array_keys(array_filter($owners, fn ($o) => count($o) > 1));
        sort($shared);

        // Pinned because the search shows one row per name with its parents
        // listed, and ticks every one of them. If a third shared name appears,
        // that behaviour should be revisited deliberately.
        $this->assertSame(['Diving', 'Night Markets'], $shared);
        $this->assertSame(['Beach', 'Adventure'], $owners['Diving']);
    }
}
