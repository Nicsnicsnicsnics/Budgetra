<?php
namespace Tests\Feature\Destination;

use App\Models\DestinationCost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComparisonTest extends TestCase
{
    use RefreshDatabase;

    public function test_comparison_page_loads(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/compare')->assertStatus(200);
    }

    public function test_comparison_shows_selected_destinations(): void
    {
        $user = User::factory()->create();
        DestinationCost::create(['destination' => 'Bohol',   'cost_level' => 'Budget-friendly', 'multiplier' => 0.900]);
        DestinationCost::create(['destination' => 'Siargao', 'cost_level' => 'Moderate',        'multiplier' => 1.100]);

        $response = $this->actingAs($user)
            ->get('/compare?destinations[]=Bohol&destinations[]=Siargao&days=5&travelers=2');

        $response->assertStatus(200);
        $response->assertSee('Bohol');
        $response->assertSee('Siargao');
    }

    public function test_comparison_requires_auth(): void
    {
        $this->get('/compare')->assertRedirect(route('login'));
    }
}
