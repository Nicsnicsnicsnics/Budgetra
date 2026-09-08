<?php
namespace Tests\Feature\UI;

use App\Models\SavingsGoal;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The progress-meter ramp.
 *
 * Two bars in this app look identical and read opposite ways: the multi-trip
 * bars fill as a budget is *spent* (more is worse) while the savings bar fills
 * as a goal is *reached* (more is better). The ramp therefore cannot simply
 * follow the number — colouring a nearly-finished savings goal red would
 * invert its meaning.
 */
class MeterColorTest extends TestCase
{
    use RefreshDatabase;

    public function test_spend_runs_teal_to_amber_to_red(): void
    {
        $this->assertSame('var(--meter-good)', meter_color(0));
        $this->assertSame('var(--meter-good)', meter_color(69));
        $this->assertSame('var(--meter-warn)', meter_color(70));
        $this->assertSame('var(--meter-warn)', meter_color(89));
        $this->assertSame('var(--meter-bad)',  meter_color(90));
        $this->assertSame('var(--meter-bad)',  meter_color(100));
    }

    public function test_over_budget_stays_red(): void
    {
        // The bar's width is clamped to 100%; the colour must not be, or an
        // over-budget trip would land at the top of the ramp rather than past it.
        $this->assertSame('var(--meter-bad)', meter_color(140));
        $this->assertSame('var(--meter-bad)', meter_color(1000));
    }

    public function test_progress_runs_the_other_way(): void
    {
        $this->assertSame('var(--meter-bad)',  meter_color(0,   'progress'));
        $this->assertSame('var(--meter-bad)',  meter_color(29,  'progress'));
        $this->assertSame('var(--meter-warn)', meter_color(30,  'progress'));
        $this->assertSame('var(--meter-warn)', meter_color(69,  'progress'));
        $this->assertSame('var(--meter-good)', meter_color(70,  'progress'));
        $this->assertSame('var(--meter-good)', meter_color(100, 'progress'));
    }

    public function test_the_two_kinds_are_genuinely_inverted(): void
    {
        // The same ratio must not produce the same colour at the ends.
        $this->assertNotSame(meter_color(95), meter_color(95, 'progress'));
        $this->assertNotSame(meter_color(5),  meter_color(5,  'progress'));
    }

    public function test_the_hardcoded_two_state_comparison_bar_is_gone(): void
    {
        $src = file_get_contents(
            resource_path('views/livewire/traveler/multi-trip-hub.blade.php')
        );

        // Was a flat two-state green/red pair. Assert on the markup that
        // applied it: the same hexes still appear on the comparison card
        // borders and status pills (a separate element, out of scope), and the
        // class names themselves survive in the comment explaining the change.
        $this->assertStringNotContainsString('cmp-bar-{{ $over', $src);
        $this->assertStringContainsString('meter_color($pctRaw)', $src);
    }

    public function test_the_savings_bar_asks_for_the_progress_ramp(): void
    {
        $src = file_get_contents(
            resource_path('views/livewire/traveler/savings-goal-manager.blade.php')
        );

        $this->assertStringContainsString("meter_color(\$cardPct, 'progress')", $src);
    }

    public function test_the_meter_tokens_are_defined_for_light_and_dark(): void
    {
        $css = file_get_contents(public_path('css/style.css'));

        $this->assertStringContainsString('--meter-good:', $css);
        $this->assertStringContainsString('--meter-warn: var(--warning)', $css);
        $this->assertStringContainsString('--meter-bad:  var(--danger)', $css);
        // The teal needs its own dark-theme value; #0D9488 is ~2.6:1 there.
        $this->assertSame(2, substr_count($css, '--meter-good:'));
    }
}
