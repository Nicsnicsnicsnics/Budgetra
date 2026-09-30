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
 * Both destinations are now rendered every time and CSS shows one, keyed off
 * :root[data-has-profile]. Constants can be frozen safely; what has to stay
 * current is the choice between them, and Livewire's replaceHtmlAttributes()
 * refreshes that attribute on every wire:navigate — the one piece of
 * per-request truth that reaches inside a persisted block.
 *
 * ProfileController::edit() still redirects, and that redirect is what these
 * tests mostly pin down. It is the guarantee behind the whole arrangement:
 * bookmarks, the other views linking to /profile, and any browser the
 * stylesheet never reaches.
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

    public function test_both_destinations_are_rendered_so_css_can_choose(): void
    {
        // Identical markup for both travelers is the point: no server state in
        // the href means nothing can freeze into the persisted sidebar.
        $fresh = User::factory()->create();
        $setUp = User::factory()->create();
        $this->profileFor($setUp);

        foreach ([$fresh, $setUp] as $user) {
            $html = $this->actingAs($user)->get('/dashboard')
                ->assertStatus(200)
                ->getContent();

            $this->assertStringContainsString('href="' . url('/profile') . '" wire:navigate', $html);
            $this->assertStringContainsString('href="' . url('/profile/setup') . '" wire:navigate', $html);
            $this->assertStringContainsString('data-profile-when="ready"', $html);
            $this->assertStringContainsString('data-profile-when="setup"', $html);

            // Exactly one pair — the swap wraps the Profile entry only, and
            // must not have been applied to Settings as well.
            $this->assertSame(2, substr_count($html, 'data-profile-when='));
        }
    }

    public function test_the_stylesheet_carries_the_rules_that_choose_between_them(): void
    {
        // Without these the mechanism degrades silently to two visible Profile
        // rows, which no HTML assertion elsewhere would notice.
        $css = file_get_contents(public_path('css/style.css'));

        $this->assertStringContainsString('.sidebar-profile-swap { display: contents; }', $css);
        $this->assertStringContainsString(
            '.sidebar-profile-swap[data-profile-when="setup"] { display: none; }',
            $css
        );
        $this->assertStringContainsString(
            ':root:not([data-has-profile]) .sidebar-profile-swap[data-profile-when="ready"] { display: none; }',
            $css
        );
        $this->assertStringContainsString(
            ':root:not([data-has-profile]) .sidebar-profile-swap[data-profile-when="setup"] { display: contents; }',
            $css
        );
    }

    public function test_the_root_element_says_whether_a_profile_exists(): void
    {
        // The single input the CSS reads. Scoped to the <html> tag because the
        // layout's skip script names the attribute in a string too.
        $fresh = User::factory()->create();
        $setUp = User::factory()->create();
        $this->profileFor($setUp);

        $this->assertStringNotContainsString(
            'data-has-profile',
            $this->rootTag($this->actingAs($fresh)->get('/dashboard')->getContent())
        );

        $this->assertStringContainsString(
            'data-has-profile',
            $this->rootTag($this->actingAs($setUp)->get('/dashboard')->getContent())
        );
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
        // The server-side guarantee: whichever link they arrived by, and
        // whether or not they pressed "Skip for now" — that flag is cosmetic
        // and lives only in localStorage.
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

    public function test_following_the_redirect_ends_on_the_builder_itself(): void
    {
        // A profile-less traveler now sees the /profile/setup link, but this
        // path still has to work: it is what every other view's link to
        // /profile lands on, and what a stale bookmark hits.
        $this->actingAs(User::factory()->create())
            ->followingRedirects()
            ->get('/profile')
            ->assertStatus(200)
            ->assertSee('Where does your journey begin?', false)
            ->assertDontSee('Travel Preferences', false);
    }

    /** The opening <html ...> tag, where the server flags profile state. */
    private function rootTag(string $html): string
    {
        $start = strpos($html, '<html');
        $this->assertNotFalse($start, 'no <html> element');

        return substr($html, $start, strpos($html, '>', $start) - $start + 1);
    }
}
