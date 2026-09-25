<?php
namespace Tests\Feature\UI;

use Tests\TestCase;

/**
 * Nothing Alpine toggles may carry its layout in a style attribute.
 *
 * x-show hides by setting an inline display:none and reveals by calling
 * style.removeProperty('display') — which takes any inline display the
 * element already had with it. So an x-show'd element styled
 * `display:flex;...` inline comes back as plain flow the first time it is
 * shown: on the "No trips found" panel that meant the icon snapped hard
 * left, the heading was centred only by its text-align and the paragraph
 * drifted off on its own.
 *
 * savings/index.blade.php hit this first and solved it with .sg-empty. The
 * shared .tab-pane / .tab-empty / .tab-pager rules are the same fix.
 */
class XShowDisplayTest extends TestCase
{
    public static function templates(): array
    {
        return [
            'saved trips' => ['saved-trips.blade.php'],
            'multi trips' => ['multi-trip-hub.blade.php'],
        ];
    }

    private function template(string $file): string
    {
        return file_get_contents(resource_path('views/livewire/traveler/' . $file));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('templates')]
    public function test_no_toggled_element_styles_its_own_display(string $file): void
    {
        $lines = explode("\n", $this->template($file));
        $offenders = [];

        foreach ($lines as $i => $line) {
            if (! str_contains($line, 'x-show')) {
                continue;
            }

            // An element's attributes can wrap onto the following line.
            $chunk = $line;
            if (! str_contains($line, 'style=') && ! str_contains($line, '>')) {
                $chunk .= $lines[$i + 1] ?? '';
            }

            if (preg_match_all('/style="([^"]*)"/', $chunk, $m)) {
                foreach ($m[1] as $style) {
                    if (preg_match('/(^|;)\s*display:/', $style)) {
                        $offenders[] = ($i + 1) . ': ' . trim($line);
                    }
                }
            }
        }

        $this->assertSame([], $offenders, "x-show strips an inline display in {$file}");
    }

    public function test_the_detector_would_catch_a_real_offender(): void
    {
        // Proves the regex above matches what it claims to, rather than
        // passing because it matches nothing at all.
        $pattern = '/(^|;)\s*display:/';

        $this->assertSame(1, preg_match($pattern, 'display:flex;flex-direction:column;'));
        $this->assertSame(1, preg_match($pattern, 'min-height:360px;display:flex;'));
        // A property that merely ends in "display" is not one.
        $this->assertSame(0, preg_match($pattern, 'backdrop-display:none;'));
        $this->assertSame(0, preg_match($pattern, 'flex:0 0 auto;max-width:100px;'));
    }

    public function test_the_shared_layout_classes_exist(): void
    {
        // The templates now lean on these; a rename would leave every toggled
        // panel unstyled, and the test above would still pass.
        $css = file_get_contents(public_path('css/style.css'));

        $this->assertStringContainsString('.tab-pane { display: flex; flex-direction: column; }', $css);
        $this->assertMatchesRegularExpression('/\.tab-empty \{[^}]*display: flex;/s', $css);
        $this->assertMatchesRegularExpression('/\.tab-empty \{[^}]*align-items: center;/s', $css);
        $this->assertMatchesRegularExpression('/\.tab-empty \{[^}]*justify-content: center;/s', $css);
        $this->assertMatchesRegularExpression('/\.tab-pager \{[^}]*display: flex;/s', $css);
        $this->assertStringContainsString('.tab-pager-gap', $css);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('templates')]
    public function test_the_empty_states_use_the_shared_class(string $file): void
    {
        $template = $this->template($file);

        // Both the never-had-any state and the nothing-matched state.
        $this->assertSame(2, substr_count($template, 'class="tab-empty"'), $file);
        $this->assertStringContainsString('class="tab-pane"', $template);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('templates')]
    public function test_the_no_matches_panel_still_says_the_right_thing(string $file): void
    {
        // Guards the class swap from having eaten the copy.
        $template = $this->template($file);

        $this->assertStringContainsString('No trips found', $template);
        $this->assertStringContainsString('Try searching another trip.', $template);
    }
}
