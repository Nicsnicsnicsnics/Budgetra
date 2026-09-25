<?php
namespace Tests\Feature\UI;

use App\Models\Attraction;
use App\Models\Review;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Saving a new profile photo repaints it everywhere, with no page load.
 *
 * Three places show it: the card on /profile, the sidebar, and your own rows
 * in an attraction's reviews. A plain form post used to refresh all three only
 * because it reloaded the page — and the sidebar would not even have managed
 * that reliably, since it sits inside @persist and wire:navigate never
 * re-renders it.
 *
 * So the form posts with fetch() and the server answers with the new URL,
 * which avatar-sync.js applies to every element tagged data-user-avatar.
 */
class ProfileAvatarLiveUpdateTest extends TestCase
{
    use RefreshDatabase;

    /** A traveler with a profile — without one, /profile sends them to the builder. */
    private function traveler(): User
    {
        $user = User::factory()->create();
        UserProfile::create([
            'user_id'      => $user->id,
            'home_city'    => 'Cebu City',
            'daily_budget' => 2000,
            'travel_style' => 'Solo',
        ]);

        return $user;
    }

    public function test_an_ajax_save_answers_with_the_new_photo_url(): void
    {
        Storage::fake('public');
        $user = $this->traveler();

        $response = $this->actingAs($user)
            ->putJson('/profile', [
                'first_name'    => 'Nico',
                'last_name'     => 'Baxal',
                'profile_photo' => UploadedFile::fake()->image('new.jpg'),
            ]);

        $response->assertStatus(200);

        $user->refresh();
        $response->assertJson([
            'photo_url' => Storage::url($user->profile_photo),
            'full_name' => 'Nico Baxal',
            'initials'  => 'NB',
        ]);
    }

    public function test_a_refused_photo_comes_back_as_422_json_not_a_redirect(): void
    {
        Storage::fake('public');

        // The browser blocks anything over 5 MB before uploading, but a
        // refusal that gets past it has to reach the error dialog rather than
        // bouncing the page.
        $response = $this->actingAs($this->traveler())
            ->putJson('/profile', [
                'first_name'    => 'Nico',
                'last_name'     => 'Baxal',
                'profile_photo' => UploadedFile::fake()->create('huge.jpg', 6000, 'image/jpeg'),
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors('profile_photo');
    }

    public function test_a_plain_form_post_still_redirects(): void
    {
        Storage::fake('public');

        $this->actingAs($this->traveler())
            ->from('/profile')
            ->put('/profile', ['first_name' => 'Nico', 'last_name' => 'Baxal'])
            ->assertRedirect('/profile');
    }

    public function test_the_sidebar_avatar_carries_the_repaint_hook(): void
    {
        $html = $this->actingAs($this->traveler())->get('/profile')->getContent();

        // The initials placeholder names the classes its <img> replacement
        // needs, because avatar-sync.js has to swap the element, not its src.
        $this->assertStringContainsString('data-user-avatar', $html);
        $this->assertStringContainsString('data-avatar-img-class="sidebar-profile-avatar"', $html);
    }

    public function test_the_sync_script_is_loaded_app_wide(): void
    {
        // Not only on /profile: the sidebar is persisted and your review rows
        // live on attraction pages.
        $html = $this->actingAs($this->traveler())->get('/dashboard')->getContent();

        $this->assertStringContainsString('js/avatar-sync.js', $html);
    }

    public function test_only_your_own_review_avatar_is_hooked(): void
    {
        $me    = $this->traveler();
        $other = User::factory()->create(['full_name' => 'Someone Else']);

        $spot = Attraction::create([
            'name'        => 'Kawasan Falls',
            'destination' => 'Cebu',
            'category'    => 'Nature',
            'description' => 'A waterfall.',
            'rating'      => 4.5,
        ]);

        foreach ([$me, $other] as $author) {
            Review::create([
                'user_id'       => $author->id,
                'attraction_id' => $spot->id,
                'destination'   => $spot->destination,
                'rating'        => 5,
                'body'          => 'Worth the hike.',
            ]);
        }

        $html = $this->actingAs($me)->get('/attractions/' . $spot->id)->getContent();

        // One review row is mine; the other reviewer's picture is none of the
        // sync script's business.
        $rows = substr_count($html, 'atd-review-avatar');
        $this->assertGreaterThanOrEqual(2, $rows, 'both reviews should render');

        // Counted by the review placeholder's own marker rather than by every
        // data-user-avatar on the page: the sidebar contributes a varying
        // number of those (it renders the Profile link once per destination,
        // so CSS can pick one), and that is not what this test is about.
        $this->assertSame(
            1,
            substr_count($html, 'data-avatar-img-class="atd-review-avatar atd-review-avatar-img"'),
            'exactly one review row — mine — should carry the hook'
        );
    }

    public function test_the_profile_form_posts_without_reloading(): void
    {
        $html = $this->actingAs($this->traveler())->get('/profile')->getContent();

        $this->assertStringContainsString('id="profileForm"', $html);
        $this->assertStringContainsString('budgetraSyncAvatar', $html);

        // Saving confirms itself by the spinner giving way and the avatar and
        // name repainting — there is deliberately no success toast.
        $this->assertStringNotContainsString('profileSavedToast', $html);
        $this->assertStringNotContainsString('Profile updated', $html);
    }
}
