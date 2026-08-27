<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Trip;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:send-trip-reminders')]
#[Description('Notify travelers a few days before an upcoming trip starts')]
class SendTripReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $trips = Trip::with('user')
            ->whereIn('status', ['upcoming', 'active'])
            ->whereDate('start_date', now()->addDays(3)->toDateString())
            ->get();

        foreach ($trips as $trip) {
            if (! $trip->user || ! $trip->user->notify_trip_reminders) continue;

            $exists = Notification::where('user_id', $trip->user_id)
                ->where('trip_id', $trip->id)
                ->where('type', 'trip_reminder')
                ->exists();
            if ($exists) continue;

            Notification::create([
                'user_id' => $trip->user_id,
                'trip_id' => $trip->id,
                'type'    => 'trip_reminder',
                'message' => "Your trip to {$trip->destination} starts in 3 days ({$trip->start_date->format('M j, Y')}).",
                'is_read' => false,
            ]);
        }

        $this->info("Sent {$trips->count()} trip reminder check(s).");
    }
}
