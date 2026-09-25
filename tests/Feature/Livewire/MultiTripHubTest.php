<?php
namespace Tests\Feature\Livewire;

use App\Livewire\Traveler\MultiTripHub;
use App\Models\Expense;
use App\Models\Trip;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MultiTripHubTest extends TestCase
{
    use RefreshDatabase;

    private function userWithProfile(): User
    {
        $user = User::factory()->create();
        UserProfile::create(['user_id' => $user->id]);
        return $user;
    }

    public function test_trips_index_loads(): void
    {
        $user = $this->userWithProfile();
        $this->actingAs($user)->get('/multi-trips')->assertStatus(200)->assertSee('Multi Trip Hub');
    }

    public function test_the_page_ships_every_trip_for_the_browser_to_filter(): void
    {
        // Searching moved into Alpine so it lands on the keystroke instead of
        // 500ms later, which means the server deliberately does NOT filter:
        // both trips are rendered and the browser hides one.
        $user = User::factory()->create();
        Trip::factory()->create(['user_id' => $user->id, 'destination' => 'Boracay, Philippines']);
        Trip::factory()->create(['user_id' => $user->id, 'destination' => 'Bangkok, Thailand']);

        Livewire::actingAs($user)
            ->test(MultiTripHub::class)
            ->assertSee('Boracay')
            ->assertSee('Bangkok');
    }

    public function test_each_trip_carries_what_the_browser_matches_on(): void
    {
        // The haystack is the client-side counterpart of the WHERE clause the
        // component used to run: a renamed trip still findable by where it
        // goes, and a multi-city trip findable by its second leg.
        $user = User::factory()->create();
        Trip::factory()->create([
            'user_id'          => $user->id,
            'destination'      => 'Boracay, Philippines',
            'trip_name'        => 'Barkada Getaway',
            'leg2_destination' => 'Cebu City',
        ]);

        $html = $this->actingAs($user)->get('/multi-trips')->getContent();

        foreach (['boracay', 'barkada getaway', 'cebu city'] as $term) {
            $this->assertStringContainsString($term, $html, "cannot be searched by \"{$term}\"");
        }
    }

    public function test_searching_can_no_longer_strand_a_compared_trip(): void
    {
        // Server-side search shrank the collection on every keystroke, and
        // fetchCompareData() then looked up a selected id that was no longer
        // in it — firstWhere() returned null and the next property read blew
        // up. With the collection stable, picking two trips and typing is
        // just a filter.
        $user  = $this->userWithProfile();
        $trip1 = Trip::factory()->create(['user_id' => $user->id, 'destination' => 'Boracay, Philippines']);
        $trip2 = Trip::factory()->create(['user_id' => $user->id, 'destination' => 'Bangkok, Thailand']);

        Livewire::actingAs($user)
            ->test(MultiTripHub::class)
            ->call('toggleCompare', $trip1->id)
            ->call('toggleCompare', $trip2->id)
            ->call('runComparison')
            ->assertOk();
    }

    public function test_picking_two_trips_and_running_the_comparison_opens_the_modal(): void
    {
        $user  = User::factory()->create();
        $trip1 = Trip::factory()->create(['user_id' => $user->id]);
        $trip2 = Trip::factory()->create(['user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test(MultiTripHub::class)
            ->call('toggleCompare', $trip1->id)
            ->call('toggleCompare', $trip2->id)
            ->call('runComparison')
            ->assertSet('showComparison', true)
            ->assertSet('compareIds', [$trip1->id, $trip2->id]);
    }

    public function test_one_pick_alone_does_not_open_the_comparison(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test(MultiTripHub::class)
            ->call('toggleCompare', $trip->id)
            ->call('runComparison')
            ->assertSet('showComparison', false)
            ->assertSet('compareIds', [$trip->id]);
    }

    public function test_picking_the_same_trip_twice_unpicks_it(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test(MultiTripHub::class)
            ->call('toggleCompare', $trip->id)
            ->call('toggleCompare', $trip->id)
            ->assertSet('compareIds', []);
    }

    public function test_compare_data_breaks_down_spending_by_category_per_trip(): void
    {
        $user  = User::factory()->create();
        $trip1 = Trip::factory()->create(['user_id' => $user->id]);
        $trip2 = Trip::factory()->create(['user_id' => $user->id]);
        Expense::create(['trip_id' => $trip1->id, 'user_id' => $user->id, 'amount' => 1000, 'category' => 'Food', 'expense_date' => '2026-08-01']);
        Expense::create(['trip_id' => $trip2->id, 'user_id' => $user->id, 'amount' => 1500, 'category' => 'Food', 'expense_date' => '2026-08-01']);

        $component = Livewire::actingAs($user)
            ->test(MultiTripHub::class)
            ->call('toggleCompare', $trip1->id)
            ->call('toggleCompare', $trip2->id)
            ->call('runComparison');

        $data = $component->viewData('compareData');
        $this->assertSame(1000.0, $data[0]['categories']['Food']);
        $this->assertSame(1500.0, $data[1]['categories']['Food']);
    }

    public function test_comparison_modal_renders_trip_and_category_details(): void
    {
        $user  = User::factory()->create();
        $trip1 = Trip::factory()->create(['user_id' => $user->id, 'destination' => 'Cebu']);
        $trip2 = Trip::factory()->create(['user_id' => $user->id, 'destination' => 'Davao']);
        Expense::create(['trip_id' => $trip1->id, 'user_id' => $user->id, 'amount' => 1000, 'category' => 'Food', 'expense_date' => '2026-08-01']);

        Livewire::actingAs($user)
            ->test(MultiTripHub::class)
            ->call('toggleCompare', $trip1->id)
            ->call('toggleCompare', $trip2->id)
            ->call('runComparison')
            ->assertSee('Compare Trips')
            ->assertSee('Spending by Category')
            ->assertSee('Cebu')
            ->assertSee('Davao');
    }

    public function test_details_modal_shows_selected_trip(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id, 'trip_name' => 'Palawan Getaway']);

        Livewire::actingAs($user)
            ->test(MultiTripHub::class)
            ->call('showDetail', $trip->id)
            ->assertSee('Palawan Getaway')
            ->assertSee('Budget Usage')
            ->call('closeDetail')
            ->assertDontSee('Budget Usage');
    }

    public function test_empty_state_shown_when_no_trips(): void
    {
        $user = $this->userWithProfile();
        Livewire::actingAs($user)
            ->test(MultiTripHub::class)
            ->assertSee('No trips planned yet');
    }
}
