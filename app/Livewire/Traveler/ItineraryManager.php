<?php
namespace App\Livewire\Traveler;

use App\Models\Attraction;
use App\Models\Itinerary;
use App\Models\Moment;
use App\Models\MomentPhoto;
use App\Models\Trip;
use App\Services\MomentService;
use Livewire\Component;
use Livewire\WithFileUploads;

class ItineraryManager extends Component
{
    use WithFileUploads;

    protected MomentService $momentService;

    public ?int    $selectedTripId    = null;
    public ?string $selectedDate      = null;
    public bool    $showGenerateModal = false;
    public bool    $showDayModal      = false;
    public string  $tab               = 'itinerary';
    public string  $momentsMode       = 'overview'; // 'overview' | 'trip' — only meaningful when $tab === 'moments'

    // ── Moments: travel pins ────────────────────────────────
    public bool    $showPinModal      = false;
    public string  $pinModalMode      = 'add'; // 'add' | 'edit'
    public ?int    $editingPinId      = null;
    public ?float  $pinLat            = null;
    public ?float  $pinLng            = null;
    public string  $pinPlaceName      = '';
    public string  $pinDescription    = '';
    public string  $pinVisitedDate    = '';
    public array   $pinPhotos         = []; // Livewire temp uploads, newly selected
    public array   $pinExistingPhotos = []; // [['id'=>.., 'url'=>..], ...] — saved photos (edit mode)
    public ?int    $pinToDelete       = null;

    // Shown briefly (auto-clears client-side) when a pin add is blocked
    // because the target trip isn't Ongoing — see canPostMomentFor().
    public string  $momentBlockedMessage = '';

    // Destination name (lowercased) -> [lat, lng]. Used only for the Moments
    // map marker; not shared with SerpApiService's own coords table.
    private const DEST_COORDS = [
        'manila'          => [14.5995, 120.9842],
        'cebu'            => [10.3157, 123.8854],
        'cebu city'       => [10.3157, 123.8854],
        'davao'           => [7.1907,  125.4553],
        'davao city'      => [7.1907,  125.4553],
        'boracay'         => [11.9674, 121.9248],
        'malay'           => [11.9420, 121.9370],
        'bohol'           => [9.6272,  123.8788], // Tagbilaran City (Bohol's capital) — verified via Nominatim
        'palawan'         => [9.8349,  118.7384],
        'puerto princesa' => [9.7392,  118.7353],
        'el nido'         => [11.1784, 119.4074],
        'coron'           => [12.0031, 120.2040],
        'siargao'         => [9.8490,  126.0458],
        'bacolod'         => [10.6765, 122.9509],
        'iloilo'          => [10.7202, 122.5621],
        'iloilo city'     => [10.7202, 122.5621],
        'zamboanga'       => [6.9214,  122.0790],
        'cagayan de oro'  => [8.4542,  124.6319],
        'general santos'  => [6.1164,  125.1716],
        'tagaytay'        => [14.1153, 120.9621],
        'baguio'          => [16.4023, 120.5960],
        'vigan'           => [17.5747, 120.3869],
        'batangas'        => [13.7565, 121.0583],
        'tacloban'        => [11.2543, 124.9900],
        'dumaguete'       => [9.3076,  123.3080],
        'surigao'         => [9.7527,  125.4874],
        'camiguin'        => [9.1737,  124.7197],
        'siquijor'        => [9.2100,  123.5100],
        'batanes'         => [20.4487, 121.9700],
        'sagada'          => [17.0880, 120.9010],
        'pagudpud'        => [18.5600, 120.7900],
        'laoag'           => [18.1977, 120.5937],
        'puerto galera'   => [13.5021, 120.9539],
        'singapore'       => [1.3521,  103.8198],
        'bangkok'         => [13.7563, 100.5018],
        'phuket'          => [7.8804,  98.3923],
        'bali'            => [-8.3405, 115.0920],
        'kuala lumpur'    => [3.1390,  101.6869],
        'hong kong'       => [22.3193, 114.1694],
        'tokyo'           => [35.6762, 139.6503],
        'osaka'           => [34.6937, 135.5023],
        'seoul'           => [37.5665, 126.9780],
        'taipei'          => [25.0330, 121.5654],
        'dubai'           => [25.2048, 55.2708],
        'london'          => [51.5074, -0.1278],
        'paris'           => [48.8566, 2.3522],
        'new york'        => [40.7128, -74.0060],
        'sydney'          => [-33.8688, 151.2093],
        'rome'            => [41.9028, 12.4964],
        'barcelona'       => [41.3851, 2.1734],
        'amsterdam'       => [52.3676, 4.9041],
        'maldives'        => [3.2028,  73.2207],
    ];

