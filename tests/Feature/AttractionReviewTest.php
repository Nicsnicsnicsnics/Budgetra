<?php
namespace Tests\Feature;

use App\Models\Attraction;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttractionReviewTest extends TestCase
{
    use RefreshDatabase;

    private function makeAttraction(): Attraction
    {
        return Attraction::create([
            'name'        => 'Chocolate Hills',
            'destination' => 'Bohol',
            'category'    => 'Nature',
            'description' => 'Famous cone-shaped hills.',
            'rating'      => 4.8,
        ]);
    }

    public function test_attractions_index_loads(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/attractions')->assertStatus(200);
    }

    public function test_attraction_detail_loads(): void
    {
        $user       = User::factory()->create();
        $attraction = $this->makeAttraction();
        $this->actingAs($user)
            ->get("/attractions/{$attraction->id}")
            ->assertStatus(200)
            ->assertSee('Chocolate Hills');
    }

    public function test_user_can_submit_attraction_review(): void
    {
        $user       = User::factory()->create();
        $attraction = $this->makeAttraction();
        $this->actingAs($user)->post('/reviews', [
            'destination'   => $attraction->destination,
            'rating'        => 5,
            'body'          => 'Amazing place to visit!',
            'attraction_id' => $attraction->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('reviews', [
            'user_id'       => $user->id,
            'attraction_id' => $attraction->id,
            'rating'        => 5,
        ]);
    }

    public function test_attraction_id_column_exists(): void
    {
        $review = Review::create([
            'user_id'       => User::factory()->create()->id,
            'destination'   => 'Bohol',
            'rating'        => 4,
            'body'          => 'Great place!',
            'status'        => 'active',
            'attraction_id' => null,
        ]);
        $this->assertNull($review->fresh()->attraction_id);
    }
}
