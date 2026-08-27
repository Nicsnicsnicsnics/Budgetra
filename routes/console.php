<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:send-trip-reminders')->daily();
Schedule::command('app:send-itinerary-reminders')->hourly();
Schedule::command('app:fill-destination-images')->daily();
Schedule::command('app:fill-attraction-images')->daily();