    // Philippines centroid — fallback for destinations not in the table above.
    private const DEFAULT_LAT  = 12.8797;
    private const DEFAULT_LNG  = 121.7740;
    private const DEFAULT_ZOOM = 6;

    // Livewire calls boot() on every request (initial + subsequent AJAX
    // calls), unlike mount() which only runs once — this is the correct
    // place to receive injected services, not the constructor.
    public function boot(MomentService $momentService): void
    {
        $this->momentService = $momentService;
    }

    public function mount(): void
    {
        $trips = $this->getTripsProperty();
        if ($trips->isEmpty()) return;

        $queryTripId = request()->query('trip_id');
        if ($queryTripId && $trips->contains('id', (int) $queryTripId)) {
            $this->selectedTripId = (int) $queryTripId;
            $this->momentsMode    = 'trip'; // deep link: skip the overview, go straight to the trip
        } else {
            $this->selectedTripId = $trips->first()->id;
            // momentsMode stays at its default 'overview' — Moments lands on
            // the all-destinations map first; Itinerary ignores this property.
        }
    }


    public function updatedSelectedTripId(): void
    {
        $this->selectedDate      = null;
        $this->showGenerateModal = false;
        $this->showDayModal      = false;
        if ($this->selectedTripId) {
            $trip = $this->getSelectedTripProperty();
            if ($trip) {
                $this->dispatch('trip-selected',
                    start:  $trip->start_date->toDateString(),
                    end:    $trip->end_date->clone()->addDay()->toDateString(),
                    events: $this->getEventsProperty(),
                );
            }
        } else {
            $this->dispatch('trip-cleared');
        }
    }

    public function selectTrip(int $tripId): void
    {
        // Scoped to trips the traveller may open — their own plus any they
        // were added to — rather than owner-only, which locked a group member
        // out of the shared trip's itinerary and moments entirely.
        $trip = auth()->user()->accessibleTrips()->whereKey($tripId)->firstOrFail();
        $this->selectedTripId    = $trip->id;
        $this->selectedDate      = null;
        $this->showGenerateModal = false;
        $this->showDayModal      = false;
        $this->dispatch('trip-selected',
            start:  $trip->start_date->toDateString(),
            end:    $trip->end_date->clone()->addDay()->toDateString(),
            events: $this->getEventsProperty(),
        );
    }

    // ── Moments: overview map navigation ────────────────────
    public function showTripOnMap(int $tripId): void
    {
        $this->selectTrip($tripId);
        $this->momentsMode = 'trip';
    }

    public function backToOverview(): void
    {
        $this->momentsMode = 'overview';
    }

    // Clicking empty space on the overview map creates a Moment there. The
    // map spans every trip's destination at once, so the clicked point is
    // attributed to whichever trip's destination is geographically nearest
    // — the Add Pin modal then shows "For: <destination>" so that choice is
    // never a silent guess. Reuses the existing, unchanged openAddPinModal
    // (and therefore savePin/validation) once selectedTripId is set.
    public function openAddPinModalFromOverview(float $lat, float $lng): void
    {
        $nearestTripId = $this->resolveNearestTrip($lat, $lng);
        if (!$nearestTripId) return;

        $this->selectedTripId = $nearestTripId;
        $this->openAddPinModal($lat, $lng);
    }

    private function resolveNearestTrip(float $lat, float $lng): ?int
    {
        $nearestId   = null;
        $nearestDist = null;

        foreach ($this->trips as $trip) {
            $coords = $this->resolveDestinationCoords($trip->destination);
            $dist   = sqrt(($coords['lat'] - $lat) ** 2 + ($coords['lng'] - $lng) ** 2);

            if ($nearestDist === null || $dist < $nearestDist) {
                $nearestDist = $dist;
                $nearestId   = $trip->id;
            }
        }

        return $nearestId;
    }

