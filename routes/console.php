<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// These two are what the "Trip reminders" and "Itinerary reminders" toggles in
// Settings switch on. Neither runs unless something invokes `schedule:run` every
// minute — cron on a server, or scripts/install-scheduler.ps1 on Windows.
Schedule::command('app:send-trip-reminders')->daily();
Schedule::command('app:send-itinerary-reminders')->hourly();

// Photo backfill, deliberately not scheduled by default.
//
// Each nightly pass costs up to 20 SerpAPI requests out of the 80/day pool it
// shares with live flight, hotel and restaurant lookups, and the backlog is
// large enough to keep spending for weeks (roughly 250 destinations and 100
// attractions without a photo). It also never truly finishes: TripObserver adds
// a bare Destination row for every new place a traveler types, and a name whose
// photo cannot be resolved stays NULL and is re-fetched every single night.
//
// So it runs when asked for, not on its own. Either:
//     php artisan app:fill-destination-images
//     php artisan app:fill-attraction-images
// or set SERPAPI_IMAGE_BACKFILL=true to hand it back to the scheduler.
if (config('services.serpapi.image_backfill')) {
    Schedule::command('app:fill-destination-images')->daily();
    Schedule::command('app:fill-attraction-images')->daily();
}
