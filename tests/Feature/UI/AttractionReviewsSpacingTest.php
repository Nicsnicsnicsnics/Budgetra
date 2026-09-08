<?php
namespace Tests\Feature\UI;

use App\Models\Attraction;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The gap between the hero photo and the Reviews row.
 *
 * The hero carries no bottom margin: every bit of spacing under it comes from
 * the rating-summary card, which floats up over the photo (margin-top: -70px)
 * and supplies 28px below itself. That card is only rendered once an
 * attraction has a review — so on one with none, nothing supplied any spacing
 * at all and the "Write a review" button sat flush against the photo. Which is
 * the exact page where that button matters most.
 */
class AttractionReviewsSpacingTest extends TestCase
{
    use RefreshDatabase;

    private function attraction(): Attraction
    {
        return Attraction::create([
            'name'        => 'Kawasan Falls',
            'destination' => 'Cebu',
            'category'    => 'Nature',
            'description' => 'A waterfall.',
            'rating'      => 4.5,
        ]);
    }

    public function test_an_attraction_with_no_reviews_carries_its_own_gap(): void
    {
        $attraction = $this->attraction();

        $res = $this->actingAs(User::factory()->create())->get("/attractions/{$attraction->id}");

        $res->assertStatus(200);
        $res->assertSee('atd-reviews-bare', false);
        $res->assertSee('.atd-reviews-bare { margin-top: 28px; }', false);
        // Nothing else is supplying the gap on this page.
        $res->assertDontSee('atd-rev-summary"', false);
    }

    public function test_the_summary_card_keeps_supplying_it_when_there_are_reviews(): void
    {
        $attraction = $this->attraction();
        $user       = User::factory()->create();

        Review::create([
            'user_id'       => $user->id,
            'attraction_id' => $attraction->id,
            'destination'   => $attraction->destination,
            'rating'        => 5,
            'body'          => 'Worth the hike.',
        ]);

        $res = $this->actingAs($user)->get("/attractions/{$attraction->id}");

        // The card is back, so the extra margin must not stack on top of it.
        $res->assertSee('atd-rev-summary', false);
        $res->assertDontSee('atd-reviews-bare"', false);
    }
}