    // Memory markers on the overview map span every trip, unlike
    // openEditPinModal/confirmDeletePin below which scope to
    // $this->selectedTrip. Align that scoping to the specific moment's own
    // trip first, then delegate to the existing, unchanged methods.
    private function focusTripForMoment(int $momentId): bool
    {
        $moment = Moment::find($momentId);
        if (!$moment) return false;

        $trip = auth()->user()->accessibleTrips()->whereKey($moment->trip_id)->first();
        if (!$trip) return false;

        $this->selectedTripId = $trip->id;
        return true;
    }

    public function openEditPinModalFromOverview(int $momentId): void
    {
        if (!$this->focusTripForMoment($momentId)) return;
        $this->openEditPinModal($momentId);
    }

    public function confirmDeletePinFromOverview(int $momentId): void
    {
        if (!$this->focusTripForMoment($momentId)) return;
        $this->confirmDeletePin($momentId);
    }

    public function goBack(): void
    {
        $this->selectedTripId  = null;
        $this->selectedDate    = null;
        $this->showGenerateModal = false;
        $this->showDayModal    = false;
    }

    public function selectDay(string $date): void
    {
        $this->selectedDate = $date;

        // selectedTripId is a public property, settable directly by the
        // client (not just through selectTrip()'s ownership check) — every
        // query below goes through the ownership-verified selectedTrip
        // accessor instead of trusting it raw, the same reasoning as
        // getEventsProperty()/getDayItemsProperty() further down.
        $trip = $this->selectedTrip;
        if (!$trip) return;

        $hasItems = Itinerary::where('trip_id', $trip->id)
            ->whereDate('start_datetime', $date)
            ->exists();

        if ($hasItems) {
            $this->showDayModal      = true;
            $this->showGenerateModal = false;
        } else {
            $this->showGenerateModal = true;
            $this->showDayModal      = false;
        }
    }

    public function closeModals(): void
    {
        $this->showGenerateModal = false;
        $this->showDayModal      = false;
    }

    public function deleteItem(int $itemId): void
    {
        // Same reasoning as selectDay() above — selectedTripId can be
        // tampered directly, so re-verify ownership via selectedTrip
        // before this query is allowed to touch anything.
        $trip = $this->selectedTrip;
        if (!$trip) return;

        Itinerary::where('id', $itemId)
            ->where('trip_id', $trip->id)
            ->delete();

        // If no items left, close day modal
        if (!Itinerary::where('trip_id', $trip->id)
                ->whereDate('start_datetime', $this->selectedDate)
                ->exists()) {
            $this->showDayModal = false;
        }

        $this->dispatch('trip-changed',
            start:  $trip->start_date->toDateString(),
            end:    $trip->end_date->clone()->addDay()->toDateString(),
            events: $this->getEventsProperty(),
        );
    }

    public function generateItinerary(): void
    {
        $trip = $this->selectedTrip;
        if (!$trip || !$this->selectedDate) return;

        $dateStr = $this->selectedDate;
        $dest    = $trip->destination;

        Itinerary::where('trip_id', $trip->id)
            ->whereDate('start_datetime', $dateStr)
            ->delete();

        $attractions = Attraction::where('destination', 'LIKE', '%' . $dest . '%')
            ->orWhere('name', 'LIKE', '%' . $dest . '%')
            ->orderBy('rating', 'desc')
            ->get();

        $isFirst = $dateStr === $trip->start_date->toDateString();
        $isLast  = $dateStr === $trip->end_date->toDateString();
        $attrIdx = 0;

        $dinners    = ['Dinner at local restaurant', 'Seafood dinner', 'Traditional dinner'];

        if ($isFirst) {
            $this->createItem($trip->id, $dateStr, '16:00', 'Arrival in ' . $dest, 'Flight', 'Welcome to ' . $dest . '!');
            $this->createItem($trip->id, $dateStr, '17:00', 'Hotel Check-in', 'Hotel', 'Settle in and rest up.');
            $this->createItem($trip->id, $dateStr, '19:00', $dinners[0], 'Activity', null);
        } elseif ($isLast) {
            $this->createItem($trip->id, $dateStr, '07:00', 'Buffet breakfast', 'Activity', null);
            $this->createItem($trip->id, $dateStr, '09:00', 'Hotel Check-out', 'Hotel', 'Pack up and check out.');
            $this->createItem($trip->id, $dateStr, '12:00', 'Departure from ' . $dest, 'Flight', 'Safe travels home!');
        } else {
            $this->createItem($trip->id, $dateStr, '07:30', 'Breakfast at hotel in ' . $dest, 'Activity', null);
            $a1 = $this->nextAttraction($attractions, $attrIdx++, $dest);
            $this->createItem($trip->id, $dateStr, '10:00', $a1['title'], 'Activity', $a1['note']);
            $this->createItem($trip->id, $dateStr, '12:30', 'Lunch at local restaurant', 'Activity', null);
            $a2 = $this->nextAttraction($attractions, $attrIdx++, $dest);
            $this->createItem($trip->id, $dateStr, '14:00', $a2['title'], 'Activity', $a2['note']);
            $a3 = $this->nextAttraction($attractions, $attrIdx++, $dest);
            $this->createItem($trip->id, $dateStr, '16:30', $a3['title'], 'Activity', $a3['note']);
            $this->createItem($trip->id, $dateStr, '19:00', $dinners[0], 'Activity', null);
        }

        $this->showGenerateModal = false;
        $this->showDayModal      = false;

        $this->dispatch('trip-changed',
            start:  $trip->start_date->toDateString(),
            end:    $trip->end_date->clone()->addDay()->toDateString(),
            events: $this->getEventsProperty(),
        );
    }

