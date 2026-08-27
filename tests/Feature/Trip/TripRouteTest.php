<?php
namespace Tests\Feature\Trip;

use App\Models\User;
use App\Models\Trip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_trip_factory_creates_valid_trip(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        $this->assertDatabaseHas('trips', ['id' => $trip->id, 'user_id' => $user->id]);
        $this->assertContains($trip->travel_type, ['Solo', 'Family', 'Couple', 'Friends']);
    }

    public function test_trips_index_requires_auth(): void
    {
        $this->get('/trips')->assertRedirect(route('login'));
    }

    public function test_trips_show_requires_auth(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        $this->get("/trips/{$trip->id}")->assertRedirect(route('login'));
    }
}
