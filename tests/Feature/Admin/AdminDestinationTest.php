<?php
namespace Tests\Feature\Admin;

use App\Models\Attraction;
use App\Models\DestinationCost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminDestinationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User { return User::factory()->admin()->create(); }

    public function test_admin_can_view_destinations(): void
    {
        $admin = $this->admin();
        DestinationCost::create(['destination' => 'Palawan', 'cost_level' => 'Moderate', 'multiplier' => 1.0]);
        $this->actingAs($admin)->get('/admin/destinations')->assertStatus(200)->assertSee('Palawan');
    }

    public function test_admin_can_create_destination(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/destinations', [
            'destination' => 'Siargao',
            'cost_level'  => 'Moderate',
            'multiplier'  => 1.1,
        ])->assertRedirect(route('admin.destinations.index'));
        $this->assertDatabaseHas('destination_costs', ['destination' => 'Siargao']);
    }

    public function test_admin_can_delete_destination(): void
    {
        $admin = $this->admin();
        $dest  = DestinationCost::create(['destination' => 'Test', 'cost_level' => 'Budget-friendly', 'multiplier' => 1.0]);
        $this->actingAs($admin)->delete("/admin/destinations/{$dest->id}")->assertRedirect();
        $this->assertDatabaseMissing('destination_costs', ['id' => $dest->id]);
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
            'rating'      => 4.8,
            'image'       => UploadedFile::fake()->image('hills.jpg'),
        ])->assertRedirect(route('admin.attractions.index'));

        $this->assertDatabaseHas('attractions', ['name' => 'Chocolate Hills']);
        Storage::disk('public')->assertExists('attraction-images/Chocolate_Hills.jpg');
    }
}
