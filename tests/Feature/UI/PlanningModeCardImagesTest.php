<?php
namespace Tests\Feature\UI;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The two cards on the planning-mode chooser (/trips at step 0).
 *
 * Each <img> pairs asset() with filemtime(public_path(...)) as a cache buster,
 * so a missing served file is a fatal error on the page rather than a broken
 * image — worth pinning down.
 *
 * The originals in stockimages/ are full-size camera files; only the resized
 * copies under public/stockimages/ are served. manualtrip.jpg is 4160x6240 and
 * 4 MB at source, which is why the size ceiling below exists: dropping the
 * original straight into public/ would work, and would quietly make this the
 * heaviest page in the app.
 */
class PlanningModeCardImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_cards_point_at_their_images(): void
    {
        $this->actingAs(User::factory()->create())->get('/trips')
            ->assertStatus(200)
            ->assertSee('stockimages/manualtrip.jpg', false)
            ->assertSee('stockimages/aipowered.png', false);
    }

    public function test_the_served_copies_exist_and_stay_web_sized(): void
    {
        foreach (['manualtrip.jpg', 'aipowered.png'] as $file) {
            $path = public_path("stockimages/{$file}");

            $this->assertFileExists($path, "{$file} is referenced by the card but not served");
            $this->assertLessThan(
                500 * 1024,
                filesize($path),
                "{$file} is too heavy to serve — resize it instead of copying the original"
            );
        }
    }
}
