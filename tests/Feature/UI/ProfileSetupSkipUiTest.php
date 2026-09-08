<?php
namespace Tests\Feature\UI;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The first-run "Set up your profile first" prompt and its skip link.
 *
 * The skip is remembered in localStorage, which is per-browser rather than
 * per-account, so the storage key has to carry the user id. Without that, one
 * account's skip leaks to the next person who signs in on the same browser and
 * a brand-new user starts already-skipped, never seeing the prompt at all.
 */
class ProfileSetupSkipUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_profile_sees_the_setup_prompt_and_skip_link(): void
    {
        $user = User::factory()->create(['role' => 'traveler']);

        $this->actingAs($user)->get('/itinerary')
            ->assertStatus(200)
            ->assertSee('Set up your profile first')
            ->assertSee('>Skip for now</button>', false)
            ->assertSee('data-empty-when="profile"', false)
            ->assertSee('data-empty-when="skipped"', false);
    }

    public function test_skip_state_is_scoped_to_the_signed_in_user(): void
    {
        $first  = User::factory()->create(['role' => 'traveler']);
        $second = User::factory()->create(['role' => 'traveler']);

        $this->actingAs($first)->get('/itinerary')
            ->assertSee('data-user-id="' . $first->id . '"', false)
            ->assertDontSee('data-user-id="' . $second->id . '"', false);

        $this->actingAs($second)->get('/itinerary')
            ->assertSee('data-user-id="' . $second->id . '"', false)
            ->assertDontSee('data-user-id="' . $first->id . '"', false);
    }

    public function test_user_with_a_profile_gets_no_prompt_and_no_skip_link(): void
    {
        $user = User::factory()->create(['role' => 'traveler']);
        UserProfile::create(['user_id' => $user->id, 'home_city' => 'Manila']);

        $this->actingAs($user)->get('/itinerary')
            ->assertStatus(200)
            ->assertSee('data-has-profile', false)
            ->assertDontSee('Set up your profile first')
            ->assertDontSee('>Skip for now</button>', false)
            ->assertDontSee('empty-state-skip', false);
    }
}
