<?php

namespace App\Console\Commands;

use App\Models\Itinerary;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('itinerary:backfill-notes {--dry-run : Report what would change without writing} {--trip= : Limit to one trip id}')]
#[Description('Fill in a short description on itinerary rows saved before the planner wrote one')]
class BackfillItineraryNotes extends Command
{
    public function handle(): int
    {
        $query = Itinerary::query()
            ->where(fn ($q) => $q->whereNull('notes')->orWhere('notes', ''));

        if ($tripId = $this->option('trip')) {
            $query->where('trip_id', (int) $tripId);
        }

        $rows = $query->orderBy('trip_id')->orderBy('start_datetime')->get();

        if ($rows->isEmpty()) {
            $this->info('Nothing to backfill — every itinerary row already has a description.');
            return self::SUCCESS;
        }

        $dry     = (bool) $this->option('dry-run');
        $updated = 0;

        foreach ($rows as $row) {
            $note = self::composeNote($row);
            if ($note === null) continue;

            $this->line(sprintf(
                '  trip %-5s %-42s -> %s',
                $row->trip_id,
                mb_strimwidth((string) $row->title, 0, 42, '…'),
                $note
            ));

            if (! $dry) {
                $row->update(['notes' => $note]);
            }
            $updated++;
        }

        $this->newLine();
        $this->info($dry
            ? "Dry run — {$updated} row(s) would be updated."
            : "Backfilled {$updated} itinerary row(s).");

        return self::SUCCESS;
    }

    /**
     * Compose a description from what the row itself stores.
     *
     * These rows predate the planner writing a note, and the search payloads
     * they came from are long gone, so title/type/location is all there is to
     * work with. Kept in the same shape the planner now writes ("Attraction in
     * Taipei") so old and new trips read alike.
     */
    public static function composeNote(Itinerary $row): ?string
    {
        $title = trim((string) $row->title);
        $where = trim((string) $row->location);
        $in    = $where !== '' ? ' in ' . $where : '';

        $noun = match (true) {
            str_starts_with($title, 'Check-in at ')   => 'Accommodation check-in',
            str_starts_with($title, 'Check-out from') => 'Accommodation check-out',
            str_starts_with($title, 'Visit ')         => 'Attraction',
            str_starts_with($title, 'Lunch at ')      => 'Lunch',
            str_starts_with($title, 'Dinner at ')     => 'Dinner',
            str_starts_with($title, 'Breakfast at ')  => 'Breakfast',
            default => match ($row->type) {
                'Hotel'  => 'Accommodation',
                'Flight' => 'Flight',
                default  => 'Activity',
            },
        };

        return $noun . $in;
    }
}
