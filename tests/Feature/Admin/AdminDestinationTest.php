<?php
namespace Tests\Feature\Admin;

use App\Models\Attraction;
use App\Models\Destination;
use App\Models\DestinationCost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * /admin/destinations manages the Destination catalogue (the places, and the
 * attractions hanging off them). The per-destination cost multipliers live in
 * their own section at /admin/travel-costs, which is where DestinationCost
 * rows are created and removed.
 */
class AdminDestinationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User { return User::factory()->admin()->create(); }

    public function test_admin_can_view_destinations(): void
    {
        $admin = $this->admin();
        Destination::create(['name' => 'Palawan', 'country' => 'Philippines', 'description' => 'Island province.']);

        $this->actingAs($admin)->get('/admin/destinations')->assertStatus(200)->assertSee('Palawan');
    }

    public function test_admin_can_view_travel_costs(): void
    {
        $admin = $this->admin();
        DestinationCost::create(['destination' => 'Palawan', 'cost_level' => 'Moderate', 'multiplier' => 1.0]);

        $this->actingAs($admin)->get('/admin/travel-costs')->assertStatus(200)->assertSee('Palawan');
    }

    public function test_admin_can_create_travel_cost(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/travel-costs', [
            'destination' => 'Siargao',
            'cost_level'  => 'Moderate',
            'multiplier'  => 1.1,
        ])->assertRedirect(route('admin.travel-costs.index'));

        $this->assertDatabaseHas('destination_costs', ['destination' => 'Siargao']);
    }

    public function test_admin_can_delete_travel_cost(): void
    {
        $admin = $this->admin();
        $cost  = DestinationCost::create(['destination' => 'Test', 'cost_level' => 'Budget-friendly', 'multiplier' => 1.0]);

        $this->actingAs($admin)->delete("/admin/travel-costs/{$cost->id}")->assertRedirect();
        $this->assertDatabaseMissing('destination_costs', ['id' => $cost->id]);
    }

    public function test_admin_can_create_attraction_with_image(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/attractions', [
            'destination' => 'Bohol',
            'name'        => 'Chocolate Hills',
            'description' => 'Iconic hills.',
            'category'    => 'Nature',
            'region'      => 'local',
            'rating'      => 4.8,
            'image'       => UploadedFile::fake()->image('hills.jpg'),
        ])->assertRedirect(route('admin.attractions.index'));

        $this->assertDatabaseHas('attractions', ['name' => 'Chocolate Hills', 'region' => 'local']);
        Storage::disk('public')->assertExists('attraction-images/Chocolate_Hills.jpg');
    }

    public function test_creating_an_attraction_requires_a_region(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/attractions', [
            'destination' => 'Bohol',
            'name'        => 'Chocolate Hills',
        ])->assertSessionHasErrors('region');

        $this->assertDatabaseMissing('attractions', ['name' => 'Chocolate Hills']);
    }
}