    private function createItem(int $tripId, string $date, string $time, string $title, string $type, ?string $notes): void
    {
        Itinerary::create([
            'trip_id'        => $tripId,
            'title'          => $title,
            'type'           => $type,
            'start_datetime' => $date . ' ' . $time . ':00',
            'notes'          => $notes,
        ]);
    }

    private function nextAttraction($attractions, int $idx, string $dest): array
    {
        if ($attractions->isEmpty()) {
            return ['title' => 'Sightseeing in ' . $dest, 'note' => null];
        }
        $attr = $attractions->get($idx % $attractions->count());
        return [
            'title' => 'Visit ' . $attr->name,
            'note'  => $attr->description ? \Str::limit($attr->description, 80) : null,
        ];
    }

    public function getTripsProperty()
    {
        return auth()->user()->accessibleTrips()->orderByDesc('start_date')->get()
            ->filter(fn (Trip $t) => in_array($t->resolved_status, ['active', 'upcoming', 'past'], true))
            ->values();
    }

    // Only an Ongoing trip can have Moments posted — logging a "memory"
    // for a trip that hasn't started yet, or one that's already over,
    // doesn't make sense. resolved_status already resolves to exactly
    // 'active' for an ongoing trip (or a manually-set status override),
    // matching what the map's own legend calls "Ongoing".
    private function canPostMomentFor(Trip $trip): bool
    {
        return $trip->resolved_status === 'active';
    }

    // Whether ANY of the traveler's trips is currently Ongoing — used to
    // adapt the overview map's "Click anywhere to post a Moment" hint so
    // it doesn't invite an action that's about to be blocked anyway.
    public function getHasOngoingTripProperty(): bool
    {
        return $this->trips->contains(fn (Trip $t) => $t->resolved_status === 'active');
    }

    public function getSelectedTripProperty(): ?Trip
    {
        if (!$this->selectedTripId) return null;
        return auth()->user()->accessibleTrips()
                   ->whereKey($this->selectedTripId)
                   ->first();
    }

    public function getEventsProperty(): array
    {
        // selectedTripId is a public property a client can set directly
        // (not only via selectTrip()'s ownership check), and this accessor
        // is evaluated on every render — reading through selectedTrip
        // instead re-verifies ownership every time, closing that off.
        $trip = $this->selectedTrip;
        if (!$trip) return [];

        $colorMap = [
            'Flight'         => ['bg' => '#EFF6FF', 'border' => '#1D4ED8', 'text' => '#1D4ED8', 'icon' => 'plane'],
            'Hotel'          => ['bg' => '#F0FDF4', 'border' => '#16A34A', 'text' => '#16A34A', 'icon' => 'bed'],
            'Transportation' => ['bg' => '#FFF7ED', 'border' => '#D97706', 'text' => '#D97706', 'icon' => 'car'],
            'Activity'       => ['bg' => '#F5EDE7', 'border' => '#8B3A10', 'text' => '#8B3A10', 'icon' => 'camera'],
        ];

        return Itinerary::where('trip_id', $trip->id)
            ->orderBy('start_datetime')
            ->get()
            ->map(function ($i) use ($colorMap) {
                $c = $colorMap[$i->type] ?? $colorMap['Activity'];
                return [
                    'title'           => $i->title,
                    'start'           => $i->start_datetime->format('Y-m-d\TH:i:s'),
                    'backgroundColor' => $c['bg'],
                    'borderColor'     => $c['border'],
                    'textColor'       => $c['text'],
                    'extendedProps'   => ['icon' => $c['icon'], 'type' => $i->type],
                    'display'         => 'block',
                ];
            })
            ->toArray();
    }

