<?php

namespace App\Console\Commands;

use App\Models\Itinerary;
use App\Models\Notification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:send-itinerary-reminders')]
#[Description('Notify travelers before a scheduled itinerary stop')]
class SendItineraryReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $items = Itinerary::with('trip.user')
            ->whereBetween('start_datetime', [now()->addHour(), now()->addHours(2)])
            ->get();

        $sent = 0;

        foreach ($items as $item) {
            $user = $item->trip?->user;
            if (! $user || ! $user->notify_itinerary_reminders) continue;

            $exists = Notification::where('user_id', $user->id)
                ->where('trip_id', $item->trip_id)
                ->where('type', 'itinerary_reminder')
                ->where('message', 'like', "%{$item->title}%")
                ->exists();
            if ($exists) continue;

            Notification::create([
                'user_id' => $user->id,
                'trip_id' => $item->trip_id,
                'type'    => 'itinerary_reminder',
                'message' => "Coming up: \"{$item->title}\" at {$item->start_datetime->format('g:i A')}.",
                'is_read' => false,
            ]);
            $sent++;
        }

        $this->info("Sent {$sent} itinerary reminder(s).");
    }
}
