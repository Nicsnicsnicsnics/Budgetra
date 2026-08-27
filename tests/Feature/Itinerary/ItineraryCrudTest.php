<?php
namespace Tests\Feature\Itinerary;

use App\Models\Itinerary;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItineraryCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_itinerary_index_loads(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('itinerary.index'))->assertStatus(200);
    }

    public function test_user_can_add_itinerary_item(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('itinerary.store'), [
            'trip_id'        => $trip->id,
            'title'          => 'Fly to Cebu',
            'type'           => 'Flight',
            'start_datetime' => '2026-08-01 08:00:00',
            'end_datetime'   => '2026-08-01 10:00:00',
            'location'       => 'NAIA Terminal 3',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('itinerary', ['title' => 'Fly to Cebu', 'trip_id' => $trip->id]);
    }

    public function test_store_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('itinerary.store'), []);

        $response->assertSessionHasErrors(['trip_id', 'title', 'type', 'start_datetime']);
    }

    public function test_user_can_delete_their_itinerary_item(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        $item = Itinerary::create([
            'trip_id'        => $trip->id,
            'title'          => 'Hotel Check-in',
            'type'           => 'Hotel',
            'start_datetime' => '2026-08-01 14:00:00',
        ]);

        $response = $this->actingAs($user)->delete(route('itinerary.destroy', $item));

        $response->assertRedirect();
        $this->assertDatabaseMissing('itinerary', ['id' => $item->id]);
    }

    public function test_user_cannot_delete_another_users_itinerary_item(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $trip  = Trip::factory()->create(['user_id' => $other->id]);
        $item  = Itinerary::create([
            'trip_id'        => $trip->id,
            'title'          => 'Snorkeling',
            'type'           => 'Activity',
            'start_datetime' => '2026-08-02 09:00:00',
        ]);

        $this->actingAs($user)->delete(route('itinerary.destroy', $item))->assertStatus(403);
        $this->assertDatabaseHas('itinerary', ['id' => $item->id]);
    }
}
