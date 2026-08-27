<?php
namespace Tests\Feature\Livewire;

use App\Livewire\Traveler\TripDashboard;
use App\Models\Expense;
use App\Models\Trip;
use App\Models\TripBudget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TripDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function makeTrip(User $user): Trip
    {
        return Trip::factory()->create([
            'user_id'      => $user->id,
            'budget_limit' => 50000,
            'start_date'   => now()->toDateString(),
            'end_date'     => now()->addDays(4)->toDateString(),
        ]);
    }

    public function test_trip_dashboard_page_loads(): void
    {
        $user = User::factory()->create();
        $trip = $this->makeTrip($user);
        $this->actingAs($user)->get("/trips/{$trip->id}/dashboard")->assertStatus(200);
    }

    public function test_dashboard_shows_trip_budget(): void
    {
        $user = User::factory()->create();
        $trip = $this->makeTrip($user);
        Livewire::actingAs($user)
            ->test(TripDashboard::class, ['trip' => $trip])
            ->assertSee('50,000');
    }

    public function test_dashboard_shows_spent_amount(): void
    {
        $user = User::factory()->create();
        $trip = $this->makeTrip($user);
        Expense::create([
            'trip_id'      => $trip->id,
            'user_id'      => $user->id,
            'amount'       => 1500,
            'category'     => 'Food',
            'description'  => 'Lunch',
            'expense_date' => now()->toDateString(),
        ]);
        Livewire::actingAs($user)
            ->test(TripDashboard::class, ['trip' => $trip])
            ->assertSee('1,500');
    }

    public function test_other_user_cannot_view_trip_dashboard(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $trip  = $this->makeTrip($user);
        $this->actingAs($other)->get("/trips/{$trip->id}/dashboard")->assertStatus(403);
    }

    public function test_global_dashboard_loads(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')->assertStatus(200);
    }
}
