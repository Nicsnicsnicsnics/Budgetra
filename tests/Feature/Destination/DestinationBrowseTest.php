<?php
namespace Tests\Feature\Destination;

use App\Models\Attraction;
use App\Models\DestinationCost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestinationBrowseTest extends TestCase
{
    use RefreshDatabase;

    public function test_attractions_index_loads_for_auth_user(): void
    {
        $user = User::factory()->create();
        Attraction::create([
            'name'        => 'White Beach',
            'destination' => 'Boracay',
            'rating'      => 4.7,
        ]);
        $this->actingAs($user)->get('/attractions')->assertStatus(200);
    }

    public function test_destination_detail_shows_attractions(): void
    {
        $user = User::factory()->create();
        $attraction = Attraction::create([
            'name'        => 'Underground River',
            'destination' => 'Palawan',
            'rating'      => 4.9,
        ]);

        $this->actingAs($user)
             ->get('/attractions/' . $attraction->id)
             ->assertStatus(200)
             ->assertSee('Underground River');
    }

    public function test_attractions_page_requires_auth(): void
    {
        $this->get('/attractions')->assertRedirect(route('login'));
    }
}
