<?php
namespace Tests\Feature\UI;

use Tests\TestCase;

/**
 * The two planning-mode cards carry no feature pills.
 *
 * Both cards used to float a row of translucent pills over the bottom of
 * their photo — Transportation / Accommodation / Food and Dining /
 * Attractions on Manual, and Instant Itinerary / Personalized Picks /
 * Budget Optimized on AI. They were removed.
 */
class PlanningModeCardsTest extends TestCase
{
    private function template(): string
    {
        return file_get_contents(
            resource_path('views/livewire/traveler/trip-planner-wizard.blade.php')
        );
    }

    public static function pillLabels(): array
    {
        // Matched as >Label<, the pill's own element form. Four of these
        // words are also step names, headings and button text elsewhere in
        // this 3,800-line file — asserting on the bare word would fail
        // against "Select Accommodation" and prove nothing about the pills.
        return [
            // Manual Planning
            'transportation' => ['>Transportation<'],
            'accommodation'  => ['>Accommodation<'],
            'food'           => ['>Food and Dining<'],
            'attractions'    => ['>Attractions<'],
            // AI Powered Planning
            'instant'        => ['>Instant Itinerary<'],
            'personalized'   => ['>Personalized Picks<'],
            'budget'         => ['>Budget Optimized<'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pillLabels')]
    public function test_the_pill_is_gone(string $label): void
    {
        $this->assertStringNotContainsString($label, $this->template());
    }

    public function test_the_pill_markup_and_styling_are_both_gone(): void
    {
        // Leaving the CSS behind would be dead weight that reads as though
        // the pills are still meant to come back.
        $template = $this->template();

        $this->assertStringNotContainsString('mode-tags', $template);
        $this->assertStringNotContainsString('mode-tag', $template);
    }

    public function test_the_cards_themselves_still_render(): void
    {
        // Guards the assertions above from passing because the whole mode
        // screen was deleted.
        $template = $this->template();

        $this->assertStringContainsString('Manual Planning', $template);
        $this->assertStringContainsString('AI Powered Planning', $template);
        $this->assertStringContainsString("selectPlanningMode('manual')", $template);
        $this->assertStringContainsString("route('trips.plan.ai')", $template);
        // Both photos and the card shell survived the edit.
        $this->assertSame(2, substr_count($template, 'class="mode-img-wrap"'));
        $this->assertSame(2, substr_count($template, 'class="mode-card"'));
    }
}
