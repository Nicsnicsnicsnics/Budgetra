<?php
namespace Tests\Feature\Livewire;

use App\Livewire\Traveler\TripPlannerWizard;
use Tests\TestCase;

/**
 * The AI provider fallback chain, and what it does with a refusal.
 *
 * generateItinerary() asks suggestItinerary() once per option against one
 * shared ~100s budget, and each call walks five providers in order. The
 * refusals that actually occur are not transient — an exhausted free-tier day
 * answers 429 and a key with no billing answers 402, both in well under a
 * second, for every option alike. Re-asking them spends the later options'
 * budget on an answer already known.
 */
class ItineraryProviderChainTest extends TestCase
{
    private function source(): string
    {
        return file_get_contents(app_path('Livewire/Traveler/TripPlannerWizard.php'));
    }

    public function test_a_provider_that_refused_is_not_asked_again_this_request(): void
    {
        $src = $this->source();

        $this->assertStringContainsString('private array $refusedThisRequest = [];', $src);
        $this->assertStringContainsString('if (isset($this->refusedThisRequest[$serviceClass])) continue;', $src);
        $this->assertStringContainsString('$this->refusedThisRequest[$serviceClass] = true;', $src);
    }

    public function test_the_memo_is_private_so_it_lasts_one_request(): void
    {
        // A public property would be persisted by Livewire and carried into
        // the next request, so a provider that recovered would stay skipped.
        $ref  = new \ReflectionClass(TripPlannerWizard::class);
        $prop = $ref->getProperty('refusedThisRequest');

        $this->assertTrue($prop->isPrivate());
        $this->assertFalse($prop->isStatic());
    }

    public function test_a_fresh_component_starts_with_nothing_refused(): void
    {
        $ref  = new \ReflectionClass(TripPlannerWizard::class);
        $prop = $ref->getProperty('refusedThisRequest');
        $prop->setAccessible(true);

        $this->assertSame([], $prop->getValue($ref->newInstanceWithoutConstructor()));
    }

    public function test_only_a_success_short_circuits_the_chain(): void
    {
        // The marking has to sit after the success return, or a provider that
        // worked would be struck off for the options that follow it.
        $src  = $this->source();
        $ok   = strpos($src, 'return $this->applyDepartureCost($result);');
        $mark = strpos($src, '$this->refusedThisRequest[$serviceClass] = true;');

        $this->assertNotFalse($ok);
        $this->assertNotFalse($mark);
        $this->assertGreaterThan($ok, $mark);
    }

    public function test_all_five_providers_are_still_in_the_chain(): void
    {
        // Skipping is per-request, not a removal — today's exhausted provider
        // is tomorrow's working one.
        $src = $this->source();

        foreach (['Mistral', 'OpenRouter', 'Groq', 'Gemini', 'Cerebras'] as $provider) {
            // Built by concatenation: in a double-quoted string PHP reads the
            // brace after a backslash as a literal one instead of interpolating.
            $this->assertStringContainsString($provider . 'Service::class', $src);
        }
    }
}
