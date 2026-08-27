<?php
namespace Tests\Feature\Destination;

use App\Models\DestinationCost;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstimatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_estimate_page_loads_for_trip_owner(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id, 'destination' => 'Cebu']);
        DestinationCost::create(['destination' => 'Cebu', 'cost_level' => 'Moderate', 'multiplier' => 1.100]);

        $this->actingAs($user)->get("/trips/{$trip->id}/estimate")->assertStatus(200)->assertSee('Cebu');
    }

    public function test_estimate_page_blocked_for_non_owner(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $trip  = Trip::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user)->get("/trips/{$trip->id}/estimate")->assertStatus(403);
    }
}
