<?php
namespace Tests\Feature\UI;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Where the sidebar's Profile link takes a traveler, and who decides.
 *
 * A traveler who has not set up their preferences belongs in the builder, not
 * on the profile page. The obvious place to put that condition is the href
 * itself, and that is where it used to live:
 *
 *     auth()->user()?->userProfile ? url('/profile') : url('/profile/setup')
 *
 * It cannot stay there. The sidebar is inside @persist('sidebar'), so
 * wire:navigate never re-renders it — every href is computed once at page load
 * and then frozen for the rest of the session. A traveler who signed in with no
 * profile got /profile/setup, finished the builder (which redirects with
 * wire:navigate, leaving the persisted sidebar untouched), and kept being sent
 * back to the setup wizard until they hard reloaded.
 *
 * So the link is unconditional and the decision moved to the server, where
 * ProfileController::edit() re-makes it on every request and nothing can go
 * stale.
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

    public function test_a_traveler_with_no_profile_is_sent_to_the_builder(): void
    {
        // The redirect, not the href, is what puts a new traveler in the
        // wizard — decided fresh on this request, so finishing the builder
        // ends it without a reload.
        $this->actingAs(User::factory()->create())->get('/profile')
            ->assertRedirect(route('profile.setup'));
    }

    public function test_the_builder_is_entered_as_the_full_wizard_not_a_quick_edit(): void
    {
        // No ?return= on the redirect: that parameter is the quick-edit path
        // from the profile page's per-section "Edit" links, which swaps Next
        // Step for a single Save Changes. A traveler with nothing set up needs
        // all seven steps.
        // assertLocation is exact, so a ?return= creeping in would fail here.
        $this->actingAs(User::factory()->create())->get('/profile')
            ->assertLocation(route('profile.setup'));
    }

    public function test_following_the_link_ends_on_the_builder_itself(): void
    {
        // The href stays /profile, so what a click actually lands on is the
        // far end of the redirect. wire:navigate takes its destination from
        // the fetch's final response.url, which is this.
        $this->actingAs(User::factory()->create())
            ->followingRedirects()
            ->get('/profile')
            ->assertStatus(200)
            ->assertSee('Where does your journey begin?', false)
            ->assertDontSee('Travel Preferences', false);
    }
}