    public function getDayItemsProperty(): \Illuminate\Database\Eloquent\Collection
    {
        // Same reasoning as getEventsProperty() above — this is what the
        // day-detail modal renders directly, so it's the exact accessor a
        // tampered selectedTripId would otherwise use to read another
        // traveler's itinerary items.
        $trip = $this->selectedTrip;
        // collect() returns Support\Collection, not the Eloquent\Collection
        // this method declares — pre-existing mismatch that PHP's return
        // type enforcement never actually caught before, since this path
        // was rarely reached with a legitimately empty selection.
        if (!$trip || !$this->selectedDate) return new \Illuminate\Database\Eloquent\Collection();
        return Itinerary::where('trip_id', $trip->id)
            ->whereDate('start_datetime', $this->selectedDate)
            ->orderBy('start_datetime')
            ->get();
    }

    private function resolveDestinationCoords(string $destination): array
    {
        $key   = strtolower(trim($destination));
        $found = self::DEST_COORDS[$key] ?? null;

        return [
            'lat'  => $found[0] ?? self::DEFAULT_LAT,
            'lng'  => $found[1] ?? self::DEFAULT_LNG,
            'zoom' => $found ? 12 : self::DEFAULT_ZOOM,
        ];
    }

    public function getMapCenterProperty(): array
    {
        $trip = $this->selectedTrip;
        if (!$trip) {
            return ['lat' => self::DEFAULT_LAT, 'lng' => self::DEFAULT_LNG, 'zoom' => self::DEFAULT_ZOOM, 'label' => ''];
        }

        return [...$this->resolveDestinationCoords($trip->destination), 'label' => $trip->destination];
    }

    public function getOverviewPinsProperty(): array
    {
        return $this->trips->map(function (Trip $trip) {
            $coords = $this->resolveDestinationCoords($trip->destination);
            return [
                'id'          => $trip->id,
                'lat'         => $coords['lat'],
                'lng'         => $coords['lng'],
                'destination' => $trip->destination,
                'start_date'  => $trip->start_date->format('M j, Y'),
                'end_date'    => $trip->end_date->format('M j, Y'),
                'status'      => $trip->resolved_status,
            ];
        })->values()->toArray();
    }

    // Every Moment across every trip, for the overview map's separate
    // "memory marker" layer — distinct from the trip-status pins above.
    public function getAllMomentPinsProperty(): array
    {
        return Moment::whereIn('trip_id', $this->trips->pluck('id'))
            ->with('photos')
            ->get()
            ->map(fn (Moment $m) => $this->momentService->pinToArray($m))
            ->values()
            ->toArray();
    }

    // Same moments as getAllMomentPinsProperty, enriched for the overview
    // page's Timeline View — each one's day number within its OWN trip and
    // that trip's destination (for grouping), since "Day N" only makes
    // sense per-trip when the timeline spans every trip at once.
    public function getAllMomentsTimelineProperty(): array
    {
        $tripsById = $this->trips->keyBy('id');

        $moments = Moment::whereIn('trip_id', $tripsById->keys())
            ->with('photos')
            ->orderBy('visited_date')
            ->orderBy('created_at')
            ->get();

        // Numbered per trip — "Day N" only means anything within one trip.
        $dayNumbers = $moments->groupBy('trip_id')
            ->map(fn ($tripMoments) => $this->dayNumbersFor($tripMoments));

        return $moments
            ->map(function (Moment $m) use ($tripsById, $dayNumbers) {
                $pin  = $this->momentService->pinToArray($m);
                $trip = $tripsById->get($m->trip_id);

                if ($trip) {
                    $pin['day_number']        = $dayNumbers[$m->trip_id][$m->visited_date->toDateString()] ?? 1;
                    $pin['trip_destination']  = $trip->destination;
                } else {
                    $pin['day_number']       = 1;
                    $pin['trip_destination'] = 'Unknown trip';
                }

                $pin['posted_at'] = local_time($m->created_at)->format('M j, Y g:i A');
                return $pin;
            })
            ->values()
            ->toArray();
    }

