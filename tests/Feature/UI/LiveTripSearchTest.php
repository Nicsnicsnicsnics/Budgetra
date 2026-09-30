<?php
namespace Tests\Feature\UI;

use App\Models\Trip;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Searching Saved Trips and the Multi-Trip Hub filters as you type.
 *
 * Both used wire:model.live.debounce.500ms, so every keystroke was a round
 * trip: half a second of nothing, then the list jumped. Saving Goals already
 * filtered in Alpine over cards that were all on the page anyway, and these
 * two now do the same. Neither screen paginates server-side — both call
 * ->get() — so nothing is lost by filtering in the browser.
 */
class LiveTripSearchTest extends TestCase
{
    use RefreshDatabase;

    private function traveler(): User
    {
        $user = User::factory()->create();
        UserProfile::create(['user_id' => $user->id]);

        return $user;
    }

    private function trip(User $user, array $attrs = []): Trip
    {
        return Trip::factory()->create($attrs + [
            'user_id'    => $user->id,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date'   => now()->addDays(14)->toDateString(),
        ]);
    }

    public static function pages(): array
    {
        return [
            'saved trips' => ['/saved-trips', 'saved-trips.blade.php'],
            'multi trips' => ['/multi-trips', 'multi-trip-hub.blade.php'],
        ];
    }

    private function template(string $file): string
    {
        $dir = resource_path('views/livewire/traveler/');

        return file_get_contents($dir . $file);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function test_the_box_no_longer_waits_on_the_server(string $url, string $file): void
    {
        // The debounce IS the delay the traveler was complaining about.
        $template = $this->template($file);

        $this->assertStringNotContainsString('wire:model.live.debounce.500ms="search"', $template);
        $this->assertStringContainsString('x-model="q"', $template);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function test_the_spinner_is_gone_with_the_round_trip(string $url, string $file): void
    {
        // It existed because the DB round trip ran ~0.5s and the box looked
        // frozen. There is nothing to wait for now, so a spinner would be
        // claiming work that is not happening.
        $this->assertStringNotContainsString(
            'wire:target="search"',
            $this->template($file)
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function test_the_server_stops_filtering_so_the_browser_can(string $url, string $file): void
    {
        // Both trips must reach the page; the browser hides one.
        $user = $this->traveler();
        $this->trip($user, ['destination' => 'Boracay, Philippines']);
        $this->trip($user, ['destination' => 'Bangkok, Thailand']);

        $html = $this->actingAs($user)->get($url)->getContent();

        $this->assertStringContainsString('Boracay', $html);
        $this->assertStringContainsString('Bangkok', $html);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function test_a_renamed_trip_is_still_findable_by_where_it_goes(string $url, string $file): void
    {
        // The old WHERE clause matched destination, trip_name and
        // leg2_destination. The haystack has to carry all three or the
        // client-side search is quietly narrower than what it replaced.
        $user = $this->traveler();
        $this->trip($user, [
            'destination'      => 'Boracay, Philippines',
            'trip_name'        => 'Barkada Getaway',
            'leg2_destination' => 'Cebu City',
        ]);

        $html = $this->actingAs($user)->get($url)->getContent();

        foreach (['boracay', 'barkada getaway', 'cebu city'] as $term) {
            $this->assertStringContainsString($term, $html, "not searchable by \"{$term}\"");
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function test_the_tab_counts_follow_the_search(string $url, string $file): void
    {
        // A count that stays at 3 while one card is showing is worse than no
        // count. Saving Goals already recomputes these in the browser.
        $this->assertStringContainsString('countFor(', $this->template($file));
        $this->assertStringContainsString('x-text="countFor(', $this->template($file));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function test_a_search_matching_nothing_says_so(string $url, string $file): void
    {
        // Distinct from an empty tab: the trips exist, just not under this
        // term, so the copy must not offer "plan a trip".
        $template = $this->template($file);

        $this->assertStringContainsString('No trips found', $template);
        $this->assertStringContainsString('Try searching another trip.', $template);
        $this->assertMatchesRegularExpression('/x-show="q\.trim\(\) !== \'\'/', $template);
    }

    public function test_saved_trips_pages_the_filtered_set_not_the_whole_one(): void
    {
        // The page each card sat on was baked in from the unfiltered loop
        // index. Left alone, searching would have shown page 1 holding
        // whichever 1 of its 3 cards matched, with the rest stranded on
        // pages the traveler had no reason to visit.
        $template = $this->template('saved-trips.blade.php');

        $this->assertStringContainsString('x-show="pageOf({{ $loop->index }}) === page"', $template);
        $this->assertDoesNotMatchRegularExpression(
            '/x-show="page === \{\{ \(int\) floor\(\$loop->index/',
            $template
        );
        // Rank among MATCHING cards is the whole mechanism.
        $this->assertStringContainsString('if (!this.matches(this.hays[i])) return -1;', $template);
    }

    public function test_the_pager_shrinks_with_the_results(): void
    {
        $template = $this->template('saved-trips.blade.php');

        // Page count and the run of numbers are both derived in the browser.
        $this->assertStringContainsString('get totalPages()', $template);
        $this->assertStringContainsString('get pageNums()', $template);
        $this->assertStringContainsString('x-show="totalPages > 1"', $template);
        // Nothing server-rendered left to go stale.
        $this->assertStringNotContainsString('$stTotalPages', $template);
        $this->assertStringNotContainsString('$stPageNums', $template);
    }

    public function test_typing_returns_to_the_first_page(): void
    {
        // Filter down to two results while sitting on page 3 and, without
        // this, the traveler stares at an empty panel.
        $this->assertStringContainsString(
            "x-init=\"\$watch('q', () => page = 1)\"",
            $this->template('saved-trips.blade.php')
        );
    }

    public function test_cards_are_hidden_rather_than_unmounted(): void
    {
        // Dropping the node would discard state that lives on the card — an
        // open kebab menu, or the Hub's compare selection.
        //
        // The two express it differently: the Hub tests the haystack straight,
        // while Saved Trips folds the same test into pageOf() -> rank(), which
        // returns -1 for a card that does not match.
        $this->assertStringContainsString(
            'x-show="matches(',
            $this->template('multi-trip-hub.blade.php')
        );
        $this->assertStringContainsString(
            'x-show="pageOf({{ $loop->index }}) === page"',
            $this->template('saved-trips.blade.php')
        );
    }

    public function test_the_page_level_empty_state_no_longer_guards_on_search(): void
    {
        // The guard existed because a search that matched nothing emptied the
        // collection and would have offered "Plan Your First Trip" to someone
        // who already had trips. The collection no longer shrinks.
        foreach (['saved-trips.blade.php', 'multi-trip-hub.blade.php'] as $file) {
            $this->assertStringNotContainsString('&& !$search', $this->template($file), $file);
        }
    }
}
