<?php
namespace Tests\Feature\Livewire;

use App\Livewire\Traveler\ItineraryManager;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Switching trips from the Itinerary dropdown.
 *
 * The server side of this was always right — $set fires
 * updatedSelectedTripId(), selectedDate is cleared and the new trip's dates
 * render. What was wrong was the calendar's Alpine state: x-data is evaluated
 * once, when the element is first initialised, and Livewire's morph reuses
 * that same element across a re-render. So selDate stayed on a date belonging
 * to the trip you had just left and mi stayed on a month index the new trip
 * might not span, leaving the calendar blank or empty-looking until the page
 * was reloaded.
 *
 * A wire:key carrying the trip id makes the morph replace the element rather
 * than patch it, which is what gets the new x-data read.
 */
class ItineraryTripSwitchTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, Trip, Trip} */
    private function travelerWithTwoTrips(): array
    {
        $user = User::factory()->create();

        $near = Trip::factory()->create([
            'user_id'    => $user->id,
            'trip_name'  => 'Manila to Tokyo',
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date'   => now()->addDays(14)->toDateString(),
        ]);

        // Deliberately in a different month, which is the case that broke: the
        // month index left over from the other trip pointed nowhere.
        $far = Trip::factory()->create([
            'user_id'    => $user->id,
            'trip_name'  => 'Cebu City to Siargao',
            'start_date' => now()->addDays(80)->toDateString(),
            'end_date'   => now()->addDays(84)->toDateString(),
        ]);

        return [$user, $near, $far];
    }

    public function test_the_calendar_is_keyed_on_the_selected_trip(): void
    {
        [$user, $near, $far] = $this->travelerWithTwoTrips();

        $component = Livewire::actingAs($user)->test(ItineraryManager::class);
        $first     = $component->get('selectedTripId');

        $component->assertSee('wire:key="itin-cal-' . $first . '"', false);

        $other = $first === $near->id ? $far->id : $near->id;
        $component->call('$set', 'selectedTripId', $other);

        // A different key is the whole mechanism: same key means Livewire
        // patches the element and Alpine keeps its stale seed.
        $component->assertSee('wire:key="itin-cal-' . $other . '"', false);
        $component->assertDontSee('wire:key="itin-cal-' . $first . '"', false);
    }

    public function test_switching_reseeds_the_agenda_date_onto_the_new_trip(): void
    {
        [$user, $near, $far] = $this->travelerWithTwoTrips();

        $component = Livewire::actingAs($user)->test(ItineraryManager::class);
        $other     = $component->get('selectedTripId') === $near->id ? $far : $near;

        $component->call('$set', 'selectedTripId', $other->id);

        // Neither trip is running today, so the agenda falls back to the
        // trip's own start date — which is what Alpine seeds selDate from.
        $component->assertSee("selDate: '" . $other->start_date->toDateString() . "'", false);
    }

    public function test_a_day_picked_on_the_old_trip_does_not_survive_the_switch(): void
    {
        [$user, $near, $far] = $this->travelerWithTwoTrips();

        $component = Livewire::actingAs($user)->test(ItineraryManager::class);
        $current   = $component->get('selectedTripId') === $near->id ? $near : $far;
        $other     = $current->is($near) ? $far : $near;

        $component->set('selectedDate', $current->start_date->toDateString());
        $component->call('$set', 'selectedTripId', $other->id);

        // updatedSelectedTripId() clears it; a date from the previous trip
        // would otherwise be read against the new trip's range.
        $this->assertNull($component->get('selectedDate'));
        $this->assertSame($other->id, $component->get('selectedTripId'));
    }

    public function test_each_dropdown_option_is_keyed_by_its_own_trip(): void
    {
        [$user, $near, $far] = $this->travelerWithTwoTrips();

        $component = Livewire::actingAs($user)->test(ItineraryManager::class);

        // Every option is the same shape, so a keyless morph pairs them by
        // position — exactly how a click can land on the wrong trip.
        $component->assertSee('wire:key="trip-opt-' . $near->id . '"', false);
        $component->assertSee('wire:key="trip-opt-' . $far->id . '"', false);
    }

    public function test_the_selected_trip_is_the_one_that_was_clicked(): void
    {
        [$user, $near, $far] = $this->travelerWithTwoTrips();

        $component = Livewire::actingAs($user)->test(ItineraryManager::class);

        foreach ([$far->id, $near->id, $far->id] as $id) {
            $component->call('$set', 'selectedTripId', $id);
            $this->assertSame($id, $component->get('selectedTripId'));
            $this->assertSame($id, $component->instance()->selectedTrip->id);
        }
    }
}