    /**
     * Maps each distinct visited_date in a trip's moments to a day number,
     * starting at 1 for the earliest.
     *
     * Day numbers used to be measured off the trip's start_date, which broke
     * whenever a moment's date sat outside the planned window — the trip that
     * starts 2026-08-17 with a moment logged for 2026-08-08 rendered a
     * "Day -8" badge, and one logged the day before its trip began rendered
     * "Day 0". Counting the trip's own recorded days instead keeps the first
     * moment at Day 1 and can never produce a zero or negative label.
     *
     * @param  \Illuminate\Support\Collection<int, Moment>  $moments
     * @return array<string, int>  'Y-m-d' => day number
     */
    private function dayNumbersFor($moments): array
    {
        return $moments
            ->pluck('visited_date')
            ->map(fn ($d) => $d->toDateString())
            ->unique()
            ->sort()
            ->values()
            ->flip()
            ->map(fn ($i) => $i + 1)
            ->all();
    }

    public function getInitialPinsProperty(): array
    {
        // Same reasoning as getEventsProperty()/getDayItemsProperty() above
        // — this feeds the trip map's pins (place names, descriptions,
        // photos), so it's exactly what a tampered selectedTripId would
        // otherwise expose from another traveler's trip.
        $trip = $this->selectedTrip;
        if (!$trip) return [];
        return Moment::where('trip_id', $trip->id)
            ->orderBy('visited_date')
            ->get()
            ->map(fn (Moment $m) => $this->momentService->pinToArray($m))
            ->values()
            ->toArray();
    }

    // Same moments as getInitialPinsProperty, enriched with each one's day
    // number within the trip (Day 1, Day 2, ...) and when it was actually
    // posted — powers the Timeline view without duplicating any stored data.
    public function getTimelineMomentsProperty(): array
    {
        if (!$this->selectedTripId || !$this->selectedTrip) return [];

        $moments = Moment::where('trip_id', $this->selectedTripId)
            ->with('photos')
            ->orderBy('visited_date')
            ->orderBy('created_at')
            ->get();

        $dayNumbers = $this->dayNumbersFor($moments);

        return $moments
            ->map(function (Moment $m) use ($dayNumbers) {
                $pin = $this->momentService->pinToArray($m);
                $pin['day_number'] = $dayNumbers[$m->visited_date->toDateString()] ?? 1;
                $pin['posted_at']  = local_time($m->created_at)->format('M j, Y g:i A');
                return $pin;
            })
            ->values()
            ->toArray();
    }

    // ── Moments: travel pins ────────────────────────────────
    public function openAddPinModal(float $lat, float $lng): void
    {
        $trip = $this->selectedTrip;
        if (!$trip) return;

        if (!$this->canPostMomentFor($trip)) {
            $this->momentBlockedMessage = 'You can only post moments for ongoing trips.';
            return;
        }

        $this->momentBlockedMessage = '';
        $this->pinModalMode      = 'add';
        $this->editingPinId      = null;
        $this->pinLat             = $lat;
        $this->pinLng             = $lng;
        $this->pinPlaceName      = '';
        $this->pinDescription    = '';
        // Always today. This used to clamp to the trip's nearest bound when
        // today fell outside the trip's dates, which meant a trip that had
        // already ended opened the picker on its end_date — a date the
        // calendar now blocks as past, so the field would have defaulted to
        // a value the traveler couldn't re-select.
        $this->pinVisitedDate = now()->toDateString();
        $this->pinPhotos         = [];
        $this->pinExistingPhotos = [];
        $this->showPinModal       = true;
    }

