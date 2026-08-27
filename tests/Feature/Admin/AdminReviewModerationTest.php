<?php
namespace Tests\Feature\Admin;

use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReviewModerationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User { return User::factory()->admin()->create(); }

    public function test_admin_can_view_all_reviews(): void
    {
        $admin = $this->admin();
        $user  = User::factory()->create();
        Review::create(['user_id' => $user->id, 'destination' => 'Bohol', 'rating' => 5, 'body' => 'Great!', 'status' => 'active']);
        $this->actingAs($admin)->get('/admin/reviews')->assertStatus(200)->assertSee('Bohol');
    }

    public function test_admin_can_hide_review(): void
    {
        $admin  = $this->admin();
        $user   = User::factory()->create();
        $review = Review::create(['user_id' => $user->id, 'destination' => 'Bohol', 'rating' => 5, 'body' => 'Nice', 'status' => 'active']);

        $this->actingAs($admin)->patch("/admin/reviews/{$review->id}/hide")->assertRedirect();
        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'status' => 'hidden']);
    }

    public function test_admin_can_show_review(): void
    {
        $admin  = $this->admin();
        $user   = User::factory()->create();
        $review = Review::create(['user_id' => $user->id, 'destination' => 'Bohol', 'rating' => 5, 'body' => 'Bad', 'status' => 'hidden']);

        $this->actingAs($admin)->patch("/admin/reviews/{$review->id}/show")->assertRedirect();
        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'status' => 'active']);
    }
}
