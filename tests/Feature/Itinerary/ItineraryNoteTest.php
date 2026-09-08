<?php
namespace Tests\Feature\Itinerary;

use App\Console\Commands\BackfillItineraryNotes;
use App\Livewire\Traveler\TripPlannerWizard;
use App\Models\Itinerary;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every itinerary row the planner writes carries a one-line description.
 *
 * The attraction providers return no description of their own, so these rows
 * used to be saved with a null note and rendered as a bare title in the day
 * modal, while the AI planner's rows showed prose.
 */
class ItineraryNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_attraction_note_includes_type_city_rating_and_reviews(): void
    {
        $note = TripPlannerWizard::attractionNote([
            'type' => 'Tourist attraction', 'rating' => 4.4, 'reviews' => '12,043',
        ], 'Taipei');

        $this->assertSame('Tourist attraction in Taipei · 4.4★ (12,043 reviews)', $note);
    }

    public function test_attraction_note_degrades_when_fields_are_missing(): void
    {
        $this->assertSame('Attraction in Taipei', TripPlannerWizard::attractionNote([], 'Taipei'));
        $this->assertSame('Attraction', TripPlannerWizard::attractionNote([], ''));
        $this->assertSame(
            'Museum in Taipei · 4.0★',
            TripPlannerWizard::attractionNote(['type' => 'Museum', 'rating' => 4], 'Taipei')
        );
    }

    public function test_attraction_note_flags_free_entry(): void
    {
        $note = TripPlannerWizard::attractionNote(['type' => 'Park', 'isFree' => true], 'Taipei');

        $this->assertSame('Park in Taipei · Free entry', $note);
    }

    public function test_hotel_note_prefers_the_search_summary(): void
    {
        $note = TripPlannerWizard::hotelNote(['detail' => '3 Nights · Hotel · Taipei'], 'Taipei');

        $this->assertSame('3 Nights · Hotel · Taipei', $note);
    }

    public function test_hotel_note_composes_when_no_summary_is_given(): void
    {
        $this->assertSame(
            '2 Nights · Resort · Taipei',
            TripPlannerWizard::hotelNote(['nights' => 2, 'type' => 'Resort'], 'Taipei')
        );
        $this->assertSame('Accommodation · Taipei', TripPlannerWizard::hotelNote([], 'Taipei'));
    }

    public function test_backfill_composes_from_the_stored_row(): void
    {
        $shapes = [
            ['Check-in at Grand Pleasure',    'Hotel',    'Accommodation check-in in Taipei'],
            ['Check-out from Grand Pleasure', 'Hotel',    'Accommodation check-out in Taipei'],
            ['Visit Rainbow Ximending',       'Activity', 'Attraction in Taipei'],
            ['Lunch at Miznon',               'Activity', 'Lunch in Taipei'],
            ['Dinner at Din Tai Fung',        'Activity', 'Dinner in Taipei'],
            ['EVA Air arrival to Taipei',     'Flight',   'Flight in Taipei'],
        ];

        foreach ($shapes as [$title, $type, $expected]) {
            $row = new Itinerary(['title' => $title, 'type' => $type, 'location' => 'Taipei']);
            $this->assertSame($expected, BackfillItineraryNotes::composeNote($row), $title);
        }
    }

    public function test_backfill_fills_only_blank_notes_and_honours_dry_run(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        $blank = $trip->itinerary()->create([
            'title' => 'Visit Rainbow Ximending', 'type' => 'Activity', 'location' => 'Taipei',
            'start_datetime' => now(), 'end_datetime' => now()->addHour(), 'notes' => null,
        ]);
        $kept = $trip->itinerary()->create([
            'title' => 'Beitou Hot Spring', 'type' => 'Activity', 'location' => 'Taipei',
            'start_datetime' => now(), 'end_datetime' => now()->addHour(),
            'notes' => 'Relax in a natural open-air hot spring pool.',
        ]);

        $this->artisan('itinerary:backfill-notes', ['--dry-run' => true, '--trip' => $trip->id])
            ->assertSuccessful();
        $this->assertNull($blank->fresh()->notes, 'dry run must not write');

        $this->artisan('itinerary:backfill-notes', ['--trip' => $trip->id])->assertSuccessful();

        $this->assertSame('Attraction in Taipei', $blank->fresh()->notes);
        $this->assertSame('Relax in a natural open-air hot spring pool.', $kept->fresh()->notes,
            'an existing description must never be overwritten');
    }
}
