<?php
namespace App\Observers;

use App\Models\Destination;
use App\Models\MomentPhoto;
use App\Models\Notification;
use App\Models\Trip;
use Illuminate\Support\Facades\Storage;

class TripObserver
{
    public function created(Trip $trip): void
    {
        // autosaveDraft() creates a Trip row (status 'draft') on every
        // wizard autosave, well before the traveler has actually finished
        // planning — congratulating them here is premature, and when the
        // wizard later saves the real trip as a separate row, this draft
        // becomes a duplicate. Worse, once an abandoned draft is deleted,
        // notifications.trip_id (nullOnDelete) orphans this notification
        // with no trip_id — permanently invisible on the trip-scoped
        // Notifications page yet still counted as unread forever. Only
        // notify once the trip is a real, finalized save.
        if ($trip->status === 'draft') {
            return;
        }

        Notification::create([
            'user_id' => $trip->user_id,
            'trip_id' => $trip->id,
            'type'    => 'trip_created',
            'message' => "Congratulations! Your trip to {$trip->destination} has been saved.",
            'is_read' => false,
        ]);
    }

    // The trip planner's destination picker is a hardcoded city list, not
    // backed by the `destinations` table the admin panel manages — so a
    // place a traveler actually books a trip to (e.g. "Manila") can be
    // completely absent from Admin > Destinations. Mirror every real
    // destination/leg2_destination a trip is saved with into that table so
    // the admin list reflects what travelers actually pick, not just the
    // pre-seeded reference list. Runs on "saved" (create + update) since
    // draft autosaves update an existing trip's destination in place rather
    // than creating a new row each time.
    public function saved(Trip $trip): void
    {
        $this->syncDestination($trip->destination);
        if ($trip->is_multi_city) {
            $this->syncDestination($trip->leg2_destination);
        }
    }

    private function syncDestination(?string $name): void
    {
        $name = trim((string) $name);
        if ($name === '' || strtolower($name) === 'unknown') {
            return;
        }
        $exists = Destination::whereRaw('LOWER(name) = ?', [strtolower($name)])->exists();
        if (!$exists) {
            Destination::create(['name' => $name]);
        }
    }

    // expenses/moments/moment_photos all cascade-delete at the DB level
    // (see their cascadeOnDelete() foreign keys) when a trip is deleted —
    // that never touches the actual uploaded files on disk. The single-item
    // delete paths (ExpenseController::destroy(), MomentService::deleteMoment())
    // already clean those up individually, but nothing did so when a whole
    // trip goes at once. Runs on "deleting" (before the cascade fires, not
    // "deleted"/after), so the trip's child rows are still queryable here.
    public function deleting(Trip $trip): void
    {
        $paths = $trip->expenses()->whereNotNull('receipt_path')->pluck('receipt_path')
            ->merge(MomentPhoto::whereIn('moment_id', $trip->moments()->pluck('id'))->pluck('photo_path'));

        if ($paths->isNotEmpty()) {
            Storage::disk('public')->delete($paths->all());
        }
    }
}
