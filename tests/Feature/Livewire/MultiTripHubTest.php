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

    public function test_search_filters_trips(): void
    {
        $user = User::factory()->create();
        Trip::factory()->create(['user_id' => $user->id, 'destination' => 'Boracay, Philippines']);
        Trip::factory()->create(['user_id' => $user->id, 'destination' => 'Bangkok, Thailand']);

        Livewire::actingAs($user)
            ->test(MultiTripHub::class)
            ->set('search', 'Boracay')
            ->assertSee('Boracay')
            ->assertDontSee('Bangkok');
    }

    public function test_compare_with_auto_pairs_with_another_trip_and_opens_modal(): void
    {
        $user  = User::factory()->create();
        $trip1 = Trip::factory()->create(['user_id' => $user->id]);
        $trip2 = Trip::factory()->create(['user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test(MultiTripHub::class)
            ->call('compareWith', $trip1->id)
            ->assertSet('showComparison', true)
            ->assertSet('compareIds', [$trip1->id, $trip2->id]);
    }

    public function test_compare_with_does_nothing_when_no_other_trip_exists(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test(MultiTripHub::class)
            ->call('compareWith', $trip->id)
            ->assertSet('showComparison', false)
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
            ->call('compareWith', $trip1->id);

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
            ->call('compareWith', $trip1->id)
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
