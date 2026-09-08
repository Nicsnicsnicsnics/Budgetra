<?php
namespace Tests\Feature\UI;

use App\Models\Attraction;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Submit Review is a plain form POST, so the wait is a full page navigation.
 *
 * Nothing marked that: the button stayed lit and clickable for the whole
 * round trip. It latches on submit instead — which the browser only fires
 * once the required fields have passed, so an empty form still gets its
 * native prompt rather than a spinner that goes nowhere.
 */
class AttractionReviewSubmitStateTest extends TestCase
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

    private function page(): \Illuminate\Testing\TestResponse
    {
        $attraction = $this->attraction();
        return $this->actingAs(User::factory()->create())->get("/attractions/{$attraction->id}");
    }

    public function test_the_form_latches_the_button_on_submit(): void
    {
        $res = $this->page();

        $res->assertStatus(200);
        $res->assertSee('x-on:submit="busy = true"', false);
        $res->assertSee('<button type="submit" class="atd-submit-btn" :disabled="busy">', false);
        $res->assertSee('<span x-show="busy" x-cloak><i class="fa-solid fa-spinner fa-spin"></i></span>', false);
    }

    public function test_a_submit_in_flight_cannot_be_dismissed(): void
    {
        // Closing the modal would not stop the POST, so the traveler would be
        // left with no idea whether the review went through.
        $res = $this->page();

        $res->assertSee('if (event.target === $el && !busy) showReviewForm = false', false);
        $res->assertSee('if (!busy) showReviewForm = false', false);
        $res->assertSee('class="atd-modal-cancel-btn" :disabled="busy"', false);
    }

    public function test_the_label_keeps_its_icon_spacing(): void
    {
        // The button's own flex gap now falls between the two spans, so the
        // label span has to carry the icon-to-text gap itself.
        $this->page()->assertSee('.atd-submit-btn > span { display: inline-flex; align-items: center; gap: 8px; }', false);
    }

    public function test_the_edit_form_gets_the_same_treatment(): void
    {
        // Same modal, relabelled — a traveler who already reviewed this place
        // sees "Save Changes" and posts to reviews.update.
        $attraction = $this->attraction();
        $user       = User::factory()->create();

        Review::create([
            'user_id'       => $user->id,
            'attraction_id' => $attraction->id,
            'destination'   => $attraction->destination,
            'rating'        => 4,
            'body'          => 'Slippery but worth it.',
        ]);

        $res = $this->actingAs($user)->get("/attractions/{$attraction->id}");

        $res->assertSee('Save Changes');
        $res->assertSee('x-on:submit="busy = true"', false);
    }
}
