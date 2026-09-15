<?php
namespace Tests\Feature\UI;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Changing the profile photo from the avatar itself.
 *
 * The dropzone under the form is gone; the + on the avatar opens the picker.
 * It also settles a three-way disagreement about the size limit: the dropzone
 * said 5 MB, the controller enforced 2 MB, so a 3 MB photo uploaded in full
 * and was then thrown away with a validation error.
 */
class ProfilePhotoUploadTest extends TestCase
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

    private function page(): string
    {
        return $this->actingAs($this->traveler())
            ->get('/profile')->assertStatus(200)->getContent();
    }

    public function test_the_dropzone_is_gone(): void
    {
        $html = $this->page();

        $this->assertStringNotContainsString('profile-photo-dropzone', $html);
        $this->assertStringNotContainsString('Click to upload', $html);
        $this->assertStringNotContainsString('fileNameLabel', $html);
    }

    public function test_the_avatar_carries_the_picker(): void
    {
        $html = $this->page();

        // A <label for> rather than a button, so it opens the picker with no
        // JS and still reaches the keyboard.
        $this->assertStringContainsString('<label for="profile_photo" class="avatar-add-btn"', $html);
        $this->assertStringContainsString('fa-solid fa-plus', $html);
        $this->assertStringContainsString('id="profile_photo"', $html);
        $this->assertStringContainsString('onchange="budgetraPreviewAvatar(this)"', $html);
    }

    public function test_the_browser_refuses_anything_over_five_megabytes(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('var BUDGETRA_MAX_PHOTO_BYTES = 5 * 1024 * 1024;', $html);
        // Cleared, or choosing the same file again would not fire change.
        $this->assertStringContainsString("input.value = '';", $html);
        $this->assertStringContainsString('budgetraShowPhotoError(', $html);
    }

    public function test_the_refusal_is_a_modal(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('id="photoErrorModal"', $html);
        $this->assertStringContainsString('class="pf-modal-backdrop"', $html);
        $this->assertStringContainsString('role="alertdialog"', $html);
        // Closed on a clean load.
        $this->assertStringContainsString('style="display:none;"', $html);
    }

    public function test_a_photo_under_the_limit_is_accepted(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->put('/profile', [
            'first_name'    => 'Nico',
            'last_name'     => 'Baxal',
            'profile_photo' => UploadedFile::fake()->image('artwork.jpg')->size(4096), // 4 MB
        ])->assertSessionHasNoErrors();

        $this->assertNotNull($user->fresh()->profile_photo);
    }

    public function test_a_photo_over_the_limit_is_refused(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->put('/profile', [
            'first_name'    => 'Nico',
            'last_name'     => 'Baxal',
            'profile_photo' => UploadedFile::fake()->image('huge.jpg')->size(6144), // 6 MB
        ])->assertSessionHasErrors('profile_photo');

        $this->assertNull($user->fresh()->profile_photo);
    }

    public function test_the_three_megabyte_photo_the_old_limit_threw_away(): void
    {
        // The page advertised 5 MB while the controller enforced 2 MB, so this
        // one uploaded in full and was then rejected.
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->put('/profile', [
            'first_name'    => 'Nico',
            'last_name'     => 'Baxal',
            'profile_photo' => UploadedFile::fake()->image('phone-photo.jpg')->size(3072),
        ])->assertSessionHasNoErrors();

        $this->assertNotNull($user->fresh()->profile_photo);
    }

    public function test_a_server_refusal_opens_the_same_modal(): void
    {
        Storage::fake('public');
        $user = $this->traveler();

        $html = $this->actingAs($user)->from('/profile')->followingRedirects()->put('/profile', [
            'first_name'    => 'Nico',
            'last_name'     => 'Baxal',
            'profile_photo' => UploadedFile::fake()->image('huge.jpg')->size(6144),
        ])->getContent();

        // Rendered open, carrying the server's own wording — a rejection that
        // slipped past the browser check lands where the browser's would.
        $this->assertStringContainsString('style="display:flex;"', $html);
        $this->assertMatchesRegularExpression('/id="photoErrorMsg">[^<]+\S/', $html);
    }
}
