<?php
namespace Tests\Feature\Livewire;

use App\Livewire\Traveler\ItineraryManager;
use App\Models\Itinerary;
use App\Models\Moment;
use App\Models\MomentPhoto;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ItineraryManagerTest extends TestCase
{
    use RefreshDatabase;

    private function makeTrip(User $user): Trip
    {
        return Trip::factory()->create([
            'user_id'    => $user->id,
            'start_date' => now()->toDateString(),
            'end_date'   => now()->addDays(3)->toDateString(),
        ]);
    }

    public function test_itinerary_page_loads(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/itinerary')->assertStatus(200);
    }

    public function test_component_mounts_with_first_trip(): void
    {
        $user = User::factory()->create();
        $trip = $this->makeTrip($user);
        Livewire::actingAs($user)
            ->test(ItineraryManager::class)
            ->assertSet('selectedTripId', $trip->id);
    }

    public function test_can_open_add_pin_modal_for_an_ongoing_trip(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id'    => $user->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date'   => now()->addDay()->toDateString(),
        ]);

        Livewire::actingAs($user)
            ->test(ItineraryManager::class)
            ->call('selectTrip', $trip->id)
            ->call('openAddPinModal', 10.0, 120.0)
            ->assertSet('showPinModal', true)
            ->assertSet('momentBlockedMessage', '');
    }

    public function test_cannot_open_add_pin_modal_for_an_upcoming_trip(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id'    => $user->id,
            'start_date' => now()->addWeek()->toDateString(),
            'end_date'   => now()->addWeek()->addDays(3)->toDateString(),
        ]);

        Livewire::actingAs($user)
            ->test(ItineraryManager::class)
            ->call('selectTrip', $trip->id)
            ->call('openAddPinModal', 10.0, 120.0)
            ->assertSet('showPinModal', false)
            ->assertSet('momentBlockedMessage', 'You can only post moments for ongoing trips.');
    }

    public function test_cannot_open_add_pin_modal_for_a_completed_trip(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id'    => $user->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date'   => now()->subMonth()->addDays(3)->toDateString(),
        ]);

        Livewire::actingAs($user)
            ->test(ItineraryManager::class)
            ->call('selectTrip', $trip->id)
            ->call('openAddPinModal', 10.0, 120.0)
            ->assertSet('showPinModal', false)
            ->assertSet('momentBlockedMessage', 'You can only post moments for ongoing trips.');
    }

    public function test_savePin_rejects_a_non_ongoing_trip_even_if_called_directly(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id'    => $user->id,
            'start_date' => now()->addWeek()->toDateString(),
            'end_date'   => now()->addWeek()->addDays(3)->toDateString(),
        ]);

        // Bypasses openAddPinModal()'s own guard entirely — simulates a
        // request sent straight to savePin(), the scenario its own
        // server-side abort_if() is there to catch.
        Livewire::actingAs($user)
            ->test(ItineraryManager::class)
            ->call('selectTrip', $trip->id)
            ->set('pinPlaceName', 'Test Spot')
            ->set('pinVisitedDate', now()->toDateString())
            ->set('pinLat', 10.0)
            ->set('pinLng', 120.0)
            ->call('savePin')
            ->assertStatus(403);

        $this->assertDatabaseMissing('moments', ['trip_id' => $trip->id]);
    }

    public function test_selecting_trip_loads_days(): void
    {
        $user = User::factory()->create();
        $trip = $this->makeTrip($user);
        Livewire::actingAs($user)
            ->test(ItineraryManager::class)
            ->call('selectTrip', $trip->id)
            ->assertSet('selectedTripId', $trip->id);
    }

    public function test_selecting_a_day(): void
    {
        $user = User::factory()->create();
        $trip = $this->makeTrip($user);
        $date = now()->toDateString();
        Livewire::actingAs($user)
            ->test(ItineraryManager::class)
            ->call('selectDay', $date)
            ->assertSet('selectedDate', $date);
    }

    // ── Moments: travel pins ────────────────────────────────

    public function test_add_pin_creates_moment_with_coordinates(): void
    {
        $user = User::factory()->create();
        $trip = $this->makeTrip($user);

        Livewire::actingAs($user)
            ->test(ItineraryManager::class, ['tab' => 'moments'])
            ->call('selectTrip', $trip->id)
            ->call('openAddPinModal', 10.3157, 123.8854)
            ->assertSet('showPinModal', true)
            ->assertSet('pinModalMode', 'add')
            ->set('pinPlaceName', "Magellan's Cross")
            ->set('pinDescription', 'Historic landmark')
            ->set('pinVisitedDate', '2026-08-01')
            ->call('savePin')
            ->assertSet('showPinModal', false)
            ->assertDispatched('pin-saved');

        $this->assertDatabaseHas('moments', [
            'trip_id'    => $trip->id,
            'place_name' => "Magellan's Cross",
        ]);
        $moment = Moment::where('trip_id', $trip->id)->first();
        $this->assertEqualsWithDelta(10.3157, (float) $moment->lat, 0.0001);
        $this->assertEqualsWithDelta(123.8854, (float) $moment->lng, 0.0001);
    }

    public function test_add_pin_with_multiple_photos_stores_all_of_them(): void
    {
        Storage::fake('public');
        $user   = User::factory()->create();
        $trip   = $this->makeTrip($user);
        $photos = [UploadedFile::fake()->image('spot1.jpg'), UploadedFile::fake()->image('spot2.jpg')];

        Livewire::actingAs($user)
            ->test(ItineraryManager::class, ['tab' => 'moments'])
            ->call('selectTrip', $trip->id)
            ->call('openAddPinModal', 10.0, 123.0)
            ->set('pinPlaceName', 'Beach')
            ->set('pinVisitedDate', '2026-08-02')
            ->set('pinPhotos', $photos)
            ->call('savePin');

        $moment = Moment::where('trip_id', $trip->id)->first();
        $this->assertCount(2, $moment->photos);
        foreach ($moment->photos as $photo) {
            Storage::disk('public')->assertExists($photo->photo_path);
        }
    }

    public function test_remove_new_photo_before_saving_excludes_it(): void
    {
        Storage::fake('public');
        $user   = User::factory()->create();
        $trip   = $this->makeTrip($user);
        $photos = [UploadedFile::fake()->image('keep.jpg'), UploadedFile::fake()->image('drop.jpg')];

        Livewire::actingAs($user)
            ->test(ItineraryManager::class, ['tab' => 'moments'])
            ->call('selectTrip', $trip->id)
            ->call('openAddPinModal', 10.0, 123.0)
            ->set('pinPlaceName', 'Beach')
            ->set('pinVisitedDate', '2026-08-02')
            ->set('pinPhotos', $photos)
            ->call('removeNewPhoto', 1) // drop the second selection
            ->assertCount('pinPhotos', 1)
            ->call('savePin');

        $moment = Moment::where('trip_id', $trip->id)->first();
        $this->assertCount(1, $moment->photos);
    }

    public function test_remove_existing_photo_deletes_file_and_row(): void
    {
        Storage::fake('public');
        $user   = User::factory()->create();
        $trip   = $this->makeTrip($user);
        $moment = Moment::create([
            'trip_id' => $trip->id, 'place_name' => 'Spot',
            'visited_date' => '2026-08-01', 'lat' => 10.0, 'lng' => 123.0,
        ]);
        $path = 'moment-photos/existing.jpg';
        Storage::disk('public')->put($path, 'fake-content');
        $photo = MomentPhoto::create(['moment_id' => $moment->id, 'photo_path' => $path]);

        Livewire::actingAs($user)
            ->test(ItineraryManager::class, ['tab' => 'moments'])
            ->call('selectTrip', $trip->id)
            ->call('openEditPinModal', $moment->id)
            ->assertCount('pinExistingPhotos', 1)
            ->call('removeExistingPhoto', $photo->id)
            ->assertCount('pinExistingPhotos', 0);

        $this->assertDatabaseMissing('moment_photos', ['id' => $photo->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_edit_pin_updates_existing_moment(): void
    {
        $user   = User::factory()->create();
        $trip   = $this->makeTrip($user);
        $moment = Moment::create([
            'trip_id' => $trip->id, 'place_name' => 'Old Name',
            'visited_date' => '2026-08-01', 'lat' => 10.0, 'lng' => 123.0,
        ]);

        Livewire::actingAs($user)
            ->test(ItineraryManager::class, ['tab' => 'moments'])
            ->call('selectTrip', $trip->id)
            ->call('openEditPinModal', $moment->id)
            ->assertSet('pinModalMode', 'edit')
            ->assertSet('pinPlaceName', 'Old Name')
            ->set('pinPlaceName', 'New Name')
            ->call('savePin');

        $this->assertDatabaseHas('moments', ['id' => $moment->id, 'place_name' => 'New Name']);
        $this->assertDatabaseCount('moments', 1); // edit, not a duplicate insert
    }

    public function test_delete_pin_removes_moment_and_its_photos(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $trip = $this->makeTrip($user);
        $path = 'moment-photos/test.jpg';
        Storage::disk('public')->put($path, 'fake-content');
        $moment = Moment::create([
            'trip_id' => $trip->id, 'place_name' => 'Spot', 'visited_date' => '2026-08-01',
            'lat' => 10.0, 'lng' => 123.0,
        ]);
        $photo = MomentPhoto::create(['moment_id' => $moment->id, 'photo_path' => $path]);

        Livewire::actingAs($user)
            ->test(ItineraryManager::class, ['tab' => 'moments'])
            ->call('selectTrip', $trip->id)
            ->call('confirmDeletePin', $moment->id)
            ->assertSet('pinToDelete', $moment->id)
            ->call('deletePin')
            ->assertDispatched('pin-deleted');

        $this->assertDatabaseMissing('moments', ['id' => $moment->id]);
        $this->assertDatabaseMissing('moment_photos', ['id' => $photo->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_pins_passed_to_map_are_ordered_by_visited_date(): void
    {
        $user = User::factory()->create();
        $trip = $this->makeTrip($user);
        Moment::create(['trip_id' => $trip->id, 'place_name' => 'Second', 'visited_date' => '2026-08-05', 'lat' => 1, 'lng' => 1]);
        Moment::create(['trip_id' => $trip->id, 'place_name' => 'First',  'visited_date' => '2026-08-01', 'lat' => 2, 'lng' => 2]);

        $html = Livewire::actingAs($user)
            ->test(ItineraryManager::class, ['tab' => 'moments'])
            ->call('showTripOnMap', $trip->id) // drill into this trip's Moments map
            ->html();

        $firstPos  = strpos($html, 'First');
        $secondPos = strpos($html, 'Second');
        $this->assertNotFalse($firstPos);
        $this->assertNotFalse($secondPos);
        $this->assertLessThan($secondPos, $firstPos, 'Earlier visited_date should be serialized first for polyline ordering.');
    }

    public function test_cannot_edit_pin_belonging_to_another_users_trip(): void
    {
        $owner  = User::factory()->create();
        $other  = User::factory()->create();
        $trip   = $this->makeTrip($owner);
        $moment = Moment::create([
            'trip_id' => $trip->id, 'place_name' => 'Secret',
            'visited_date' => '2026-08-01', 'lat' => 1, 'lng' => 1,
        ]);

        // $other has no trip selected that resolves to $trip (selectedTrip is
        // scoped to auth()->id()), so editing must no-op, not leak the data.
        Livewire::actingAs($other)
            ->test(ItineraryManager::class, ['tab' => 'moments'])
            ->call('openEditPinModal', $moment->id)
            ->assertSet('showPinModal', false)
            ->assertSet('pinPlaceName', '');
    }

    // selectedTripId is a public property, settable directly by a client
    // request without ever going through selectTrip()'s ownership check —
    // Livewire::set() is the standard way to simulate exactly that (it's
    // the same mechanism a wire:model binding or a hand-crafted request
    // uses). Confirms getEventsProperty()/getDayItemsProperty() can't be
    // used to read another traveler's itinerary this way.
    public function test_tampering_selected_trip_id_does_not_leak_another_users_itinerary(): void
    {
        $attacker = User::factory()->create();
        $victim   = User::factory()->create();
        $victimTrip = $this->makeTrip($victim);
        Itinerary::create([
            'trip_id' => $victimTrip->id, 'title' => 'VICTIM SECRET PLAN',
            'type' => 'Activity', 'start_datetime' => now()->addDay()->toDateString() . ' 19:00:00',
        ]);

        $component = Livewire::actingAs($attacker)
            ->test(ItineraryManager::class, ['tab' => 'itinerary'])
            ->set('selectedTripId', $victimTrip->id)
            ->set('showDayModal', true)
            ->set('selectedDate', now()->addDay()->toDateString());

        $component->assertDontSee('VICTIM SECRET PLAN');
        $this->assertEmpty($component->get('events'));
        $this->assertCount(0, $component->get('dayItems'));
    }

    // Same tampering vector as above, but against the destructive action —
    // confirms deleteItem() can't be used to delete another traveler's
    // itinerary item just by pointing selectedTripId at their trip.
    public function test_tampering_selected_trip_id_cannot_delete_another_users_itinerary_item(): void
    {
        $attacker = User::factory()->create();
        $victim   = User::factory()->create();
        $victimTrip = $this->makeTrip($victim);
        $victimItem = Itinerary::create([
            'trip_id' => $victimTrip->id, 'title' => 'Victim item',
            'type' => 'Activity', 'start_datetime' => now()->addDay()->toDateString() . ' 19:00:00',
        ]);

        Livewire::actingAs($attacker)
            ->test(ItineraryManager::class, ['tab' => 'itinerary'])
            ->set('selectedTripId', $victimTrip->id)
            ->call('deleteItem', $victimItem->id);

        $this->assertDatabaseHas('itinerary', ['id' => $victimItem->id]);
    }

    // Same tampering vector, against getInitialPinsProperty() (the trip
    // map's Moment pins) instead of the itinerary calendar.
    public function test_tampering_selected_trip_id_does_not_leak_another_users_moments(): void
    {
        $attacker = User::factory()->create();
        $victim   = User::factory()->create();
        $victimTrip = $this->makeTrip($victim);
        Moment::create([
            'trip_id' => $victimTrip->id, 'place_name' => 'Victim secret spot',
            'visited_date' => '2026-08-01', 'lat' => 1, 'lng' => 1,
        ]);

        $component = Livewire::actingAs($attacker)
            ->test(ItineraryManager::class, ['tab' => 'moments'])
            ->set('selectedTripId', $victimTrip->id)
            ->set('momentsMode', 'trip');

        $this->assertEmpty($component->get('initialPins'));
    }

    // ── Moments: destination overview map ───────────────────

    public function test_moments_tab_defaults_to_overview_mode(): void
    {
        $user = User::factory()->create();
        $this->makeTrip($user);

        Livewire::actingAs($user)
            ->test(ItineraryManager::class, ['tab' => 'moments'])
            ->assertSet('momentsMode', 'overview');
    }

    public function test_moments_deep_link_with_trip_id_skips_straight_to_trip_mode(): void
    {
        $user = User::factory()->create();
        $trip = $this->makeTrip($user);

        $response = $this->actingAs($user)->get("/moments?trip_id={$trip->id}");

        // "Ongoing" alone isn't a reliable absence check — it also appears
        // inside the always-present initOverviewMap JS source. Check the
        // overview map's own wire:key instead, which only renders in overview mode.
        $response->assertStatus(200)
            ->assertSee('All Destinations')                       // trip-mode back button present
            ->assertDontSee('moments-overview-map', false);        // overview map div absent
    }

    public function test_show_trip_on_map_selects_trip_and_switches_to_trip_mode(): void
    {
        $user  = User::factory()->create();
        $trip1 = $this->makeTrip($user);
        $trip2 = $this->makeTrip($user);

        Livewire::actingAs($user)
            ->test(ItineraryManager::class, ['tab' => 'moments'])
            ->assertSet('momentsMode', 'overview')
            ->call('showTripOnMap', $trip2->id)
            ->assertSet('momentsMode', 'trip')
            ->assertSet('selectedTripId', $trip2->id);
    }

    public function test_back_to_overview_resets_mode(): void
    {
        $user = User::factory()->create();
        $trip = $this->makeTrip($user);

        Livewire::actingAs($user)
            ->test(ItineraryManager::class, ['tab' => 'moments'])
            ->call('showTripOnMap', $trip->id)
            ->assertSet('momentsMode', 'trip')
            ->call('backToOverview')
            ->assertSet('momentsMode', 'overview');
    }

    public function test_overview_pins_includes_every_trip_with_correct_status(): void
    {
        $user = User::factory()->create();
        $past = Trip::factory()->create([
            'user_id' => $user->id,
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date'   => now()->subDays(5)->toDateString(),
            'destination' => 'Cebu City',
        ]);
        $ongoing = Trip::factory()->create([
            'user_id' => $user->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date'   => now()->addDay()->toDateString(),
            'destination' => 'Bohol',
        ]);
        $upcoming = Trip::factory()->create([
            'user_id' => $user->id,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date'   => now()->addDays(15)->toDateString(),
            'destination' => 'Boracay',
        ]);

        $component    = Livewire::actingAs($user)->test(ItineraryManager::class, ['tab' => 'moments']);
        $overviewPins = collect($component->get('overviewPins'))->keyBy('id');

        $this->assertSame('past', $overviewPins[$past->id]['status']);
        $this->assertSame('active', $overviewPins[$ongoing->id]['status']);
        $this->assertSame('upcoming', $overviewPins[$upcoming->id]['status']);
        $this->assertEqualsWithDelta(10.3157, $overviewPins[$past->id]['lat'], 0.0001); // Cebu City coords
    }

    public function test_overview_pin_status_honors_manual_override_from_saved_trips_edit(): void
    {
        // Dates alone would compute "upcoming", but the traveler manually
        // set this trip's status to "active" via Saved Trips > Edit Trip.
        // Moments must reflect that override, not the raw date computation.
        $user = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id'     => $user->id,
            'start_date'  => now()->addDays(10)->toDateString(),
            'end_date'    => now()->addDays(15)->toDateString(),
            'destination' => 'Boracay',
            'status'      => 'active',
        ]);

        $component    = Livewire::actingAs($user)->test(ItineraryManager::class, ['tab' => 'moments']);
        $overviewPins = collect($component->get('overviewPins'))->keyBy('id');

        $this->assertSame('active', $overviewPins[$trip->id]['status']);
    }

    public function test_itinerary_tab_still_shows_calendar_unaffected_by_moments_changes(): void
    {
        $user = User::factory()->create();
        $this->makeTrip($user);

        $this->actingAs($user)->get('/itinerary')
            ->assertStatus(200)
            ->assertDontSee('All Destinations');
    }

    // ── Moments: overview map memory markers ────────────────

    public function test_all_moment_pins_includes_moments_from_every_trip(): void
    {
        $user  = User::factory()->create();
        $trip1 = $this->makeTrip($user);
        $trip2 = $this->makeTrip($user);
        $m1 = Moment::create(['trip_id' => $trip1->id, 'place_name' => 'Spot A', 'visited_date' => '2026-08-01', 'lat' => 1, 'lng' => 1]);
        $m2 = Moment::create(['trip_id' => $trip2->id, 'place_name' => 'Spot B', 'visited_date' => '2026-08-02', 'lat' => 2, 'lng' => 2]);

        $component = Livewire::actingAs($user)->test(ItineraryManager::class, ['tab' => 'moments']);
        $allMoments = collect($component->get('allMomentPins'))->keyBy('id');

        $this->assertSame($trip1->id, $allMoments[$m1->id]['trip_id']);
        $this->assertSame($trip2->id, $allMoments[$m2->id]['trip_id']);
    }

    public function test_open_add_pin_modal_from_overview_resolves_nearest_trip(): void
    {
        $user    = User::factory()->create();
        $boracay = $this->makeTrip($user);
        $boracay->update(['destination' => 'Boracay']); // [11.9674, 121.9248]
        $tokyo = $this->makeTrip($user);
        $tokyo->update(['destination' => 'Tokyo']); // [35.6762, 139.6503]

        Livewire::actingAs($user)
            ->test(ItineraryManager::class, ['tab' => 'moments'])
            ->call('openAddPinModalFromOverview', 11.9, 121.9) // near Boracay, far from Tokyo
            ->assertSet('selectedTripId', $boracay->id)
            ->assertSet('showPinModal', true)
            ->assertSet('pinModalMode', 'add');
    }

    public function test_edit_pin_from_overview_focuses_the_moments_own_trip(): void
    {
        $user  = User::factory()->create();
        $trip1 = $this->makeTrip($user);
        $trip2 = $this->makeTrip($user);
        $moment = Moment::create([
            'trip_id' => $trip2->id, 'place_name' => 'Old Name',
            'visited_date' => '2026-08-01', 'lat' => 2, 'lng' => 2,
        ]);

        Livewire::actingAs($user)
            ->test(ItineraryManager::class, ['tab' => 'moments'])
            ->call('selectTrip', $trip1->id) // simulate selectedTripId pointing elsewhere
            ->call('openEditPinModalFromOverview', $moment->id)
            ->assertSet('selectedTripId', $trip2->id)
            ->assertSet('pinModalMode', 'edit')
            ->assertSet('pinPlaceName', 'Old Name');
    }

    public function test_confirm_delete_pin_from_overview_focuses_the_moments_own_trip(): void
    {
        $user  = User::factory()->create();
        $trip1 = $this->makeTrip($user);
        $trip2 = $this->makeTrip($user);
        $moment = Moment::create([
            'trip_id' => $trip2->id, 'place_name' => 'Spot',
            'visited_date' => '2026-08-01', 'lat' => 2, 'lng' => 2,
        ]);

        Livewire::actingAs($user)
            ->test(ItineraryManager::class, ['tab' => 'moments'])
            ->call('selectTrip', $trip1->id)
            ->call('confirmDeletePinFromOverview', $moment->id)
            ->assertSet('selectedTripId', $trip2->id)
            ->assertSet('pinToDelete', $moment->id);
    }

    public function test_edit_pin_from_overview_ignores_another_users_moment(): void
    {
        $owner  = User::factory()->create();
        $other  = User::factory()->create();
        $trip   = $this->makeTrip($owner);
        $moment = Moment::create([
            'trip_id' => $trip->id, 'place_name' => 'Secret',
            'visited_date' => '2026-08-01', 'lat' => 1, 'lng' => 1,
        ]);
        $otherTrip = $this->makeTrip($other);

        Livewire::actingAs($other)
            ->test(ItineraryManager::class, ['tab' => 'moments'])
            ->call('openEditPinModalFromOverview', $moment->id)
            ->assertSet('selectedTripId', $otherTrip->id) // unchanged from mount()
            ->assertSet('showPinModal', false);
    }

    // ── Moments: travel story timeline ──────────────────────

    public function test_timeline_moments_computes_correct_day_numbers(): void
    {
        $user = User::factory()->create();
        $trip = $this->makeTrip($user); // start_date = today, end_date = today+3

        $dayOne   = Moment::create(['trip_id' => $trip->id, 'place_name' => 'Arrival Spot', 'visited_date' => now()->toDateString(), 'lat' => 1, 'lng' => 1]);
        $dayThree = Moment::create(['trip_id' => $trip->id, 'place_name' => 'Beach', 'visited_date' => now()->addDays(2)->toDateString(), 'lat' => 2, 'lng' => 2]);

        $component = Livewire::actingAs($user)
            ->test(ItineraryManager::class, ['tab' => 'moments'])
            ->call('selectTrip', $trip->id);

        $timeline = collect($component->get('timelineMoments'))->keyBy('id');

        $this->assertSame(1, $timeline[$dayOne->id]['day_number']);
        $this->assertSame(3, $timeline[$dayThree->id]['day_number']);
    }

    public function test_timeline_moments_ordered_by_visited_date(): void
    {
        $user    = User::factory()->create();
        $trip    = $this->makeTrip($user);
        $later   = Moment::create(['trip_id' => $trip->id, 'place_name' => 'Later Spot', 'visited_date' => now()->addDays(2)->toDateString(), 'lat' => 1, 'lng' => 1]);
        $earlier = Moment::create(['trip_id' => $trip->id, 'place_name' => 'Earlier Spot', 'visited_date' => now()->toDateString(), 'lat' => 2, 'lng' => 2]);

        $component = Livewire::actingAs($user)
            ->test(ItineraryManager::class, ['tab' => 'moments'])
            ->call('selectTrip', $trip->id);

        $timeline = $component->get('timelineMoments');

        $this->assertSame('Earlier Spot', $timeline[0]['place_name']);
        $this->assertSame('Later Spot', $timeline[1]['place_name']);
    }

    public function test_timeline_moments_includes_posted_at_and_photo_urls_fields(): void
    {
        $user = User::factory()->create();
        $trip = $this->makeTrip($user);
        Moment::create(['trip_id' => $trip->id, 'place_name' => 'Spot', 'description' => 'Nice place', 'visited_date' => now()->toDateString(), 'lat' => 1, 'lng' => 1]);

        $component = Livewire::actingAs($user)
            ->test(ItineraryManager::class, ['tab' => 'moments'])
            ->call('selectTrip', $trip->id);

        $timeline = $component->get('timelineMoments');

        $this->assertArrayHasKey('posted_at', $timeline[0]);
        $this->assertArrayHasKey('photo_urls', $timeline[0]);
        $this->assertSame('Nice place', $timeline[0]['description']);
    }

    public function test_timeline_moments_empty_when_user_has_no_trips(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(ItineraryManager::class, ['tab' => 'moments']);

        $this->assertSame([], $component->get('timelineMoments'));
    }
}
