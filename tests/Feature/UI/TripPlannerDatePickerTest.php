<?php
namespace Tests\Feature\UI;

use Tests\TestCase;

/**
 * Guards on the Plan Your Trip date pickers.
 *
 * Three defects made the pickers unusable at month-end:
 *
 *  1. The month seed ignored an already-chosen date, so every $wire.set()
 *     re-ran the Alpine factory and snapped the grid back to a fixed month —
 *     navigate to September, pick a day, and the calendar jumped away.
 *  2. pickDate() rebuilt the date from startMonth/endMonth at click time
 *     instead of using the clicked cell's own value, so a click in one month
 *     could be stored as the same day in another.
 *  3. Cell keys concatenated unpadded numbers, so Jan 11 and Nov 1 both came
 *     out as "d2026111" and x-for could reuse the wrong node.
 *
 * The pytManual() factory is pushed to the layout's script stack rather than
 * rendered inside the component, so a rendered-HTML assertion cannot reach it.
 * Asserting on the source is blunter than a behavioural test, but it holds
 * these three shut.
 */
class TripPlannerDatePickerTest extends TestCase
{
    private function wizardSource(): string
    {
        $path = resource_path('views/livewire/traveler/trip-planner-wizard.blade.php');
        $this->assertFileExists($path);

        return file_get_contents($path);
    }

    public function test_calendars_seed_from_a_chosen_date_then_fall_back_to_today(): void
    {
        $src = $this->wizardSource();

        $this->assertStringContainsString('monthOf(seed.startVal', $src);
        $this->assertStringContainsString('monthOf(seed.endVal', $src);
        $this->assertStringContainsString('now.getMonth() + 1', $src);
    }

    public function test_no_month_skipping_heuristic_is_present(): void
    {
        $src = $this->wizardSource();

        // An earlier version moved both calendars forward a month near
        // month-end, which pushed today off the grid entirely.
        $this->assertStringNotContainsString('roomy', $src);
        $this->assertStringNotContainsString('daysLeft', $src);
    }

    public function test_pick_date_uses_the_clicked_cell_value(): void
    {
        $src = $this->wizardSource();

        $this->assertStringContainsString("pickDate('start',cell.val)", $src);
        $this->assertStringContainsString("pickDate('end',cell.val)", $src);
        // Passing the bare day number is what allowed a September click to be
        // stored as an August date.
        $this->assertStringNotContainsString("pickDate('start',cell.d)", $src);
        $this->assertStringNotContainsString("pickDate('end',cell.d)", $src);
    }

    public function test_cell_keys_are_built_from_the_padded_iso_date(): void
    {
        $src = $this->wizardSource();

        $this->assertStringContainsString("key: 'd'+val", $src);
        $this->assertStringNotContainsString("key: 'd'+y+m+d", $src);
        $this->assertStringNotContainsString("key: 'e'+y+m+i", $src);
    }

    public function test_dates_are_resynced_from_the_server(): void
    {
        $src = $this->wizardSource();

        // updatedStartDate()/updatedEndDate() clear the opposite date on the
        // server; without these watchers the Alpine seed, read once at init,
        // silently drifts out of step with it.
        $this->assertStringContainsString("\$wire.\$watch('startDate'", $src);
        $this->assertStringContainsString("\$wire.\$watch('endDate'", $src);
        $this->assertStringContainsString('syncDate(which, val)', $src);
    }
}
