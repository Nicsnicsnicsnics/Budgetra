<?php
namespace Tests\Feature\UI;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The sidebar's Profile link, which must not depend on server state.
 *
 * The sidebar is inside @persist('sidebar'), so wire:navigate never re-renders
 * it — every href is computed once at page load and then frozen for the rest
 * of the session. This link used to read
 *
 *     auth()->user()?->userProfile ? url('/profile') : url('/profile/setup')
 *
 * so a traveler who signed in with no profile got /profile/setup, finished the
 * builder (which redirects with wire:navigate, leaving the persisted sidebar
 * untouched), and kept being sent back to the setup wizard until they hard
 * reloaded. /profile already renders a "Set Up Preferences" prompt when there
 * is no profile, so the condition bought nothing and could only go stale.
 */
class SidebarProfileLinkTest extends TestCase
{
    use RefreshDatabase;

    private function profileFor(User $user): UserProfile
    {
        return UserProfile::create([
            'user_id'      => $user->id,
            'home_city'    => 'Cebu City',
            'daily_budget' => 2000,
            'travel_style' => 'Solo',
        ]);
    }

    public function test_the_link_is_the_same_whether_a_profile_exists_or_not(): void
    {
        // The whole point: no server state in the href, so nothing can freeze
        // into the persisted sidebar.
        $fresh = User::factory()->create();
        $setUp = User::factory()->create();
        $this->profileFor($setUp);

        $a = $this->actingAs($fresh)->get('/dashboard');
        $b = $this->actingAs($setUp)->get('/dashboard');

        foreach ([$a, $b] as $res) {
            $res->assertStatus(200);
            $res->assertSee('href="' . url('/profile') . '" wire:navigate', false)
                ->assertDontSee('href="' . url('/profile/setup') . '" wire:navigate', false);
        }
    }

    public function test_a_traveler_who_finished_setup_reaches_their_profile(): void
    {
        $user = User::factory()->create();
        $this->profileFor($user);

        $this->actingAs($user)->get('/profile')
            ->assertStatus(200)
            ->assertDontSee("You haven't set up your travel preferences yet", false);
    }

    public function test_a_traveler_with_no_profile_is_offered_the_builder(): void
    {
        // Landing on /profile without one is not a dead end — the page says so
        // and links onward, which is why the sidebar needs no condition.
        $this->actingAs(User::factory()->create())->get('/profile')
            ->assertStatus(200)
            ->assertSee("You haven't set up your travel preferences yet", false)
            ->assertSee(route('profile.setup'), false);
    }
}
