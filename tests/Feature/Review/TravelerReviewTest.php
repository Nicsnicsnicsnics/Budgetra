<?php
namespace Tests\Feature\Review;

use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TravelerReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_reviews_page_loads(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/reviews')->assertStatus(200);
    }

    public function test_user_can_submit_review(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/reviews', [
            'destination' => 'Boracay',
            'rating'      => 5,
            'body'        => 'Amazing beach! Highly recommend.',
        ]);

        $response->assertRedirect(route('reviews.index'));
        $this->assertDatabaseHas('reviews', [
            'destination' => 'Boracay',
            'user_id'     => $user->id,
            'status'      => 'active',
        ]);
    }

    public function test_reviews_index_shows_active_reviews_only(): void
    {
        $user = User::factory()->create();
        Review::create(['user_id' => $user->id, 'destination' => 'Palawan', 'rating' => 4, 'body' => 'Visible', 'status' => 'active']);
        Review::create(['user_id' => $user->id, 'destination' => 'Palawan', 'rating' => 1, 'body' => 'Hidden',  'status' => 'hidden']);

        $this->actingAs($user)->get('/reviews')->assertSee('Visible')->assertDontSee('Hidden');
    }

    public function test_review_requires_valid_rating(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->post('/reviews', [
            'destination' => 'Bohol', 'rating' => 6, 'body' => 'Test',
        ]);
        $response->assertSessionHasErrors('rating');
    }

    public function test_review_requires_auth(): void
    {
        $this->post('/reviews', [])->assertRedirect(route('login'));
    }
}
