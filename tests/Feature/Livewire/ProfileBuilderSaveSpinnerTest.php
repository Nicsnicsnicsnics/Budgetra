<?php
namespace Tests\Feature\Livewire;

use App\Livewire\Traveler\ProfileBuilder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Confirm Profile and Save Changes both end in a wire:navigate redirect.
 *
 * The fetch for the next page runs after the Livewire commit has already
 * finished, so a wire:loading spinner stopped there: the button flicked back
 * to its label and sat idle for the whole navigation — which is the part the
 * traveler is actually waiting on. These latch on the click instead, and a
 * refused save is what releases them.
 */
class ProfileBuilderSaveSpinnerTest extends TestCase
{
    use RefreshDatabase;

    private function builder(array $params = [])
    {
        return Livewire::actingAs(User::factory()->create())->test(ProfileBuilder::class, $params);
    }

    public function test_confirm_profile_latches_into_its_spinner(): void
    {
        $html = $this->builder()->set('step', 7)->html();

        $this->assertStringContainsString('x-data="{ busy: false }" x-on:click="busy = true"', $html);
        $this->assertStringContainsString('<span x-show="busy" x-cloak><i class="fa-solid fa-spinner fa-spin"></i></span>', $html);
        // The version that could not survive the navigation is gone.
        $this->assertStringNotContainsString('wire:target="confirmProfile"', $html);
    }

    public function test_the_button_releases_when_the_save_is_refused(): void
    {
        $html = $this->builder()->set('step', 7)->html();

        // Otherwise a profile that could not be saved would leave the traveler
        // staring at a spinner that never ends.
        $this->assertStringContainsString('x-on:profile-save-failed.window="busy = false"', $html);
    }

    public function test_a_refused_save_announces_itself(): void
    {
        // The only foreign-currency budget with no reachable rate: no key
        // configured, so the daily budget cannot be turned into pesos.
        config(['services.currency_converter.key' => '']);

        $this->builder()
            ->set('homeCity', 'Tokyo')
            ->set('dailyBudget', 8000)
            ->set('step', 7)
            ->call('confirmProfile')
            ->assertDispatched('profile-save-failed')
            ->assertSet('step', 7);
    }

    public function test_a_good_save_redirects_instead(): void
    {
        $this->builder()
            ->set('homeCity', 'Manila')
            ->set('dailyBudget', 2000)
            ->set('step', 7)
            ->call('confirmProfile')
            ->assertNotDispatched('profile-save-failed')
            ->assertRedirect(route('trips.plan'));
    }

    public function test_save_changes_latches_and_releases_on_either_refusal(): void
    {
        $html = $this->builder(['returnTo' => 'profile.edit'])->set('step', 4)->html();

        $this->assertStringContainsString('x-on:profile-save-failed.window="busy = false"', $html);
        // saveAndReturn also refuses outright when the step is incomplete.
        $this->assertStringContainsString('x-on:profile-missing.window="busy = false"', $html);
        $this->assertStringContainsString('busy = true; $wire.syncInterests(', $html);
    }
}
