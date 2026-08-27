<?php
namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_loads_for_authenticated_user(): void
    {
        $user = User::factory()->create(['full_name' => 'Kent Pielago']);
        $response = $this->actingAs($user)->get('/profile');
        $response->assertStatus(200);
        $response->assertSee('Kent Pielago');
    }

    public function test_user_can_update_profile_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put('/profile', [
            'full_name'       => 'Updated Name',
            'phone'           => '09123456789',
            'country'         => 'Philippines',
            'currency_code'   => 'PHP',
            'currency_symbol' => '₱',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', [
            'id'        => $user->id,
            'full_name' => 'Updated Name',
            'phone'     => '09123456789',
            'country'   => 'Philippines',
        ]);
    }

    public function test_user_can_upload_profile_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put('/profile', [
            'full_name'     => $user->full_name,
            'profile_photo' => UploadedFile::fake()->image('photo.jpg', 200, 200),
        ]);

        $response->assertRedirect();
        $user->refresh();
        $this->assertNotNull($user->profile_photo);
        Storage::disk('public')->assertExists($user->profile_photo);
    }

    public function test_profile_update_replaces_old_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['profile_photo' => 'profile-photos/old.jpg']);
        Storage::disk('public')->put('profile-photos/old.jpg', 'fake-content');

        $this->actingAs($user)->put('/profile', [
            'full_name'     => $user->full_name,
            'profile_photo' => UploadedFile::fake()->image('new.jpg', 200, 200),
        ]);

        $user->refresh();
        Storage::disk('public')->assertMissing('profile-photos/old.jpg');
        Storage::disk('public')->assertExists($user->profile_photo);
    }

    public function test_profile_requires_authentication(): void
    {
        $this->get('/profile')->assertRedirect(route('login'));
    }

    public function test_full_name_is_required(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->put('/profile', ['full_name' => '']);
        $response->assertSessionHasErrors('full_name');
    }

    // The exact bug reported live: the Address card's country line was a
    // hardcoded "Philippines" string regardless of the traveler's actual
    // home city — a Montreal-based traveler saw "Montreal / Philippines".
    // It must be resolved from the real home city instead.
    public function test_profile_page_shows_the_real_country_for_the_home_city(): void
    {
        $user = User::factory()->create();
        UserProfile::create(['user_id' => $user->id, 'home_city' => 'Montreal']);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertSee('Montreal');
        $response->assertSee('Canada');
        $response->assertDontSee('Philippines');
    }

    // The same double-conversion-adjacent bug as the Profile Builder's own
    // review screen, in a different file: this summary card was showing
    // the peso ledger value (daily_budget) instead of the traveler's real
    // local number (daily_budget_local) with its matching currency symbol.
    public function test_profile_page_shows_the_local_budget_not_the_peso_ledger_value(): void
    {
        $user = User::factory()->create();
        UserProfile::create([
            'user_id'               => $user->id,
            'home_city'             => 'Vancouver',
            'daily_budget'          => 22247.23,
            'daily_budget_currency' => 'CAD',
            'daily_budget_local'    => 500,
        ]);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertSee('C$500');
        $response->assertDontSee('22,247');
    }

    public function test_profile_page_shows_pesos_when_no_local_currency_is_set(): void
    {
        $user = User::factory()->create();
        UserProfile::create([
            'user_id'      => $user->id,
            'home_city'    => 'Manila',
            'daily_budget' => 5000,
        ]);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertSee('5,000');
    }
}