    public function openEditPinModal(int $pinId): void
    {
        $trip = $this->selectedTrip;
        if (!$trip) return;
        $moment = Moment::with('photos')->where('id', $pinId)->where('trip_id', $trip->id)->first();
        if (!$moment) return;

        $this->pinModalMode      = 'edit';
        $this->editingPinId      = $moment->id;
        $this->pinLat             = (float) $moment->lat;
        $this->pinLng             = (float) $moment->lng;
        $this->pinPlaceName      = $moment->place_name;
        $this->pinDescription    = $moment->description ?? '';
        $this->pinVisitedDate    = $moment->visited_date->toDateString();
        $this->pinPhotos         = [];
        $this->pinExistingPhotos = $this->momentService->existingPhotosArray($moment);
        $this->showPinModal       = true;
    }

    public function closePinModal(): void
    {
        $this->showPinModal = false;
    }

    public function removeNewPhoto(int $index): void
    {
        unset($this->pinPhotos[$index]);
        $this->pinPhotos = array_values($this->pinPhotos);
    }

    public function removeExistingPhoto(int $photoId): void
    {
        // editingPinId is a public property a client can set directly (same
        // reasoning as selectedTripId elsewhere in this file) — re-verify
        // the moment belongs to the current, ownership-checked trip before
        // trusting it, so a tampered editingPinId can't
        // be used to delete another traveler's photo.
        $trip = $this->selectedTrip;
        if (!$trip || !$this->editingPinId) return;

        $moment = Moment::where('id', $this->editingPinId)->where('trip_id', $trip->id)->first();
        if (!$moment) return;

        $photo = MomentPhoto::where('id', $photoId)->where('moment_id', $moment->id)->first();
        if (!$photo) return;

        $this->momentService->deletePhoto($photo);

        $this->pinExistingPhotos = array_values(array_filter(
            $this->pinExistingPhotos,
            fn ($p) => $p['id'] !== $photoId
        ));
    }

    public function savePin(): void
    {
        $trip = $this->selectedTrip;
        abort_if(!$trip, 403);
        // Re-checked here, not just in openAddPinModal() — that only
        // guards whether the modal opens; a request straight to this
        // action must be blocked the same way regardless of how it got
        // triggered, the same "never trust client-side only" reasoning
        // applied elsewhere in this app.
        abort_if(!$this->canPostMomentFor($trip), 403);

        $validated = $this->validate([
            'pinPlaceName'   => 'required|string|max:255',
            'pinDescription' => 'nullable|string|max:2000',
            'pinVisitedDate' => 'required|date',
            'pinPhotos'      => 'nullable|array|max:6',
            'pinPhotos.*'    => 'image|max:5120',
            'pinLat'         => 'required|numeric',
            'pinLng'         => 'required|numeric',
        ]);

        $data = [
            'place_name'   => $validated['pinPlaceName'],
            'description'  => $validated['pinDescription'] ?: null,
            'visited_date' => $validated['pinVisitedDate'],
            'lat'          => $validated['pinLat'],
            'lng'          => $validated['pinLng'],
        ];

        if ($this->pinModalMode === 'edit' && $this->editingPinId) {
            $moment = Moment::where('id', $this->editingPinId)->where('trip_id', $trip->id)->first();
            abort_if(!$moment, 403);
            $moment = $this->momentService->updatePin($moment, $data, $this->pinPhotos);
        } else {
            $moment = $this->momentService->createPin($trip, $data, $this->pinPhotos);
        }

        $this->showPinModal = false;
        $this->dispatch('pin-saved', pin: $this->momentService->pinToArray($moment));
    }

    public function confirmDeletePin(int $pinId): void
    {
        $this->pinToDelete   = $pinId;
        $this->showPinModal  = false;
    }

    public function cancelDeletePin(): void
    {
        $this->pinToDelete = null;
    }

    public function deletePin(): void
    {
        $trip = $this->selectedTrip;
        if (!$trip || !$this->pinToDelete) return;

        $moment = Moment::with('photos')->where('id', $this->pinToDelete)->where('trip_id', $trip->id)->first();
        if ($moment) {
            $deletedId = $moment->id;
            $this->momentService->deleteMoment($moment);
            $this->dispatch('pin-deleted', id: $deletedId);
        }

        $this->pinToDelete = null;
    }

    public function render()
    {
        return view('livewire.traveler.itinerary-manager');
    }
}
