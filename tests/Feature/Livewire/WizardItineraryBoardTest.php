<?php
namespace Tests\Feature\Livewire;

use Tests\TestCase;

/**
 * The itinerary preview's day cards, as a board rather than a stack.
 *
 * Step 8 rendered one full-width card per day, so a five-day trip was five
 * tall rows and the cost card and Save Itinerary button at the top scrolled
 * out of reach before you reached day two. The days now sit side by side in
 * a grid that wraps.
 *
 * These read the template rather than rendering the component: step 8 needs
 * a generated itinerary and far more wizard state than a test sets up, and
 * what is being pinned here is a stylesheet rule. Same approach as
 * WizardEmergencyFundBackTest's step 8 and 9 assertions.
 */
class WizardItineraryBoardTest extends TestCase
{
    private function template(): string
    {
        return file_get_contents(
            resource_path('views/livewire/traveler/trip-planner-wizard.blade.php')
        );
    }

    private function daysRule(): string
    {
        $template = $this->template();
        $start    = strpos($template, '.itin8-days{');

        $this->assertNotFalse($start, '.itin8-days rule is gone');

        return substr($template, $start, strpos($template, '}', $start) - $start + 1);
    }

    public function test_the_days_are_a_grid_not_a_vertical_stack(): void
    {
        $rule = $this->daysRule();

        $this->assertStringContainsString('display:grid', $rule);
        $this->assertStringNotContainsString('flex-direction:column', $rule);
    }

    public function test_the_column_count_comes_from_the_trip_not_the_window(): void
    {
        // A four-day trip is one row of four on every screen. An auto-fill
        // track reflowed on each resize, so the same itinerary looked
        // different depending on the window.
        $template = $this->template();

        $this->assertStringContainsString('repeat(var(--day-cols,3),minmax(0,1fr))', $this->daysRule());
        $this->assertStringContainsString('--day-cols:{{ $dayCols }}', $template);
        // Scoped to the rule, not the file: the comment above it explains why
        // auto-fill was dropped, and matching the whole template caught that.
        $this->assertStringNotContainsString('auto-fill', $this->daysRule());
    }

    public function test_the_board_is_capped_at_five_columns(): void
    {
        // Past five the columns are too narrow to read, so a longer trip has
        // to wrap rather than keep subdividing the row.
        $this->assertStringContainsString(
            '$dayCols = max(1, min(5, count($optDays)));',
            $this->template()
        );
    }

    public function test_the_tracks_can_shrink_below_their_content(): void
    {
        // The load-bearing detail. A plain 1fr track still refuses to go below
        // its content's min-content width, which pushes the row wider than the
        // page and puts the sideways scrollbar back. minmax(0,1fr) is what
        // lets it shrink, and it is exactly what a later "simplify" edit
        // rewrites to 1fr without noticing.
        $this->assertStringContainsString('minmax(0,1fr)', $this->daysRule());
    }

    public function test_the_board_steps_down_on_narrow_screens(): void
    {
        // Five columns on a phone is five slivers. Each ceiling is also capped
        // against the trip's own length inline, so a two-day trip never opens
        // a third empty column at a breakpoint.
        $template = $this->template();

        $this->assertStringContainsString('@media (max-width:1100px){.itin8-days', $template);
        $this->assertStringContainsString('@media (max-width:560px){.itin8-days{grid-template-columns:1fr;}}', $template);
        $this->assertStringContainsString('--day-cols-md:{{ min(3, $dayCols) }}', $template);
        $this->assertStringContainsString('--day-cols-sm:{{ min(2, $dayCols) }}', $template);
    }

    public function test_the_timeline_rail_still_runs_through_the_icons(): void
    {
        // The card was made compact, which moved the icons; the rail's left
        // offset is derived from their geometry and silently drifts off centre
        // if only one of the two is adjusted.
        $template = $this->template();

        $this->assertStringContainsString('.itin8-timeline{position:relative;padding-left:22px;}', $template);
        // icon spans 0..26px from the column edge -> centre 13px
        $this->assertStringContainsString('left:-22px;top:10px;width:26px;height:26px', $template);
        // 2px rail at 12px -> centre 13px
        $this->assertStringContainsString(';position:absolute;left:12px;top:6px;bottom:6px;width:2px;', $template);
    }

    public function test_a_quiet_day_is_not_stretched_to_match_a_busy_one(): void
    {
        // align-items is the block axis in grid: without start, a day holding
        // one activity is padded out to the height of the tallest card beside
        // it, which is most of the reason a board reads worse than a stack.
        $this->assertStringContainsString('align-items:start', $this->daysRule());
    }

    public function test_a_long_activity_title_cannot_widen_its_column(): void
    {
        // Grid items default to min-width:auto and refuse to shrink below
        // their min-content width, pushing the track past its share.
        $template = $this->template();
        $start    = strpos($template, '.itin8-day-col{');

        $this->assertNotFalse($start);

        $rule = substr($template, $start, strpos($template, '}', $start) - $start + 1);

        $this->assertStringContainsString('min-width:0', $rule);
    }
}
