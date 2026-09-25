<?php
namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Facade;
use Tests\TestCase;

/**
 * What the scheduler is allowed to run.
 *
 * The trip and itinerary reminder toggles in Settings are read by two console
 * commands, and those commands only run if something invokes
 * `php artisan schedule:run` every minute. Nothing did, which is why both
 * toggles saved a preference and then sat there doing nothing.
 *
 * Installing that trigger is the fix, but it comes with a trap: schedule:run
 * runs *everything* routes/console.php declares, and the two photo-backfill
 * commands spend real money. A pass costs up to 20 SerpAPI requests out of the
 * 80/day pool shared with live flight, hotel and restaurant lookups, and the
 * backlog is large enough to keep spending for weeks.
 *
 * So they are gated behind SERPAPI_IMAGE_BACKFILL and off by default. These
 * tests are the guard on that: nothing else in the suite would notice if one
 * were scheduled again, and the symptom would be a quota bill, not a failure.
 */
class ScheduledCommandsTest extends TestCase
{
    /** @return list<string> every command the scheduler would run */
    private function scheduledCommands(): array
    {
        return collect(app(Schedule::class)->events())
            ->map(fn ($event) => $event->command ?? $event->description ?? '')
            ->values()
            ->all();
    }

    private function assertScheduled(string $command): void
    {
        $this->assertTrue(
            collect($this->scheduledCommands())->contains(fn ($c) => str_contains($c, $command)),
            "{$command} should be scheduled, but is not. Its Settings toggle would never fire."
        );
    }

    private function assertNotScheduled(string $command, string $why): void
    {
        $this->assertFalse(
            collect($this->scheduledCommands())->contains(fn ($c) => str_contains($c, $command)),
            "{$command} should not be scheduled: {$why}"
        );
    }

    public function test_the_reminder_commands_are_scheduled(): void
    {
        // Without these two the "Trip reminders" and "Itinerary reminders"
        // toggles are decoration.
        $this->assertScheduled('app:send-trip-reminders');
        $this->assertScheduled('app:send-itinerary-reminders');
    }

    public function test_the_photo_backfill_does_not_run_by_default(): void
    {
        $this->assertFalse(
            config('services.serpapi.image_backfill'),
            'SERPAPI_IMAGE_BACKFILL should default to false'
        );

        $this->assertNotScheduled(
            'app:fill-destination-images',
            'it spends up to 20 SerpAPI requests a night from the pool live trip planning uses'
        );
        $this->assertNotScheduled(
            'app:fill-attraction-images',
            'it spends SerpAPI requests from the pool live trip planning uses'
        );
    }

    public function test_the_backfill_can_still_be_switched_on(): void
    {
        // Gated rather than deleted: someone who wants the photos should be
        // able to have them without editing code.
        config(['services.serpapi.image_backfill' => true]);

        // routes/console.php is only read once per process, so re-register the
        // schedule against the new config rather than trusting the cached one.
        // Clearing the facade's cached instance is the part that is easy to
        // miss: routes/console.php calls the Schedule *facade*, which would
        // otherwise keep handing out the schedule built at boot and this test
        // would inspect a fresh object nothing had written to.
        $schedule = new Schedule();
        $this->app->instance(Schedule::class, $schedule);
        Facade::clearResolvedInstance(Schedule::class);

        require base_path('routes/console.php');

        $commands = collect($schedule->events())->map(fn ($e) => $e->command ?? '');

        $this->assertTrue($commands->contains(fn ($c) => str_contains($c, 'app:fill-destination-images')));
        $this->assertTrue($commands->contains(fn ($c) => str_contains($c, 'app:fill-attraction-images')));
    }

    public function test_the_commands_behind_the_toggles_exist(): void
    {
        // A scheduled name that matches no command fails silently at 00:00.
        $registered = array_keys(\Illuminate\Support\Facades\Artisan::all());

        $this->assertContains('app:send-trip-reminders', $registered);
        $this->assertContains('app:send-itinerary-reminders', $registered);
    }
}
