<?php
namespace Tests\Feature\UI;

use Tests\TestCase;

/**
 * Modal backdrops must use a neutral scrim.
 *
 * Two of them were tinted rgba(20,10,4,.45) — a warm brown. Over the light
 * themes that passes as a soft shadow, but the dark themes blur an already
 * near-black page behind it, so the tint is all that survives and the overlay
 * reads as a red cast.
 */
class ModalScrimThemeTest extends TestCase
{
    /** Any rgba() used as a full-screen overlay background must be neutral. */
    public function test_no_traveler_modal_backdrop_uses_a_tinted_scrim(): void
    {
        $offenders = [];

        foreach ($this->travelerViews() as $file) {
            $src = file_get_contents($file);
            // Overlay scrims: an rgba() sitting on a `background` next to the
            // inset:0 that makes the element full-screen.
            preg_match_all('/inset:\s*0;\s*background:\s*rgba\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i', $src, $m, PREG_SET_ORDER);
            foreach ($m as $hit) {
                [$all, $r, $g, $b] = $hit;
                // Neutral means the channels sit within a few points of each
                // other; a red cast shows up as r noticeably above g and b.
                if ((int) $r > (int) $g + 6 || (int) $r > (int) $b + 6) {
                    $offenders[] = basename($file) . ": rgba($r,$g,$b…)";
                }
            }
        }

        $this->assertSame([], $offenders,
            "Tinted modal scrim(s) found:\n" . implode("\n", $offenders));
    }

    /** @return string[] */
    private function travelerViews(): array
    {
        $roots = [
            resource_path('views/livewire/traveler'),
            resource_path('views/traveler'),
        ];

        $files = [];
        foreach ($roots as $root) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
            foreach ($it as $f) {
                if ($f->isFile() && str_ends_with($f->getFilename(), '.blade.php')) {
                    $files[] = $f->getPathname();
                }
            }
        }
        $this->assertNotEmpty($files);

        return $files;
    }
}
