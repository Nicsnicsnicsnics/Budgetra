<?php
namespace App\Livewire\Traveler;

use App\Models\AiConversationDraft;
use App\Models\Destination;
use App\Models\GroupMember;
use App\Models\Notification;
use App\Models\SavingsGoal;
use App\Models\Trip;
use App\Models\TripBudget;
use App\Models\User;
use App\Services\CurrencyConverterService;
use App\Services\SerpApiService;
use App\Services\SerperService;
use App\Services\TripImportService;
use App\Support\PlaceCatalog;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.app', ['active' => 'trips'])]
class TripPlannerWizard extends Component
{
    // ── Trips list view (shown when user already has trips) ─
    public bool   $showList      = false;
    public bool   $showEmpty     = false;
    public ?int   $tripToDelete  = null;

    // ── Navigation ─────────────────────────────────────────
    public int    $step     = 0;
    public string $planningMode = ''; // 'manual' | 'ai'

    // ── "Have a trip code?" — an optional inline field under the Manual/AI
    // cards on the mode-select screen. It used to be a mandatory step wedged
    // between Manual Planning and Trip Details, so every manual planner had
    // to dismiss a screen most of them had no code for. Imports and redirects
    // away on success, so it never has to hand control back to the wizard.
    public string $importCodeInput   = '';
    public string $importCodeError   = '';

    // Sets the message and tells the browser to shake the field. Alpine also
    // handles the empty-input case on its own without a round trip; this
    // covers every rejection that can only be decided server-side.
    private function reject(string $message): void
    {
        $this->importCodeError = $message;
        $this->dispatch('code-rejected');
    }

    public function importCode(): void
    {
        $this->importCodeError = '';
        $code = trim($this->importCodeInput);
        if ($code === '') {
            $this->reject('Enter a share code.');
            return;
        }

        $importer   = app(TripImportService::class);
        $sourceTrip = $importer->findByCode($code);

        if (!$sourceTrip) {
            $this->reject('No trip found with that code.');
            return;
        }
        if ($sourceTrip->user_id === auth()->id()) {
            // Used to bail silently. Now that every other rejection shakes the
            // field, silence here would read as a dead button rather than a
            // refusal — and it's the traveler's own code, so saying so leaks
            // nothing.
            $this->reject("That's your own trip's code.");
            return;
        }
        if (!$importer->isShareable($sourceTrip)) {
            $this->reject('This trip has nothing shareable saved on it.');
            return;
        }

        $importer->import($sourceTrip, auth()->user());

        // If an earlier pass through Trip Details already autosaved a draft
        // for this session, the traveler is abandoning it in favor of the
        // imported trip — remove it so it doesn't linger in Draft Trips.
        if ($this->draftTripId) {
            Trip::where('id', $this->draftTripId)
                ->where('user_id', auth()->id())
                ->where('status', 'draft')
                ->delete();
            $this->draftTripId = null;
        }

        session()->flash('success', 'Trip imported!');
        $this->redirect(route('saved-trips'), navigate: true);
    }

    // ── Step 1: trip details form (new) ───────────────────
    // Bumped every time we land back on step 1 from a later step, so the
    // Alpine card's wire:key always changes and Livewire is forced to fully
    // remount it — otherwise morphdom can end up reusing the previous DOM
    // node and Alpine's x-init never re-runs, leaving From/To/dates blank
    // even though the underlying manualFrom/manualTo/startDate/endDate
    // properties on the server were never actually cleared.
    public int $step1VisitToken    = 0;
    public string $manualFrom      = '';
    public string $manualTo        = '';
    public string $manualBudgetMin = '';
    public string $manualBudgetMax = '';
    public string $travelWith      = ''; // 'solo' | 'group'


    // ── Step 2: flight selection ───────────────────────────
    public array  $flightResults     = [];
    public bool   $flightLoading     = false;
    public string $flightError       = '';
    public string $flightTripType    = 'one_way'; // 'one_way' | 'round_trip' | 'multi_city'
    public ?array $selectedFlight    = null;
    // Index of a flight card awaiting a "Confirm this flight?" click before
    // selectFlight() actually runs — guards against an accidental misclick
    // on "Select" silently committing a flight and advancing the wizard.
    public ?int   $confirmFlightIndex = null;
    // multi-city leg 2 (synced on search)
    public string $mcTo              = '';
    public string $mcStartDate       = '';
    public string $mcEndDate         = '';
    public array  $mcFlightResults   = [];
    public bool   $mcFlightLoading   = false;
    public bool   $mcFlightStep      = false; // true = showing leg 2 flight list
    public bool   $mcSearched        = false; // true after multi-city search fires
    public ?array $selectedMcFlight  = null;
    public ?int   $confirmMcFlightIndex = null;

    // ── Step 3: accommodation ──────────────────────────────
    public array  $hotelResults      = [];
    public bool   $hotelLoading      = false;
    public ?array $selectedHotel     = null;
    public int    $hotelGuests       = 1;
    public string $hotelType         = 'hotel'; // hotel | apartment | inn | resort
    public string $hotelError        = '';
    // multi-city leg 2 accommodation
    public bool   $mcHotelStep       = false;
    public array  $mcHotelResults    = [];
    public bool   $mcHotelLoading    = false;
    public ?array $selectedMcHotel   = null;

    // ── Step 4: food & dining ─────────────────────────────
    public array  $venueResults     = [];
    public bool   $venueLoading     = false;
    public array  $selectedVenues   = [];   // ['Venue Name' => $venueArray, ...] — multi-select
    public array  $selectedMcVenues = [];
    public string $venueCategory    = 'All Cuisines';
    public string $venueError       = '';
    public bool   $mcVenueStep      = false;
    public array  $mcVenueResults   = [];
    public bool   $mcVenueLoading   = false;

    // ── Step 5: attractions ────────────────────────────────
    public array  $attractionResults      = [];
    public bool   $attractionLoading      = false;
    public array  $selectedAttractions    = [];  // ['Attraction Name' => $attrArray, ...] — multi-select
    public array  $selectedMcAttractions  = [];
    public string $attractionType         = 'All Attractions';
    public string $attractionError        = '';
    public bool   $mcAttractionStep       = false;
    public array  $mcAttractionResults    = [];
    public bool   $mcAttractionLoading    = false;

    // ── Result search (accommodation / dining / attractions) ───────────
    // Narrows what's already on screen rather than firing a fresh provider
    // query: SerpAPI is capped at 80 calls/day shared across the whole app
    // (see SerpApiService), and this step's round trip already happened when
    // it opened. "Applied" is only synced on the Search click, so typing
    // doesn't churn the list underneath the traveler.
    public string $hotelSearch             = '';
    public string $hotelSearchApplied      = '';
    public string $venueSearch             = '';
    public string $venueSearchApplied      = '';
    public string $attractionSearch        = '';
    public string $attractionSearchApplied = '';

    public function searchHotelResults(): void      { $this->hotelSearchApplied      = trim($this->hotelSearch); }
    public function searchVenueResults(): void      { $this->venueSearchApplied      = trim($this->venueSearch); }
    public function searchAttractionResults(): void { $this->attractionSearchApplied = trim($this->attractionSearch); }

    public function clearHotelSearch(): void      { $this->hotelSearch      = ''; $this->hotelSearchApplied      = ''; }
    public function clearVenueSearch(): void      { $this->venueSearch      = ''; $this->venueSearchApplied      = ''; }
    public function clearAttractionSearch(): void { $this->attractionSearch = ''; $this->attractionSearchApplied = ''; }

    /**
     * Narrow a result set by free text across whichever descriptive fields a
     * row happens to carry (providers aren't consistent about which are set).
     *
     * Keys are deliberately preserved: selectAccommodation()/toggleVenue()/
     * toggleAttraction() all index back into the ORIGINAL unfiltered array,
     * so reindexing here would silently select the wrong row.
     */
    public function filterResults(array $rows, string $needle): array
    {
        $needle = trim(mb_strtolower($needle));
        if ($needle === '') return $rows;

        return array_filter($rows, function ($row) use ($needle) {
            foreach (['name', 'address', 'location', 'city', 'category', 'type',
                      'cuisine', 'detail', 'description', 'price'] as $key) {
                $value = $row[$key] ?? null;
                if (is_string($value) && $value !== ''
                    && str_contains(mb_strtolower($value), $needle)) {
                    return true;
                }
            }
            return false;
        });
    }

    public function selectedVenuesFlat(): array
    {
        return array_merge(array_values($this->selectedVenues), array_values($this->selectedMcVenues));
    }

    public function selectedAttractionsFlat(): array
    {
        return array_merge(array_values($this->selectedAttractions), array_values($this->selectedMcAttractions));
    }

    public function selectedVenuesCost(): int
    {
        $total = 0;
        foreach ($this->selectedVenuesFlat() as $v) {
            $total += (int) ($v['priceMax'] ?? $v['priceMin'] ?? 0);
        }
        return $total;
    }

    public function selectedAttractionsCost(): int
    {
        $total = 0;
        foreach ($this->selectedAttractionsFlat() as $a) {
            if (!($a['isFree'] ?? false)) {
                $total += (int) preg_replace('/[^\d]/', '', $a['price'] ?? '0');
            }
        }
        return $total;
    }

    // Groups every selected attraction/venue into per-day buckets — day-offset
    // 0 is the first day after arrival, 1 the next, and so on. Shared by the
    // itinerary preview (step 8) and the real save, so both always agree on
    // which day a given selection lands on.
    public function selectionDayBuckets(): array
    {
        $buckets = [];
        foreach (array_chunk($this->selectedAttractionsFlat(), 3) as $i => $chunk) {
            $buckets[$i]['attractions'] = $chunk;
        }
        foreach (array_chunk($this->selectedVenuesFlat(), 2) as $i => $chunk) {
            $buckets[$i]['venues'] = $chunk;
        }
        ksort($buckets);
        return $buckets;
    }

    // The AI should only be asked to fill however many days remain after
    // arrival + the traveler's own selections — not the whole trip span —
    // otherwise its content gets appended past the trip's actual end date.
    private function aiEndDate(): string
    {
        $start = $this->startDate ?: now()->toDateString();
        $end   = $this->endDate   ?: now()->toDateString();

        $totalDays    = max(1, (int) Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1);
        $consumedDays = 1 + count($this->selectionDayBuckets()); // arrival + explore days
        $remainingDays = max(1, $totalDays - $consumedDays);

        return Carbon::parse($start)->addDays($remainingDays - 1)->toDateString();
    }

    // ── Cross-cutting: submission guard ────────────────────
    // Prevents a fast double-click on Save/Confirm from creating duplicate
    // Trip rows — Livewire's wire:loading.attr="disabled" is client-side
    // only and can be bypassed (double network request race, JS disabled).
    public bool   $isSaving = false;

    // Tracks the Trip row created by autosaveDraft() (Step 1's "From/To/
    // Budget/Dates" form autosaves in the background instead of requiring
    // an explicit "Save Draft" click) so repeated autosaves update the
    // same row instead of creating a new draft every time.
    public ?int   $draftTripId = null;

    // Step 8 — AI-generated itinerary
    public ?array $aiItinerary          = null; // the currently selected option (what saveItinerary() reads)
    public array  $aiItineraryOptions   = [];   // 2-3 generated alternatives to choose between
    public int    $selectedItineraryIndex = 0;
    public bool   $aiLoading        = false;
    // Multi-city: itinerary suggestions are generated one leg at a time —
    // leg 1 first, then leg 2 after the traveler continues past leg 1's
    // options. $itineraryLeg tracks which leg is currently being shown;
    // leg 1's chosen option is stashed here once leg 2 generation starts.
    public int    $itineraryLeg         = 1;
    public ?array $aiItineraryLeg1      = null;
    public array  $aiItineraryLeg1Options = [];
    public bool   $showBudgetAdjust = false;
    public string $adjustBudgetMin  = '';
    public string $adjustBudgetMax  = '';

    // A little variety per generated option so the choices actually read
    // differently instead of being near-duplicates of each other. Each
    // entry also carries a short label shown in the option list.
    // Trimmed from 5 to 3 — each option can fall through up to 3 AI
    // providers (see suggestItinerary()), so fewer options means a lower
    // worst-case wait while still giving a real comparison grid.
    private const ITINERARY_OPTION_CONSTRAINTS = [
        ['label' => 'Balanced Mix',     'prompt' => 'Mix cultural, culinary, and outdoor experiences for a well-rounded trip.'],
        ['label' => 'Adventure Focus',  'prompt' => 'Prioritize outdoor adventure, active excursions, and thrill activities.'],
        ['label' => 'Budget Friendly',  'prompt' => 'Prioritize affordable, high-value activities and local eats to stretch the budget further.'],
    ];

    // Step 8 — traveler-added custom activities
    public array  $customActivities        = []; // [['day'=>int,'title'=>,'time'=>,'cost'=>float,'type'=>,'description'=>], ...]
    public bool   $showCustomActivityModal = false;
    public string $customActivityDay         = '1';
    public string $customActivityTitle       = '';
    public string $customActivityTime        = '09:00';
    public string $customActivityCost        = '';
    public string $customActivityType        = 'Activity';
    public string $customActivityDescription = '';

    public function openCustomActivityModal(): void
    {
        $this->customActivityDay         = '1';
        $this->customActivityTitle       = '';
        $this->customActivityTime        = '09:00';
        $this->customActivityCost        = '';
        $this->customActivityType        = 'Activity';
        $this->customActivityDescription = '';
        $this->showCustomActivityModal   = true;
    }

    public function closeCustomActivityModal(): void
    {
        $this->showCustomActivityModal = false;
    }

    public function addCustomActivity(): void
    {
        if (trim($this->customActivityTitle) === '') return;

        $this->customActivities[] = [
            'day'         => max(1, (int) $this->customActivityDay),
            'title'       => trim($this->customActivityTitle),
            'time'        => $this->customActivityTime ?: '09:00',
            'cost'        => (float) preg_replace('/[^\d.]/', '', $this->customActivityCost ?: '0'),
            'type'        => $this->customActivityType ?: 'Activity',
            'description' => trim($this->customActivityDescription),
        ];

        $this->showCustomActivityModal = false;
    }

    public function removeCustomActivity(int $index): void
    {
        unset($this->customActivities[$index]);
        $this->customActivities = array_values($this->customActivities);
    }

    // ── Step 3 (was 2): scope ─────────────────────────────
    public string $tripScope = '';

    // ── Step 3: destination ────────────────────────────────
    public ?int   $destinationId   = null;
    public string $destinationName = '';
    public string $destSearch      = '';

    // ── Step 4: calendar ───────────────────────────────────
    public string $startDate  = '';
    public string $endDate    = '';
    public int    $calYear;
    public int    $calMonth;

    // ── Step 4: group + budget ─────────────────────────────
    public string $groupType  = '';
    public int    $travelers  = 1;
    public string $budgetTier = '';

    // ── Step 5: editable cost categories ──────────────────
    public float  $transportation = 0;
    public float  $accommodation  = 0;
    public float  $food           = 0;
    public float  $attractions    = 0;
    public float  $shopping       = 0;
    public float  $emergency      = 0;
    public string $emergencyError = '';
    public float  $budgetLimit    = 0;
    public string $tripCurrency        = '';
    public bool   $emergencyConverted  = false;
    public bool    $showCurrencyConvertModal = false;
    public ?string $destinationCurrencyCode  = null;
    public ?float  $convertedBudget          = null;
    public string  $currencyConvertError     = '';
    public string $editingCategory = '';

    // ── Cost rate tables (₱ per trip) ─────────────────────
    private const RATES = [
        'local' => [
            'Shoestring' => ['transport_base' => 2000,   'transport_daily' => 100,  'accommodation_night' => 800,   'food_day' => 350,  'attractions_day' => 150,  'shopping_per_person' => 800  ],
            'Mid-range'  => ['transport_base' => 8000,   'transport_daily' => 400,  'accommodation_night' => 3000,  'food_day' => 1200, 'attractions_day' => 600,  'shopping_per_person' => 3000 ],
            'Luxury'     => ['transport_base' => 20000,  'transport_daily' => 1000, 'accommodation_night' => 8000,  'food_day' => 3000, 'attractions_day' => 1500, 'shopping_per_person' => 10000],
        ],
        'international' => [
            'Shoestring' => ['transport_base' => 25000,  'transport_daily' => 500,  'accommodation_night' => 2000,  'food_day' => 1000, 'attractions_day' => 500,  'shopping_per_person' => 2000 ],
            'Mid-range'  => ['transport_base' => 55000,  'transport_daily' => 1000, 'accommodation_night' => 5000,  'food_day' => 2500, 'attractions_day' => 1500, 'shopping_per_person' => 7000 ],
            'Luxury'     => ['transport_base' => 150000, 'transport_daily' => 3000, 'accommodation_night' => 15000, 'food_day' => 6000, 'attractions_day' => 4000, 'shopping_per_person' => 25000],
        ],
    ];

    /** Emails from the traveller's profile, prefilled onto a Group trip. */
    public array $groupMemberEmails = [];

    public function mount(): void
    {
        $this->calYear  = (int) date('Y');
        $this->calMonth = (int) date('n');

        // A traveller whose profile says they travel in a group shouldn't have
        // to re-pick "Group" and re-enter the same people on every trip — seed
        // step 6 from the profile instead. Still fully changeable in the step.
        $profile = auth()->user()?->userProfile;
        if ($profile && $profile->travel_style === 'Group') {
            $this->groupMemberEmails = array_values(array_filter((array) ($profile->group_member_emails ?? [])));
            $this->groupType = 'Group';
            // The traveller themself plus everyone listed.
            $this->travelers = max(2, count($this->groupMemberEmails) + 1);
        }
        // /trips/plan always starts the wizard fresh; /trips shows list or empty state
        $hasTrips = auth()->user()->trips()->exists();
        $isPlanRoute = request()->routeIs('trips.plan');
        $this->showList  = !$isPlanRoute && $hasTrips;
        $this->showEmpty = !$isPlanRoute && !$hasTrips;

        // Higher-priority deep-link from the AI Trip Planner's "Next" action:
        // the AI package already stands in for everything steps 2-5 would
        // otherwise have collected manually, so this lands straight on Step
        // 6 (Emergency Fund) with selectedFlight/Hotel/Venue/Attraction
        // synthesized from that package, as if the traveler had picked each
        // one by hand. One-time use (pull, not get) so refreshing this page
        // afterward behaves like any other normal wizard session instead of
        // replaying the same handoff forever. Checked before the "Edit"
        // handoff below since both can't apply to the same request.
        if (session()->has('wizard_ai_handoff')) {
            $handoff = session()->pull('wizard_ai_handoff');

            $this->planningMode    = 'manual';
            $this->manualFrom      = (string) ($handoff['from'] ?? '');
            $this->manualTo        = (string) ($handoff['to'] ?? '');
            $this->manualBudgetMin = (string) ($handoff['budget_min'] ?? '');
            $this->manualBudgetMax = (string) ($handoff['budget_max'] ?? $handoff['budget_min'] ?? '');
            $this->tripCurrency    = (string) ($handoff['currency'] ?? '');
            $this->startDate       = (string) ($handoff['start'] ?? '');
            $this->endDate         = (string) ($handoff['end'] ?? '');
            // Reuse the draft Trip the AI Planner already autosaved (see
            // Llm::autosaveDraft()) so this handoff updates it instead of
            // leaving it behind as an orphaned duplicate draft.
            $this->draftTripId     = $handoff['draft_trip_id'] ?? null;

            $this->selectedFlight     = $handoff['flight']     ?? null;
            $this->selectedHotel      = $handoff['hotel']      ?? null;
            $this->selectedVenues      = !empty($handoff['venue'])      ? [$handoff['venue']['name']      => $handoff['venue']]      : [];
            $this->selectedAttractions = !empty($handoff['attraction']) ? [$handoff['attraction']['name'] => $handoff['attraction']] : [];
            $this->flightTripType     = strtolower($this->selectedFlight['type'] ?? '') === 'one way' ? 'one_way' : 'round_trip';

            // Same international/local auto-detect selectAttraction() runs
            // right before its own transition to Step 6 — kept in sync so
            // the destinations() computed property behaves the same
            // regardless of which path got the traveler here.
            $intlKeywords = ['singapore','bangkok','phuket','bali','kuala lumpur','hong kong','tokyo','osaka','seoul','taipei','dubai','london','paris','new york','sydney','rome','barcelona','amsterdam','maldives','vietnam','hanoi','ho chi minh','jakarta','yangon','colombo','kathmandu','delhi','mumbai','beijing','shanghai','auckland'];
            $toLower = strtolower($this->manualTo);
            $isIntl  = false;
            foreach ($intlKeywords as $kw) {
                if (str_contains($toLower, $kw)) { $isIntl = true; break; }
            }
            $this->tripScope = $isIntl ? 'international' : 'local';

            $this->showList  = false;
            $this->showEmpty = false;
            $this->step = 6;
            return;
        }

        // "Continue Editing" from a Draft Trips card — resume the same draft
        // row (so autosaving keeps updating it instead of spawning a
        // duplicate) with its own From/To/Budget/Dates prefilled.
        if (request()->filled('draft')) {
            $draftTrip = Trip::where('id', (int) request()->query('draft'))
                ->where('user_id', auth()->id())
                ->where('status', 'draft')
                ->first();

            if ($draftTrip) {
                $this->draftTripId     = $draftTrip->id;
                $this->planningMode    = 'manual';
                $this->manualFrom      = (string) ($draftTrip->origin ?? '');
                $this->manualTo        = $draftTrip->destination !== 'Draft' ? (string) $draftTrip->destination : '';
                $this->manualBudgetMin = $draftTrip->budget_limit > 0 ? (string) (int) $draftTrip->budget_limit : '';
                $this->manualBudgetMax = $this->manualBudgetMin;
                $this->startDate       = (string) $draftTrip->start_date?->toDateString();
                $this->endDate         = (string) $draftTrip->end_date?->toDateString();
                if ($this->startDate && $this->endDate) $this->flightTripType = 'round_trip';

                $this->showList  = false;
                $this->showEmpty = false;

                if ($this->manualTo !== '' && $this->manualBudgetMin !== '' && $this->startDate !== '' && $this->endDate !== '') {
                    $this->proceedFromTripDetails();
                }
                return;
            }
        }

        // Optional deep-link from the AI Trip Planner's "Edit" action, which
        // wants the traveler to land straight on flight selection with their
        // route/budget/dates already filled in instead of re-entering
        // everything. Only engages when BOTH "from" and "to" query params
        // are present — every existing way of reaching this page (bare
        // /trips, /trips/plan, any bookmark) has neither, so this leaves
        // that behavior completely untouched.
        if (request()->filled('from') && request()->filled('to')) {
            $this->planningMode    = 'manual';
            $this->manualFrom      = (string) request()->query('from');
            $this->manualTo        = (string) request()->query('to');
            $budgetMin             = (string) request()->query('budget_min', '');
            $budgetMax             = (string) request()->query('budget_max', '');
            $this->manualBudgetMin = ($budgetMax !== '' && $budgetMax !== $budgetMin)
                ? "{$budgetMin} - {$budgetMax}"
                : $budgetMin;

            $start = (string) request()->query('start', '');
            $end   = (string) request()->query('end', '');
            if ($start !== '') $this->startDate = $start;
            if ($end   !== '') $this->endDate   = $end;
            if ($start !== '' && $end !== '') $this->flightTripType = 'round_trip';

            $this->showList  = false;
            $this->showEmpty = false;
            $this->proceedFromTripDetails();
        }
    }

    public function startNewTrip(): mixed
    {
        return $this->redirect(route('trips.plan'), navigate: true);
    }

    public function startFromEmpty(): void
    {
        $this->showEmpty = false;
    }

    public function confirmDelete(int $id): void
    {
        $this->tripToDelete = $id;
    }

    public function cancelDelete(): void
    {
        $this->tripToDelete = null;
    }

    public function deleteTrip(): void
    {
        if (!$this->tripToDelete) return;
        $trip = Trip::where('id', $this->tripToDelete)->where('user_id', auth()->id())->firstOrFail();
        $trip->delete();
        $this->tripToDelete = null;
        if (!auth()->user()->trips()->exists()) {
            $this->showList = false;
            $this->step     = 1;
        }
    }

    public function getMyTripsProperty()
    {
        return auth()->user()->trips()
            ->withSum('budgets as total_estimated', 'estimated_cost')
            ->withSum('expenses as total_spent', 'amount')
            ->latest('start_date')
            ->get();
    }

    // ── Step 1 ─────────────────────────────────────────────
    public function selectPlanningMode(string $mode): void
    {
        $this->planningMode = $mode;
        $this->step = 1;
    }

    public static function staticIataCode(string $city): string
    {
        return (new self)->iataCode($city);
    }

    // Carbon's 'M' format char always gives the standard 3-letter English
    // abbreviation ("Sep") with no locale/translation hook that yields
    // "Sept" instead — post-process the one month that differs.
    public static function fmtDate(?string $date, string $format = 'M j, Y'): string
    {
        if (!$date) return '';
        return str_replace('Sep ', 'Sept ', \Carbon\Carbon::parse($date)->format($format));
    }

    public function iataCode(string $city): string
    {
        $map = [
            // Philippines
            'manila' => 'MNL', 'pasay' => 'MNL', 'makati' => 'MNL', 'quezon city' => 'MNL',
            'paranaque' => 'MNL', 'pasig' => 'MNL', 'taguig' => 'MNL', 'las pinas' => 'MNL',
            'ncr' => 'MNL', 'metro manila' => 'MNL', 'tagaytay' => 'MNL',
            'cebu' => 'CEB', 'cebu city' => 'CEB', 'mactan' => 'CEB',
            'davao' => 'DVO', 'davao city' => 'DVO',
            'boracay' => 'MPH', 'kalibo' => 'KLO', 'malay' => 'MPH',
            'bohol' => 'TAG', 'tagbilaran' => 'TAG', 'tagbilaran (bohol)' => 'TAG',
            'palawan' => 'PPS', 'puerto princesa' => 'PPS',
            'el nido' => 'ENI',
            'coron' => 'USU', 'busuanga' => 'USU',
            'siargao' => 'IAO', 'del carmen' => 'IAO',
            'bacolod' => 'BCD',
            'iloilo' => 'ILO', 'iloilo city' => 'ILO',
            'zamboanga' => 'ZAM',
            'cagayan de oro' => 'CGY', 'cagayan' => 'CGY',
            'general santos' => 'GES', 'gensan' => 'GES',
            'tacloban' => 'TAC', 'leyte' => 'TAC',
            'dumaguete' => 'DGT', 'siquijor' => 'DGT',
            'surigao' => 'SUG',
            'cotabato' => 'CBO',
            'batanes' => 'BSO', 'basco' => 'BSO',
            'camiguin' => 'CGM',
            'laoag' => 'LAO',
            'vigan' => 'VIG',
            'baguio' => 'BAG',
            'legazpi' => 'LGP', 'legazpi city' => 'LGP',
            'naga' => 'WNP', 'naga city' => 'WNP',
            'roxas' => 'RXS', 'roxas city' => 'RXS',
            'san jose' => 'SJI',
            'ozamiz' => 'OZC',
            'dipolog' => 'DPL',
            'butuan' => 'BXU',
            'pagadian' => 'PAG',
            'virac' => 'VRC',
            'tuguegarao' => 'TUG',
            'cauayan' => 'CYZ',
            'puerto galera' => 'MNL',
            // Southeast Asia
            'singapore' => 'SIN',
            'bangkok' => 'BKK', 'thailand' => 'BKK', 'suvarnabhumi' => 'BKK',
            'phuket' => 'HKT', 'krabi' => 'KBV', 'chiang mai' => 'CNX',
            'bali' => 'DPS', 'denpasar' => 'DPS',
            'jakarta' => 'CGK', 'indonesia' => 'CGK',
            'kuala lumpur' => 'KUL', 'malaysia' => 'KUL', 'kl' => 'KUL',
            'penang' => 'PEN', 'langkawi' => 'LGK', 'kota kinabalu' => 'BKI',
            'hong kong' => 'HKG',
            'macau' => 'MFM',
            'ho chi minh city' => 'SGN', 'ho chi minh' => 'SGN', 'hcmc' => 'SGN', 'saigon' => 'SGN',
            'hanoi' => 'HAN', 'vietnam' => 'SGN',
            'da nang' => 'DAD',
            'yangon' => 'RGN', 'myanmar' => 'RGN',
            'phnom penh' => 'PNH', 'cambodia' => 'PNH',
            'siem reap' => 'REP',
            'vientiane' => 'VTE', 'laos' => 'VTE',
            'colombo' => 'CMB', 'sri lanka' => 'CMB',
            'dhaka' => 'DAC', 'bangladesh' => 'DAC',
            'kathmandu' => 'KTM', 'nepal' => 'KTM',
            // East Asia
            'tokyo' => 'NRT', 'japan' => 'NRT',
            'osaka' => 'KIX',
            'nagoya' => 'NGO',
            'fukuoka' => 'FUK',
            'sapporo' => 'CTS',
            'okinawa' => 'OKA',
            'seoul' => 'ICN', 'korea' => 'ICN', 'incheon' => 'ICN',
            'busan' => 'PUS',
            'taipei' => 'TPE', 'taiwan' => 'TPE',
            'kaohsiung' => 'KHH',
            'beijing' => 'PEK', 'china' => 'PEK',
            'shanghai' => 'PVG',
            'guangzhou' => 'CAN',
            'shenzhen' => 'SZX',
            // South Asia
            'delhi' => 'DEL', 'new delhi' => 'DEL', 'india' => 'DEL',
            'mumbai' => 'BOM', 'bombay' => 'BOM',
            'bangalore' => 'BLR', 'bengaluru' => 'BLR',
            'chennai' => 'MAA', 'madras' => 'MAA',
            'kolkata' => 'CCU',
            'hyderabad' => 'HYD',
            // Middle East
            'dubai' => 'DXB', 'uae' => 'DXB',
            'abu dhabi' => 'AUH',
            'doha' => 'DOH', 'qatar' => 'DOH',
            'riyadh' => 'RUH', 'saudi arabia' => 'RUH',
            'jeddah' => 'JED',
            'kuwait' => 'KWI', 'kuwait city' => 'KWI',
            'bahrain' => 'BAH',
            'muscat' => 'MCT', 'oman' => 'MCT',
            'amman' => 'AMM', 'jordan' => 'AMM',
            'tel aviv' => 'TLV', 'israel' => 'TLV',
            'istanbul' => 'IST', 'turkey' => 'IST',
            // Europe
            'london' => 'LHR',
            'paris' => 'CDG',
            'amsterdam' => 'AMS',
            'frankfurt' => 'FRA', 'germany' => 'FRA',
            'rome' => 'FCO', 'italy' => 'FCO',
            'madrid' => 'MAD', 'spain' => 'MAD',
            'barcelona' => 'BCN',
            'vienna' => 'VIE', 'austria' => 'VIE',
            'zurich' => 'ZRH', 'switzerland' => 'ZRH',
            'brussels' => 'BRU', 'belgium' => 'BRU',
            'lisbon' => 'LIS', 'portugal' => 'LIS',
            'athens' => 'ATH', 'greece' => 'ATH',
            'prague' => 'PRG', 'czech republic' => 'PRG',
            'budapest' => 'BUD', 'hungary' => 'BUD',
            'warsaw' => 'WAW', 'poland' => 'WAW',
            'stockholm' => 'ARN', 'sweden' => 'ARN',
            'oslo' => 'OSL', 'norway' => 'OSL',
            'copenhagen' => 'CPH', 'denmark' => 'CPH',
            'helsinki' => 'HEL', 'finland' => 'HEL',
            'moscow' => 'SVO', 'russia' => 'SVO',
            // Oceania
            'sydney' => 'SYD', 'australia' => 'SYD',
            'melbourne' => 'MEL',
            'brisbane' => 'BNE',
            'perth' => 'PER',
            'auckland' => 'AKL', 'new zealand' => 'AKL',
            // Americas
            'new york' => 'JFK', 'new york city' => 'JFK', 'nyc' => 'JFK',
            'los angeles' => 'LAX', 'la' => 'LAX',
            'san francisco' => 'SFO',
            'chicago' => 'ORD',
            'miami' => 'MIA',
            'toronto' => 'YYZ', 'canada' => 'YYZ',
            'vancouver' => 'YVR',
            'sao paulo' => 'GRU', 'brazil' => 'GRU',
            // Africa
            'nairobi' => 'NBO', 'kenya' => 'NBO',
            'johannesburg' => 'JNB', 'south africa' => 'JNB',
            'cairo' => 'CAI', 'egypt' => 'CAI',
            'casablanca' => 'CMN', 'morocco' => 'CMN',
            // Maldives
            'maldives' => 'MLE', 'male' => 'MLE',
        ];

        return $map[strtolower(trim($city))] ?? '';
    }

    private function resolveCode(string $city): string
    {
        $code = $this->iataCode($city);
        return $code !== '' ? $code : trim($city);
    }

    public function proceedFromTripDetails(): void
    {
        // Alpine already gates the obvious empties before this ever runs; this
        // stays as the backstop for anything the client and server disagree
        // on, and shakes the same fields instead of opening a modal.
        $missing = [];
        if (trim($this->manualFrom) === '')                                  $missing[] = 'from';
        if (trim($this->manualTo) === '')                                    $missing[] = 'to';
        if (trim(preg_replace('/[^\d]/', '', $this->manualBudgetMin)) === '') $missing[] = 'budget';
        if ($this->startDate === '')                                         $missing[] = 'start';
        if ($this->endDate === '')                                           $missing[] = 'end';

        if ($missing) {
            $this->dispatch('trip-details-missing', fields: $missing);
            return;
        }

        if (strtolower(trim($this->manualFrom)) === strtolower(trim($this->manualTo))) {
            // addError() had nowhere to render — this view has no @error slot
            // anywhere, so clicking Next with matching cities used to do
            // nothing at all. Shake the destination field instead.
            $this->dispatch('trip-details-missing', fields: ['to']);
            return;
        }

        // Parse single budget field: "30,000 - 50,000" or "30000" or "30,000 to 50,000"
        $raw = $this->manualBudgetMin;
        if (preg_match('/(\d[\d,]*)\s*(?:[-–to]+)\s*(\d[\d,]*)/i', $raw, $m)) {
            $this->manualBudgetMin = preg_replace('/[^\d]/', '', $m[1]);
            $this->manualBudgetMax = preg_replace('/[^\d]/', '', $m[2]);
        } else {
            $single = preg_replace('/[^\d]/', '', $raw);
            $this->manualBudgetMin = $single;
            $this->manualBudgetMax = $single;
        }

        // The manual-typing path autosaves as the traveler fills in Trip
        // Details (see the Alpine $watch in the blade). The two deep-link
        // paths that jump straight here — "Continue Editing" a draft, and
        // "Edit" from the AI Planner results — set From/To/Budget/Dates
        // programmatically and skip that step entirely, so without this
        // call a traveler who abandons mid-flow (e.g. on Food & Dining)
        // would leave no draft behind at all.
        $this->autosaveDraft();

        // Editing one specific card from the AI Planner results ("Edit" on
        // Accommodation, Food, etc.) should land the traveler straight on
        // that section instead of always restarting at flight search —
        // otherwise "Edit Accommodation" would make them pick a flight
        // first just to reach the accommodation list they actually wanted.
        $editSection = session('ai_edit_section');
        if ($editSection && $editSection !== 'transport') {
            $intlKeywords = ['singapore','bangkok','phuket','bali','kuala lumpur','hong kong','tokyo','osaka','seoul','taipei','dubai','london','paris','new york','sydney','rome','barcelona','amsterdam','maldives','vietnam','hanoi','ho chi minh','jakarta','yangon','colombo','kathmandu','delhi','mumbai','beijing','shanghai','auckland'];
            $toLower = strtolower($this->manualTo);
            $isIntl  = false;
            foreach ($intlKeywords as $kw) {
                if (str_contains($toLower, $kw)) { $isIntl = true; break; }
            }
            $this->tripScope = $isIntl ? 'international' : 'local';

            if ($editSection === 'accommodation') {
                $this->step = 3;
                $this->searchAccommodations();
                return;
            }
            if ($editSection === 'food') {
                $this->step = 4;
                $this->searchVenues();
                return;
            }
            if ($editSection === 'attractions') {
                $this->step = 5;
                $this->searchAttractionsList();
                return;
            }
        }

        $this->step = 2;
        $this->searchManualFlights();
    }

    /**
     * Keeps only nonstop itineraries.
     *
     * Google Flights returns connections whose layover dwarfs the flying —
     * a "16h 15m" Cebu-Manila option that is a short hop plus 14h 20m sitting
     * in Bacolod. Those are not trips anyone plans around, and their total
     * duration was feeding the trip's travel estimate as if it were airtime.
     *
     * If a route has no direct service at all (long-haul international, say),
     * the connecting options are kept rather than showing an empty board and
     * a "we couldn't load flights" message that would not be true.
     *
     * array_values matters: selectFlight()/selectMcFlight() index into these
     * arrays by position, and array_filter preserves the original keys.
     */
    private function directOnly(array $flights): array
    {
        $direct = array_values(array_filter(
            $flights,
            fn ($f) => (int) ($f['stops'] ?? 0) === 0
        ));

        return $direct ?: $flights;
    }

    public function searchManualFlights(): void
    {
        set_time_limit(120);
        $this->flightLoading   = true;
        $this->flightResults   = [];
        $this->flightError     = '';
        $this->mcFlightStep    = false;
        $this->mcSearched      = $this->flightTripType === 'multi_city' && !empty($this->mcTo);
        $this->mcFlightResults = [];
        $this->selectedFlight  = null;
        $this->selectedMcFlight = null;
        $this->confirmFlightIndex = null;
        $this->confirmMcFlightIndex = null;

        $serp      = new SerpApiService();
        $fromCode  = $this->resolveCode($this->manualFrom);
        $toCode    = $this->resolveCode($this->manualTo);

        if ($fromCode === $toCode || strtolower(trim($this->manualFrom)) === strtolower(trim($this->manualTo))) {
            $this->flightError   = 'Origin and destination cannot be the same city. Please choose a different destination.';
            $this->flightLoading = false;
            return;
        }
        $depart    = $this->startDate ?: date('Y-m-d', strtotime('+7 days'));
        $return    = $this->flightTripType === 'round_trip' && $this->endDate ? $this->endDate : '';

        try {
            $data = $serp->searchFlightsRaw($fromCode, $toCode, $depart, $return);
            if (empty($data)) {
                $serper = new SerperService();
                $data = $serper->searchFlights($fromCode, $toCode, $depart, $return);
            }
            $this->flightResults = $this->directOnly($data ?? []);
        } catch (\Throwable $e) {
            try {
                $serper = new SerperService();
                $this->flightResults = $this->directOnly($serper->searchFlights($fromCode, $toCode, $depart, $return) ?? []);
            } catch (\Throwable $e2) {
                $this->flightResults = [];
            }
        }

        if (empty($this->flightResults)) {
            $this->flightError = 'We couldn\'t load flights right now. Try searching again in a moment.';
        }

        $this->flightLoading = false;
    }

    // Patches a single card of the AI planner's package (transport /
    // accommodation / food / attractions) with a freshly picked item and
    // sends the traveler straight back to the AI results screen, instead of
    // letting the wizard carry on to its next step. Only fires when the
    // wizard was opened via the results screen's per-card "Edit" link for
    // this exact section — a normal walk through the wizard is unaffected.
    private function maybeReturnToAiEdit(string $section, array $patch): bool
    {
        if (session('ai_edit_section') !== $section) return false;

        $draft = AiConversationDraft::where('user_id', auth()->id())->first();
        if (!$draft) {
            session()->forget(['ai_edit_return', 'ai_edit_section']);
            return false;
        }

        $pkg = $draft->ai_package ?? [];
        $pkg[$section] = array_merge($pkg[$section] ?? [], $patch);
        $pkg['total'] = collect(['transport', 'accommodation', 'food', 'attractions'])
            ->sum(fn ($key) => $pkg[$key]['cost'] ?? 0);
        $draft->update(['ai_package' => $pkg]);

        session()->forget(['ai_edit_return', 'ai_edit_section']);
        $this->redirect(route('trips.plan.ai'), navigate: true);
        return true;
    }

    public function confirmFlightPick(int $index): void
    {
        $this->confirmFlightIndex = $index;
    }

    public function cancelFlightPick(): void
    {
        $this->confirmFlightIndex = null;
    }

    public function selectFlight(int $index): void
    {
        $this->confirmFlightIndex = null;
        $this->selectedFlight = $this->flightResults[$index] ?? null;
        // A re-search between render and click can leave this index pointing
        // past the end of a now-shorter array — don't silently advance with
        // a null selection.
        if ($this->selectedFlight === null) {
            $this->flightError = 'That flight is no longer available. Please pick another.';
            return;
        }

        if ($this->maybeReturnToAiEdit('transport', [
            'from_code' => $this->selectedFlight['dep_id'] ?? $this->resolveCode($this->manualFrom ?? $this->aiFrom ?? ''),
            'to_code'   => $this->selectedFlight['arr_id'] ?? $this->resolveCode($this->manualTo ?? ''),
            'detail'    => trim(($this->selectedFlight['airline'] ?? 'Airline') . ' ' . ($this->selectedFlight['number'] ?? '')) . ' · ' . ($this->selectedFlight['type'] ?? 'Round Trip'),
            'cost'      => (int) ($this->selectedFlight['price'] ?? 0),
        ])) {
            return;
        }

        // Multi-city: search leg 2 flights before going to accommodation
        if ($this->flightTripType === 'multi_city' && $this->mcTo && $this->mcStartDate) {
            // Same reasoning as searchManualFlights()'s bump above: a real
            // SerpAPI call with its own retry, made synchronously here
            // rather than through an already-covered method.
            set_time_limit(120);
            $this->mcFlightStep    = true;
            $this->mcFlightResults = [];
            $this->mcFlightLoading = true;
            $serp      = new SerpApiService();
            $fromCode  = $this->resolveCode($this->manualTo);
            $toCode    = $this->resolveCode($this->mcTo);
            try {
                // Each multi-city leg is a one-way flight, not a round trip
                $data = $serp->searchFlightsRaw($fromCode, $toCode, $this->mcStartDate);
                if (empty($data)) {
                    $serper = new SerperService();
                    $data = $serper->searchFlights($fromCode, $toCode, $this->mcStartDate);
                }
                $this->mcFlightResults = $this->directOnly($data ?? []);
            } catch (\Throwable $e) {
                try {
                    $serper = new SerperService();
                    $this->mcFlightResults = $this->directOnly($serper->searchFlights($fromCode, $toCode, $this->mcStartDate) ?? []);
                } catch (\Throwable $e2) {
                    $this->mcFlightResults = [];
                }
            }
            $this->mcFlightLoading = false;
            return;
        }

        // Auto-detect scope from destination
        $intlKeywords = ['singapore','bangkok','phuket','bali','kuala lumpur','hong kong','tokyo','osaka','seoul','taipei','dubai','london','paris','new york','sydney','rome','barcelona','amsterdam','maldives','vietnam','hanoi','ho chi minh','jakarta','yangon','colombo','kathmandu','delhi','mumbai','beijing','shanghai','auckland'];
        $toLower = strtolower($this->manualTo);
        $isIntl  = false;
        foreach ($intlKeywords as $kw) {
            if (str_contains($toLower, $kw)) { $isIntl = true; break; }
        }
        $this->tripScope = $isIntl ? 'international' : 'local';
        $this->step = 3;
        $this->searchAccommodations();
    }

    public function confirmMcFlightPick(int $index): void
    {
        $this->confirmMcFlightIndex = $index;
    }

    public function cancelMcFlightPick(): void
    {
        $this->confirmMcFlightIndex = null;
    }

    public function selectMcFlight(int $index): void
    {
        $this->confirmMcFlightIndex = null;
        $this->selectedMcFlight = $this->mcFlightResults[$index] ?? null;
        if ($this->selectedMcFlight === null) {
            $this->flightError = 'That flight is no longer available. Please pick another.';
            return;
        }
        $this->mcFlightStep = false;

        $intlKeywords = ['singapore','bangkok','phuket','bali','kuala lumpur','hong kong','tokyo','osaka','seoul','taipei','dubai','london','paris','new york','sydney','rome','barcelona','amsterdam','maldives','vietnam','hanoi','ho chi minh','jakarta','yangon','colombo','kathmandu','delhi','mumbai','beijing','shanghai','auckland'];
        $toLower = strtolower($this->mcTo);
        $isIntl  = false;
        foreach ($intlKeywords as $kw) {
            if (str_contains($toLower, $kw)) { $isIntl = true; break; }
        }
        $this->tripScope = $isIntl ? 'international' : 'local';
        $this->step = 3;
        $this->searchAccommodations();
    }

    public function searchAccommodations(): void
    {
        set_time_limit(120);
        $serp = new SerpApiService();

        $this->hotelError = '';

        if ($this->mcHotelStep) {
            // Re-search second destination
            $this->mcHotelLoading = true;
            $this->mcHotelResults = [];
            $checkIn  = $this->mcStartDate ?: $this->startDate ?: date('Y-m-d', strtotime('+7 days'));
            $checkOut = $this->mcEndDate   ?: $this->endDate   ?: date('Y-m-d', strtotime('+12 days'));
            $nights   = max(1, \Carbon\Carbon::parse($checkIn)->diffInDays(\Carbon\Carbon::parse($checkOut)));
            try {
                $data = $serp->searchHotelsRaw($this->mcTo, $checkIn, $checkOut, $nights, $this->hotelType);
                if (empty($data)) {
                    $serper = new SerperService();
                    $data = $serper->searchHotels($this->mcTo, $checkIn, $checkOut, $nights, $this->hotelType);
                }
                $this->mcHotelResults = $data ?? [];
            } catch (\Throwable $e) {
                try {
                    $serper = new SerperService();
                    $this->mcHotelResults = $serper->searchHotels($this->mcTo, $checkIn, $checkOut, $nights, $this->hotelType) ?? [];
                } catch (\Throwable $e2) {
                    $this->mcHotelResults = [];
                }
            }
            // Unlike the main leg below, this branch previously had no
            // fallback — a total API failure left mcHotelResults empty with
            // no way forward for the user.
            if (empty($this->mcHotelResults)) {
                $this->mcHotelResults = $this->fallbackHotels($this->mcTo, $nights, $this->hotelType);
            }
            $this->mcHotelLoading = false;
            return;
        }

        $this->hotelLoading    = true;
        $this->hotelResults    = [];
        // A filter left over from the previous result set would hide the new
        // one and read as "search returned nothing".
        $this->clearHotelSearch();
        $this->mcHotelResults  = [];
        $this->selectedHotel   = null;
        $this->selectedMcHotel = null;
        $checkIn  = $this->startDate ?: date('Y-m-d', strtotime('+7 days'));
        $checkOut = $this->endDate   ?: date('Y-m-d', strtotime('+12 days'));
        $nights   = max(1, \Carbon\Carbon::parse($checkIn)->diffInDays(\Carbon\Carbon::parse($checkOut)));
        try {
            $data = $serp->searchHotelsRaw($this->manualTo, $checkIn, $checkOut, $nights, $this->hotelType);
            if (empty($data)) {
                $serper = new SerperService();
                $data = $serper->searchHotels($this->manualTo, $checkIn, $checkOut, $nights, $this->hotelType);
            }
            $this->hotelResults = $data ?? [];
        } catch (\Throwable $e) {
            try {
                $serper = new SerperService();
                $this->hotelResults = $serper->searchHotels($this->manualTo, $checkIn, $checkOut, $nights, $this->hotelType) ?? [];
            } catch (\Throwable $e2) {
                $this->hotelResults = [];
            }
        }

        if (empty($this->hotelResults)) {
            $this->hotelResults = $this->fallbackHotels($this->manualTo, $nights, $this->hotelType);
        }

        $this->hotelLoading = false;
    }

    private function fallbackHotels(string $destination, int $nights, string $type = 'hotel'): array
    {
        $dest = $destination;
        $templates = [
            'hotel' => [
                ['name' => 'Grand %s Hotel',          'stars' => 4, 'nightly' => 3500],
                ['name' => '%s Crown Hotel',           'stars' => 4, 'nightly' => 2800],
                ['name' => 'Seda %s',                  'stars' => 5, 'nightly' => 6500],
                ['name' => 'Go Hotels %s',             'stars' => 3, 'nightly' => 1200],
                ['name' => '%s Bay Hotel',             'stars' => 3, 'nightly' => 1800],
                ['name' => 'Microtel by Wyndham %s',  'stars' => 3, 'nightly' => 1500],
                ['name' => 'Crimson Hotel %s',        'stars' => 5, 'nightly' => 7200],
                ['name' => '%s Suites Hotel',         'stars' => 3, 'nightly' => 2200],
            ],
            'apartment' => [
                ['name' => '%s Studio Apartments',    'stars' => 3, 'nightly' => 1800],
                ['name' => 'Garden Residences %s',    'stars' => 3, 'nightly' => 2200],
                ['name' => '%s Service Apartments',   'stars' => 4, 'nightly' => 3000],
                ['name' => 'City Flats %s',           'stars' => 3, 'nightly' => 1500],
            ],
            'inn' => [
                ['name' => 'Happy Inn %s',            'stars' => 2, 'nightly' => 800],
                ['name' => '%s Travelers Lodge',      'stars' => 2, 'nightly' => 700],
                ['name' => 'Cozy Inn %s',             'stars' => 2, 'nightly' => 900],
                ['name' => '%s Guesthouse',           'stars' => 2, 'nightly' => 650],
            ],
            'resort' => [
                ['name' => '%s Beach Resort',         'stars' => 5, 'nightly' => 8500],
                ['name' => 'Shangri-La %s Resort',    'stars' => 5, 'nightly' => 12000],
                ['name' => '%s Cove Resort & Spa',     'stars' => 4, 'nightly' => 6500],
                ['name' => 'Paradise %s Resort',      'stars' => 4, 'nightly' => 5500],
            ],
        ];
        $list = $templates[$type] ?? $templates['hotel'];
        $shortDest = explode(',', $dest)[0];

        return array_map(function ($t) use ($shortDest, $nights, $type) {
            $nightly = $t['nightly'];
            return [
                'name'      => sprintf($t['name'], $shortDest),
                'stars'     => $t['stars'],
                'image'     => null,
                'nightly'   => $nightly,
                'total'     => $nightly * $nights,
                'nights'    => $nights,
                'dist'      => null,
                'type'      => $type,
                'typeLabel' => ucfirst($type),
                'lat'       => null,
                'lng'       => null,
            ];
        }, $list);
    }

    public function skipAccommodation(): void
    {
        $this->selectedHotel = null;

        // Skipping leg 1's accommodation on a multi-city trip should still
        // offer leg 2's accommodation, not jump straight past both legs —
        // same transition selectAccommodation() makes when a hotel IS picked.
        if (!$this->mcHotelStep && $this->flightTripType === 'multi_city' && $this->mcTo) {
            $this->mcHotelStep    = true;
            $this->mcHotelResults = [];
            $this->mcHotelLoading = true;
            $this->searchMcHotels();
            return;
        }

        $this->selectedMcHotel = null;
        $this->mcHotelStep     = false;
        $this->step = 4;
        $this->searchVenues();
    }

    public function selectAccommodation(int $index): void
    {
        $this->selectedHotel = $this->hotelResults[$index] ?? null;
        if ($this->selectedHotel === null) {
            $this->hotelError = 'That stay is no longer available. Please pick another.';
            return;
        }

        if ($this->maybeReturnToAiEdit('accommodation', [
            'name'   => $this->selectedHotel['name'] ?? 'Hotel',
            'stars'  => $this->selectedHotel['stars'] ?? 3,
            'detail' => ($this->selectedHotel['nights'] ?? 1) . ' Nights · ' . ($this->selectedHotel['typeLabel'] ?? 'Standard Room') . ' · ' . $this->manualTo,
            'cost'   => (int) ($this->selectedHotel['total'] ?? 0),
        ])) {
            return;
        }

        if ($this->flightTripType === 'multi_city' && $this->mcTo) {
            $this->mcHotelStep    = true;
            $this->mcHotelResults = [];
            $this->mcHotelLoading = true;
            // Search synchronously in the same request (like leg 1's
            // searchVenues() call below) so results are ready by the time
            // this response renders — dispatching an event here instead
            // meant a second round trip: the page would render the "Leg 2"
            // shell with an empty/loading state first, then only fetch
            // results after that separate follow-up request came back.
            $this->searchMcHotels();
            return;
        }

        $this->step = 4;
        $this->searchVenues();
    }

    #[\Livewire\Attributes\On('searchMcHotels')]
    public function searchMcHotels(): void
    {
        set_time_limit(120);
        $checkIn  = $this->mcStartDate ?: $this->startDate ?: date('Y-m-d', strtotime('+7 days'));
        $checkOut = $this->mcEndDate   ?: $this->endDate   ?: date('Y-m-d', strtotime($checkIn . ' +5 days'));
        $nights   = max(1, \Carbon\Carbon::parse($checkIn)->diffInDays(\Carbon\Carbon::parse($checkOut)));
        $serp = new SerpApiService();
        try {
            $data = $serp->searchHotelsRaw($this->mcTo, $checkIn, $checkOut, $nights, $this->hotelType);
            if (empty($data)) {
                $serper = new SerperService();
                $data = $serper->searchHotels($this->mcTo, $checkIn, $checkOut, $nights, $this->hotelType);
            }
            $this->mcHotelResults = $data ?? [];
        } catch (\Throwable $e) {
            try {
                $serper = new SerperService();
                $this->mcHotelResults = $serper->searchHotels($this->mcTo, $checkIn, $checkOut, $nights, $this->hotelType) ?? [];
            } catch (\Throwable $e2) {
                $this->mcHotelResults = [];
            }
        }
        if (empty($this->mcHotelResults)) {
            $this->mcHotelResults = $this->fallbackHotels($this->mcTo, $nights, $this->hotelType);
        }
        $this->mcHotelLoading = false;
    }

    public function selectMcAccommodation(int $index): void
    {
        $this->selectedMcHotel = $this->mcHotelResults[$index] ?? null;
        if ($this->selectedMcHotel === null) {
            $this->hotelError = 'That stay is no longer available. Please pick another.';
            return;
        }
        $this->mcHotelStep = false;
        $this->step = 4;
        $this->searchVenues();
    }

    // ── Step 4: food & dining ──────────────────────────────
    public function searchVenues(): void
    {
        set_time_limit(60);
        $this->venueLoading        = true;
        $this->venueResults        = [];
        $this->clearVenueSearch();
        $this->venueError          = '';
        $this->mcVenueStep         = false;
        $this->mcVenueResults      = [];
        $this->mcAttractionStep    = false;
        $this->mcAttractionResults = [];
        $dest = $this->manualTo ?: $this->mcTo ?: '';
        if (!$dest) { $this->venueLoading = false; return; }
        $serp = new SerpApiService();
        try {
            $this->venueResults = $serp->searchRestaurantsRaw($dest, $this->venueCategory) ?? [];
            if (empty($this->venueResults)) {
                $serper = new SerperService();
                $this->venueResults = $serper->searchRestaurants($dest, $this->venueCategory) ?? [];
            }
        } catch (\Throwable $e) {
            try {
                $serper = new SerperService();
                $this->venueResults = $serper->searchRestaurants($dest, $this->venueCategory) ?? [];
            } catch (\Throwable $e2) {
                $this->venueResults = [];
            }
        }
        if (empty($this->venueResults)) {
            $this->venueError = 'We couldn\'t load dining options right now. Try searching again, or skip this step.';
        }
        $this->venueLoading = false;
    }

    public function skipVenue(): void
    {
        $this->selectedVenues = [];

        // Same reasoning as skipAccommodation() above — skipping leg 1's
        // food & dining shouldn't skip leg 2's too.
        if (!$this->mcVenueStep && $this->flightTripType === 'multi_city' && $this->mcTo) {
            $this->mcVenueStep    = true;
            $this->mcVenueResults = [];
            $this->mcVenueLoading = true;
            $this->searchMcVenues();
            return;
        }

        $this->selectedMcVenues = [];
        $this->mcVenueStep      = false;
        $this->step = 5;
        $this->searchAttractionsList();
    }

    // Toggles a venue in/out of the current leg's selection — lets the
    // traveler pick more than one restaurant/venue instead of the old
    // "click one, auto-advance" flow. continueFromVenues() is what
    // actually moves the wizard forward once they're done picking.
    public function toggleVenue(int $index): void
    {
        $source = $this->mcVenueStep ? $this->mcVenueResults : $this->venueResults;
        $item   = $source[$index] ?? null;
        if ($item === null) {
            $this->venueError = 'That venue is no longer available. Please pick another.';
            return;
        }

        if (!$this->mcVenueStep) {
            if (isset($this->selectedVenues[$item['name']])) {
                unset($this->selectedVenues[$item['name']]);
            } else {
                $this->selectedVenues[$item['name']] = $item;
            }
        } else {
            if (isset($this->selectedMcVenues[$item['name']])) {
                unset($this->selectedMcVenues[$item['name']]);
            } else {
                $this->selectedMcVenues[$item['name']] = $item;
            }
        }
    }

    public function continueFromVenues(): void
    {
        if (!$this->mcVenueStep) {
            $days  = max(1, $this->days);
            $names = array_map(fn ($v) => $v['name'] ?? 'Restaurant', array_values($this->selectedVenues));
            if ($this->maybeReturnToAiEdit('food', [
                'name'   => $names ? implode(', ', $names) : 'Restaurant',
                'detail' => $days . ' Days · Breakfast, Lunch, & Dinner · ' . $this->manualTo,
                'cost'   => $this->selectedVenuesCost() * $days,
            ])) {
                return;
            }

            if ($this->flightTripType === 'multi_city' && $this->mcTo) {
                $this->mcVenueStep    = true;
                $this->mcVenueResults = [];
                $this->mcVenueLoading = true;
                // Synchronous, same reasoning as selectAccommodation() above.
                $this->searchMcVenues();
            } else {
                $this->step = 5;
                $this->searchAttractionsList();
            }
        } else {
            $this->step = 5;
            $this->searchAttractionsList();
        }
    }

    #[On('searchMcVenues')]
    public function searchMcVenues(): void
    {
        set_time_limit(120);
        $serp = new SerpApiService();
        try {
            $this->mcVenueResults = $serp->searchRestaurantsRaw($this->mcTo, $this->venueCategory) ?? [];
            if (empty($this->mcVenueResults)) {
                $serper = new SerperService();
                $this->mcVenueResults = $serper->searchRestaurants($this->mcTo, $this->venueCategory) ?? [];
            }
        } catch (\Throwable) {
            try {
                $serper = new SerperService();
                $this->mcVenueResults = $serper->searchRestaurants($this->mcTo, $this->venueCategory) ?? [];
            } catch (\Throwable) {
                $this->mcVenueResults = [];
            }
        }
        if (empty($this->mcVenueResults)) {
            $this->venueError = 'We couldn\'t load dining options right now. Try searching again, or skip this step.';
        }
        $this->mcVenueLoading = false;
    }

    // ── Step 5: attractions ────────────────────────────────
    public function searchAttractionsList(): void
    {
        set_time_limit(60);
        $serp = new SerpApiService();

        if ($this->flightTripType !== 'multi_city' || !$this->mcTo) {
            $this->mcAttractionStep = false;
        }

        $this->attractionError = '';

        if ($this->mcAttractionStep) {
            $this->mcAttractionLoading = true;
            $this->mcAttractionResults = [];
            $this->clearAttractionSearch();
            try {
                $this->mcAttractionResults = $serp->searchAttractionsRaw($this->mcTo, $this->attractionType) ?? [];
                if (empty($this->mcAttractionResults)) {
                    $serper = new SerperService();
                    $this->mcAttractionResults = $serper->searchAttractions($this->mcTo) ?? [];
                }
            } catch (\Throwable $e) {
                try {
                    $serper = new SerperService();
                    $this->mcAttractionResults = $serper->searchAttractions($this->mcTo) ?? [];
                } catch (\Throwable $e2) {
                    $this->mcAttractionResults = [];
                }
            }
            if (empty($this->mcAttractionResults)) {
                $this->attractionError = 'We couldn\'t load attractions right now. Try searching again, or skip this step.';
            }
            $this->mcAttractionLoading = false;
            return;
        }

        $this->attractionLoading      = true;
        $this->attractionResults      = [];
        $this->clearAttractionSearch();
        $this->mcAttractionResults    = [];
        $dest = $this->manualTo ?: $this->mcTo ?: '';
        if (!$dest) { $this->attractionLoading = false; return; }
        try {
            $this->attractionResults = $serp->searchAttractionsRaw($dest, $this->attractionType) ?? [];
            if (empty($this->attractionResults)) {
                $serper = new SerperService();
                $this->attractionResults = $serper->searchAttractions($dest) ?? [];
            }
        } catch (\Throwable $e) {
            try {
                $serper = new SerperService();
                $this->attractionResults = $serper->searchAttractions($dest) ?? [];
            } catch (\Throwable $e2) {
                $this->attractionResults = [];
            }
        }
        if (empty($this->attractionResults)) {
            $this->attractionError = 'We couldn\'t load attractions right now. Try searching again, or skip this step.';
        }
        $this->attractionLoading = false;
    }

    public function skipAttraction(): void
    {
        $this->selectedAttractions = [];

        // Same reasoning as skipAccommodation() above — skipping leg 1's
        // attractions shouldn't skip leg 2's too.
        if (!$this->mcAttractionStep && $this->flightTripType === 'multi_city' && $this->mcTo) {
            $this->mcAttractionStep    = true;
            $this->mcAttractionResults = [];
            $this->mcAttractionLoading = true;
            $this->searchMcAttractions();
            return;
        }

        $this->selectedMcAttractions = [];
        $this->mcAttractionStep      = false;
        $this->step = 6;
    }

    // Same toggle pattern as toggleVenue() — lets the traveler pick more
    // than one attraction. continueFromAttractions() advances the wizard.
    public function toggleAttraction(int $index): void
    {
        $source = $this->mcAttractionStep ? $this->mcAttractionResults : $this->attractionResults;
        $item   = $source[$index] ?? null;
        if ($item === null) {
            $this->attractionError = 'That attraction is no longer available. Please pick another.';
            return;
        }

        if (!$this->mcAttractionStep) {
            if (isset($this->selectedAttractions[$item['name']])) {
                unset($this->selectedAttractions[$item['name']]);
            } else {
                $this->selectedAttractions[$item['name']] = $item;
            }
        } else {
            if (isset($this->selectedMcAttractions[$item['name']])) {
                unset($this->selectedMcAttractions[$item['name']]);
            } else {
                $this->selectedMcAttractions[$item['name']] = $item;
            }
        }
    }

    public function continueFromAttractions(): void
    {
        if (!$this->mcAttractionStep) {
            $items = array_map(
                fn ($a) => [$a['name'] ?? 'Attraction', ($a['isFree'] ?? false) ? 'Free' : (currency_symbol() . number_format((int) preg_replace('/[^\d]/', '', $a['price'] ?? '0')))],
                array_values($this->selectedAttractions)
            );
            if ($this->maybeReturnToAiEdit('attractions', [
                'items' => $items,
                'cost'  => $this->selectedAttractionsCost(),
            ])) {
                return;
            }

            if ($this->flightTripType === 'multi_city' && $this->mcTo) {
                $this->mcAttractionStep    = true;
                $this->mcAttractionResults = [];
                $this->mcAttractionLoading = true;
                // Synchronous, same reasoning as selectAccommodation() above.
                $this->searchMcAttractions();
            } else {
                $this->step = 6;
            }
        } else {
            $this->step = 6;
        }
    }

    #[On('searchMcAttractions')]
    public function searchMcAttractions(): void
    {
        set_time_limit(120);
        $serp = new SerpApiService();
        try {
            $this->mcAttractionResults = $serp->searchAttractionsRaw($this->mcTo, $this->attractionType) ?? [];
            if (empty($this->mcAttractionResults)) {
                $serper = new SerperService();
                $this->mcAttractionResults = $serper->searchAttractions($this->mcTo) ?? [];
            }
        } catch (\Throwable) {
            try {
                $serper = new SerperService();
                $this->mcAttractionResults = $serper->searchAttractions($this->mcTo) ?? [];
            } catch (\Throwable) {
                $this->mcAttractionResults = [];
            }
        }
        if (empty($this->mcAttractionResults)) {
            $this->attractionError = 'We couldn\'t load attractions right now. Try searching again, or skip this step.';
        }
        $this->mcAttractionLoading = false;
    }


    public function backToFlights(): void
    {
        $this->step = 2;
    }

    // A traveler who reached this step via the AI Planner's per-card "Edit"
    // (accommodation/food/attractions) skipped straight past the earlier
    // steps — they were never searched, so the normal "Back to Flights" /
    // "Back to Accommodations" / etc. target would land on an empty or
    // stale results list. In that case, abandon the edit and return to the
    // AI Planner results screen instead (same destination "Back to Chat"
    // uses) — manual planning is completely untouched, since this only
    // triggers when ai_edit_section was actually set.
    public function backFromEdit(int $normalStep): mixed
    {
        if (session('ai_edit_section')) {
            session()->forget(['ai_edit_return', 'ai_edit_section']);
            return $this->redirect(route('trips.plan.ai'), navigate: true);
        }

        if ($normalStep === 1) {
            $this->step1VisitToken++;
        }
        $this->step = $normalStep;
        return null;
    }

    // Belt-and-braces server-side guard: the client-side calendars already
    // disable out-of-range days, but $wire.set() calls race over the network,
    // so also reconcile here whenever either date lands on the server —
    // whichever one just changed wins, and the other is cleared if it would
    // now put start after end.
    public function updatedStartDate(): void
    {
        if ($this->startDate && $this->endDate && $this->startDate > $this->endDate) {
            $this->endDate = '';
        }
    }

    public function updatedEndDate(): void
    {
        if ($this->startDate && $this->endDate && $this->endDate < $this->startDate) {
            $this->startDate = '';
        }
    }

    public function updatedMcStartDate(): void
    {
        if ($this->mcStartDate && $this->mcEndDate && $this->mcStartDate > $this->mcEndDate) {
            $this->mcEndDate = '';
        }
    }

    public function updatedMcEndDate(): void
    {
        if ($this->mcStartDate && $this->mcEndDate && $this->mcEndDate < $this->mcStartDate) {
            $this->mcStartDate = '';
        }
    }

    public function updatedFlightTripType(): void
    {
        if ($this->flightTripType !== 'multi_city') {
            $this->mcSearched   = false;
            $this->mcTo         = '';
            $this->mcStartDate  = '';
            $this->mcEndDate    = '';
            $this->resetMultiCityLegState();
        }
    }

    // Fires whenever the second leg's destination changes. Without this, a
    // user who picks a flight/hotel/venue/attraction for "Cebu", then
    // changes the leg-2 destination to "Davao" without ever leaving
    // multi-city mode, would keep Cebu's stale results and selections
    // sitting in state (no auto-clear existed for this specific case).
    public function updatedMcTo(): void
    {
        $this->resetMultiCityLegState();
    }

    private function resetMultiCityLegState(): void
    {
        // flights
        $this->mcFlightResults  = [];
        $this->mcFlightStep     = false;
        $this->mcFlightLoading  = false;
        $this->selectedMcFlight = null;
        // accommodation
        $this->mcHotelStep      = false;
        $this->mcHotelResults   = [];
        $this->mcHotelLoading   = false;
        $this->selectedMcHotel  = null;
        // food & dining
        $this->mcVenueStep      = false;
        $this->mcVenueResults   = [];
        $this->mcVenueLoading   = false;
        $this->selectedMcVenues = [];
        // attractions
        $this->mcAttractionStep      = false;
        $this->mcAttractionResults   = [];
        $this->mcAttractionLoading   = false;
        $this->selectedMcAttractions = [];
    }

    public function swapCities(): void
    {
        [$this->manualFrom, $this->manualTo] = [$this->manualTo, $this->manualFrom];
    }

    // The emergency fund input has no currency awareness of its own — a
    // traveler whose trip came from TARA in a foreign currency (tripCurrency,
    // carried over via the AI handoff) types a raw number meaning THAT
    // currency, not pesos. Convert it here, once, before it's used anywhere
    // else in this component (subtracted from the budget, added to totals,
    // saved to the trip) — all of which already assume pesos throughout.
    public function updatedEmergency(): void
    {
        $this->emergencyConverted = false;
    }

    public function tripDisplayAmount(int|float $pesoAmount): string
    {
        if ($this->tripCurrency === '' || $this->tripCurrency === 'PHP') {
            return '₱' . number_format($pesoAmount);
        }

        $rate = (new CurrencyConverterService())->rateToPhp($this->tripCurrency);
        if ($rate === null) {
            return '₱' . number_format($pesoAmount);
        }

        $symbol = PlaceCatalog::CURRENCY_SYMBOLS[$this->tripCurrency] ?? '₱';
        return $symbol . number_format($pesoAmount / $rate);
    }

    // Looked up by destination NAME rather than relying on any prior modal
    // interaction, so it gives the same answer whether it's called from the
    // wizard's own currency-conversion modal or independently at save time
    // in saveItinerary() — the destination's currency is a fact about the
    // trip, not something tied to a UI choice made earlier in the flow.
    private function destinationCurrencyFor(string $destination): ?string
    {
        $code = PlaceCatalog::DESTINATION_CURRENCIES[strtolower(trim($destination))] ?? null;
        return ($code !== null && $code !== 'PHP') ? $code : null;
    }

    private function convertPesosToCurrency(?string $code, float $pesoAmount): ?float
    {
        if ($code === null) return null;

        $rate = (new CurrencyConverterService())->rateToPhp($code);
        return $rate !== null ? round($pesoAmount / $rate, 2) : null;
    }

    public function confirmEmergencyFund(): void
    {
        // No zero-check here on purpose: a fund of 0 is a legitimate value
        // server-side (see TripPlannerWizardTest::test_confirming_emergency_
        // fund_skips_the_modal_for_a_domestic_destination). The empty-FIELD
        // case the traveler sees is gated in Alpine's submit() on step 6,
        // which shakes the field and never calls this method.

        if (!$this->emergencyConverted && $this->tripCurrency !== '' && $this->tripCurrency !== 'PHP' && $this->emergency > 0) {
            $rate = (new CurrencyConverterService())->rateToPhp($this->tripCurrency);
            if ($rate === null) {
                $this->emergencyError = "I couldn't fetch the live exchange rate just now — please try again in a moment.";
                return;
            }
            $this->emergency = round($this->emergency * $rate, 2);
            $this->emergencyConverted = true;
        }

        // The emergency fund is money set aside SEPARATELY from the trip
        // budget (added on top of it), not carved out of it — so unlike
        // the trip budget itself, there's no ceiling it needs to stay
        // under here.
        $this->emergencyError = '';

        $destCode = $this->destinationCurrencyFor($this->manualTo);
        if ($destCode !== null) {
            $this->destinationCurrencyCode  = $destCode;
            $this->showCurrencyConvertModal = true;
            return;
        }

        $this->step = 7;
    }

    // "Yes" — the peso budget is the only figure ever used to actually plan
    // or save the trip; this only adds a converted amount to SHOW alongside
    // it, the same "real value stays in pesos, other currencies are just
    // display" rule used everywhere else in this app.
    public function acceptCurrencyConversion(): void
    {
        $budget = (int) preg_replace('/[^\d]/', '', $this->manualBudgetMax ?: $this->manualBudgetMin);

        $converted = $this->convertPesosToCurrency($this->destinationCurrencyCode, (float) $budget);

        if ($converted === null) {
            $this->currencyConvertError = "I couldn't fetch the live exchange rate just now — please try again in a moment.";
            return;
        }

        $this->convertedBudget      = $converted;
        $this->currencyConvertError = '';

        $this->showCurrencyConvertModal = false;
        $this->step = 7;
    }

    public function declineCurrencyConversion(): void
    {
        $this->convertedBudget          = null;
        $this->currencyConvertError     = '';
        $this->showCurrencyConvertModal = false;
        $this->step = 7;
    }

    // Gemini → Groq → Cerebras → Mistral → OpenRouter — shared by every
    // place that asks the AI for one itinerary suggestion. Each is tried
    // only if the previous one failed or returned nothing (dead key, rate
    // limit, exhausted quota, no billing, etc.), so one provider being
    // down doesn't stall generation as long as another still has
    // capacity. As of the last check: Gemini's key returns 401
    // UNAUTHENTICATED and Cerebras's account has no billing set up (402
    // on every model) — both fail fast and just fall through; Groq and
    // Mistral currently have working keys.
    //
    // $deadline is a microtime(true) cutoff for this ENTIRE call (all
    // providers combined) — generateItinerary() calls this up to 5 times
    // in a row, and trying every provider at a fixed 30s timeout each, per
    // option, is how a single request used to blow past PHP's 120s
    // max_execution_time and fatal out. Once the deadline is hit we stop
    // trying further providers for this option and just report failure —
    // that option gets skipped same as any other provider failure.
    private function suggestItinerary(array $args, ?float $deadline = null): ?array
    {
        $deadline ??= microtime(true) + 60;
        // Neither leg of the trip has a hotel picked — ask the AI to
        // suggest one instead of leaving the itinerary with no lodging.
        $needsAccommodation = !$this->selectedHotel && !$this->selectedMcHotel;
        // Mistral/OpenRouter/Groq tried first — Gemini's project currently
        // has zero free-tier quota (429 on every call) and Cerebras's key
        // has no billing (402), so both are pushed to the end instead of
        // wasting a request+timeout on them before falling through to a
        // provider that actually works.
        foreach ([\App\Services\MistralService::class, \App\Services\OpenRouterService::class, \App\Services\GroqService::class, \App\Services\GeminiService::class, \App\Services\CerebrasService::class] as $serviceClass) {
            $remaining = $deadline - microtime(true);
            if ($remaining < 5) break;

            try {
                $result = (new $serviceClass())->suggestAdditionalItinerary(...$args, needsAccommodation: $needsAccommodation, timeout: (int) min(25, floor($remaining)));
            } catch (\Throwable) {
                $result = null;
            }
            if ($result) {
                return $this->applyDepartureCost($result);
            }
        }
        return null;
    }

    // The AI is instructed to add a "Head to Airport / Departure" entry with
    // cost 0. That entry only makes sense for a round trip (there's a return
    // flight to catch) — for a one-way trip there's no return leg, so drop
    // it entirely instead of showing a departure that never happens. For a
    // round trip, the leg is really half of what was paid for the ticket
    // (the return half), not free.
    private function applyDepartureCost(array $result): array
    {
        $flight  = $this->selectedFlight ?: $this->selectedMcFlight;
        $isRound = $flight && strtolower($flight['type'] ?? '') === 'round trip';
        $departureCost = $isRound ? (float) ($flight['price'] ?? 0) / 2 : 0;

        foreach ($result['days'] ?? [] as $di => $day) {
            $activities = [];
            foreach ($day['activities'] ?? [] as $act) {
                $isDeparture = ($act['type'] ?? '') === 'transport' && str_contains($act['title'] ?? '', 'Head to Airport');
                if ($isDeparture) {
                    if (!$isRound) continue; // one-way trip: no return flight to catch
                    $act['cost'] = $departureCost;
                }
                $activities[] = $act;
            }
            $result['days'][$di]['activities'] = $activities;
        }

        return $result;
    }

    public function generateItinerary(): void
    {
        $this->itineraryLeg    = 1;
        $this->aiItineraryLeg1 = null;
        $this->step             = 8;

        $dest = trim($this->manualTo ?: $this->mcTo ?: 'Unknown');
        $this->runItineraryGeneration(
            $dest,
            $this->startDate ?: now()->toDateString(),
            $this->endDate   ?: now()->toDateString(),
        );
    }

    // Multi-city: called when the traveler continues past leg 1's itinerary
    // options. Stashes leg 1's chosen option and generates fresh options for
    // leg 2's destination/dates instead of jumping straight to the summary.
    public function generateItineraryLeg2(): void
    {
        $this->aiItineraryLeg1        = $this->aiItinerary;
        $this->aiItineraryLeg1Options = $this->aiItineraryOptions;
        $this->itineraryLeg           = 2;

        $this->runItineraryGeneration(
            trim($this->mcTo ?: 'Unknown'),
            $this->mcStartDate ?: now()->toDateString(),
            $this->mcEndDate   ?: now()->toDateString(),
        );
    }

    // Bound to Step 8's "Save Itinerary" button. For a multi-city trip still
    // showing leg 1's options, this generates leg 2's options instead of
    // proceeding — the actual summary/save only happens once leg 2 (or the
    // only leg, for non-multi-city trips) has been reviewed.
    public function continueItinerary(): void
    {
        if ($this->flightTripType === 'multi_city' && $this->mcTo && $this->itineraryLeg === 1) {
            $this->generateItineraryLeg2();
            return;
        }
        $this->goToSummary();
    }

    // Bound to Step 8's "Back to Leg 1 Itinerary" button — restores leg 1's
    // already-generated options instead of regenerating them from scratch.
    public function backToLeg1Itinerary(): void
    {
        $this->itineraryLeg           = 1;
        $this->aiItineraryOptions     = $this->aiItineraryLeg1Options;
        $this->aiItinerary            = $this->aiItineraryLeg1;
        $this->selectedItineraryIndex = 0;
    }

    // Bound to "Generate Other Options" / "Try Again" on Step 8 — regenerates
    // options for whichever leg is currently being shown, without resetting
    // back to leg 1's destination/dates the way generateItinerary() does.
    public function regenerateItineraryOptions(): void
    {
        if ($this->itineraryLeg === 2 && $this->mcTo) {
            $this->runItineraryGeneration(
                trim($this->mcTo),
                $this->mcStartDate ?: now()->toDateString(),
                $this->mcEndDate   ?: now()->toDateString(),
            );
            return;
        }
        $dest = trim($this->manualTo ?: $this->mcTo ?: 'Unknown');
        $this->runItineraryGeneration(
            $dest,
            $this->startDate ?: now()->toDateString(),
            $this->endDate   ?: now()->toDateString(),
        );
    }

    private function runItineraryGeneration(string $dest, string $tripStart, string $tripEnd): void
    {
        // Up to 3 options below, each capped at its own ~32s slice of a
        // ~100s overall deadline (see $overallDeadline further down) — a
        // fatal "Maximum execution time exceeded" isn't catchable, so this
        // needs its own bump past PHP's default 30s the same way
        // processAiTrip() (in Llm.php) already does for its own AI chain.
        // Shared by generateItinerary(), generateItineraryLeg2(), and
        // regenerateItineraryOptions() — all three funnel through here.
        set_time_limit(150);

        $this->aiLoading          = true;
        $this->aiItinerary        = null;
        $this->aiItineraryOptions = [];
        $this->selectedItineraryIndex = 0;

        $profile       = auth()->user()?->userProfile;
        $profileBudget = (int) ($profile?->daily_budget ?? 0);
        $interests     = array_merge($profile?->interests ?? [], $profile?->sub_interests ?? []);

        $budMin  = $profileBudget > 0 ? $profileBudget : (int) preg_replace('/[^\d]/', '', $this->manualBudgetMin);
        // The emergency fund is set aside SEPARATELY from this budget (added
        // on top of it at the end), not carved out of it — so the full
        // entered amount stays available for actually planning the trip.
        $budMax  = max(500, (int) preg_replace('/[^\d]/', '', $this->manualBudgetMax ?: $this->manualBudgetMin));
        if ($budMin >= $budMax) $budMin = (int) round($budMax * 0.8);

        // Calculate how much the traveler already spent on selections
        $selectionCost = 0;
        $selectionCost += (int) ($this->selectedFlight['price']    ?? 0);
        $selectionCost += (int) ($this->selectedMcFlight['price']  ?? 0);
        $selectionCost += (int) ($this->selectedHotel['total']     ?? 0);
        $selectionCost += (int) ($this->selectedMcHotel['total']   ?? 0);
        $selectionCost += $this->selectedVenuesCost();
        $selectionCost += $this->selectedAttractionsCost();

        // AI budget = remaining after selections; ensure AI fills up to at least budMin
        $aiBudMin = max(0, $budMin - $selectionCost);
        $aiBudMax = max($aiBudMin + 500, $budMax - $selectionCost);

        $alreadySelected = array_filter(array_merge([
            $this->selectedFlight['airline']   ?? null,
            $this->selectedHotel['name']       ?? null,
            $this->selectedMcFlight['airline'] ?? null,
            $this->selectedMcHotel['name']     ?? null,
        ], array_column($this->selectedVenuesFlat(), 'name'), array_column($this->selectedAttractionsFlat(), 'name')));

        $departTime = $this->selectedFlight['depart'] ?? $this->selectedMcFlight['depart'] ?? '';

        // Generate a small batch of distinct options up front instead of one
        // itinerary — each call gets its own "flavor" constraint so they
        // read as genuinely different choices rather than near-duplicates.
        // Firing them all back-to-back regularly trips Groq's free-tier
        // rate limit and starves the option grid down to fewer successful
        // results, so space them out a little.
        //
        // Each option can try up to 5 providers (see suggestItinerary()).
        // Capping every option to a fixed slice of an overall ~100s budget
        // (still under PHP's 120s max_execution_time) gives flaky providers
        // (connection resets/timeouts on Groq/Mistral) more room to recover
        // via suggestItinerary()'s per-provider timeout before giving up.
        $overallDeadline = microtime(true) + 100;
        foreach (self::ITINERARY_OPTION_CONSTRAINTS as $i => $option) {
            if (microtime(true) >= $overallDeadline) break;
            if ($i > 0) usleep(600_000);
            $args = [
                $dest,
                $tripStart,
                $tripEnd,
                $aiBudMin,
                $aiBudMax,
                $profileBudget,
                $interests,
                array_values($alreadySelected),
                $option['prompt'],
                $departTime,
            ];
            $optionDeadline = min($overallDeadline, microtime(true) + 32);
            $result = $this->suggestItinerary($args, $optionDeadline);
            if ($result) {
                $result['_optionLabel'] = $option['label'];
                $this->aiItineraryOptions[] = $result;
            }
        }

        $this->aiItinerary = $this->aiItineraryOptions[0] ?? null;
        $this->aiLoading    = false;
    }

    // Switch which generated option is "active" — the one saveItinerary()
    // and the cost summary read from.
    public function selectItineraryOption(int $index): void
    {
        if (!isset($this->aiItineraryOptions[$index])) return;
        $this->selectedItineraryIndex = $index;
        $this->aiItinerary            = $this->aiItineraryOptions[$index];
    }

    public function openBudgetAdjust(): void
    {
        $this->adjustBudgetMin  = $this->manualBudgetMin;
        $this->adjustBudgetMax  = $this->manualBudgetMax ?: $this->manualBudgetMin;
        $this->showBudgetAdjust = true;
    }

    public function applyBudgetAdjust(): void
    {
        if ($this->adjustBudgetMin) $this->manualBudgetMin = preg_replace('/[^\d]/', '', $this->adjustBudgetMin);
        if ($this->adjustBudgetMax) $this->manualBudgetMax = preg_replace('/[^\d]/', '', $this->adjustBudgetMax);
        $this->showBudgetAdjust = false;
        $this->regenerateItinerary();
    }

    public function regenerateItinerary(): void
    {
        $this->showBudgetAdjust = false;

        $profile       = auth()->user()?->userProfile;
        $profileBudget = (int) ($profile?->daily_budget ?? 0);
        $interests     = array_merge($profile?->interests ?? [], $profile?->sub_interests ?? []);

        // Min = profile preferred budget; Max = trip total entered in wizard.
        // Same as generateItinerary() — the emergency fund is separate, not
        // subtracted from what's available to actually plan the trip with.
        $budMin = $profileBudget > 0 ? $profileBudget : (int) preg_replace('/[^\d]/', '', $this->manualBudgetMin);
        $budMax = max(500, (int) preg_replace('/[^\d]/', '', $this->manualBudgetMax ?: $this->manualBudgetMin));
        if ($budMin >= $budMax) $budMin = (int) round($budMax * 0.8);
        $dest   = trim($this->manualTo ?: $this->mcTo ?: 'Unknown');

        // Subtract traveler selection costs to get remaining AI budget
        $selectionCost  = 0;
        $selectionCost += (int) ($this->selectedFlight['price']     ?? 0);
        $selectionCost += (int) ($this->selectedMcFlight['price']   ?? 0);
        $selectionCost += (int) ($this->selectedHotel['total']      ?? 0);
        $selectionCost += (int) ($this->selectedMcHotel['total']    ?? 0);
        $selectionCost += $this->selectedVenuesCost();
        $selectionCost += $this->selectedAttractionsCost();

        $aiBudMin = max(0, $budMin - $selectionCost);
        $aiBudMax = max($aiBudMin + 500, $budMax - $selectionCost);

        $alreadySelected = array_values(array_filter(array_merge([
            $this->selectedFlight['airline']   ?? null,
            $this->selectedHotel['name']       ?? null,
            $this->selectedMcFlight['airline'] ?? null,
            $this->selectedMcHotel['name']     ?? null,
        ], array_column($this->selectedVenuesFlat(), 'name'), array_column($this->selectedAttractionsFlat(), 'name'))));

        // Check over/under budget against remaining AI budget
        $currentCost = 0;
        foreach ($this->aiItinerary['days'] ?? [] as $day) {
            foreach ($day['activities'] ?? [] as $act) {
                if (isset($act['cost']) && is_numeric($act['cost'])) $currentCost += (float) $act['cost'];
            }
        }
        $isOverBudget  = $aiBudMax > 0 && $currentCost > $aiBudMax;
        $isUnderBudget = $aiBudMin > 0 && $currentCost < $aiBudMin;

        $this->aiItinerary = null;
        $this->aiLoading   = true;

        if ($isOverBudget) {
            $constraint = "The previous itinerary exceeded the remaining activity budget. You MUST suggest cheaper activities. Total cost MUST be between ₱{$aiBudMin} and ₱{$aiBudMax}. Do NOT exceed ₱{$aiBudMax}.";
        } elseif ($isUnderBudget) {
            $constraint = "The previous itinerary was under budget. Suggest higher-quality experiences, premium dining, guided tours, and notable attractions to bring the total closer to ₱{$aiBudMax}. Total MUST be between ₱{$aiBudMin} and ₱{$aiBudMax}.";
        } else {
            $varieties  = ['Focus on hidden gems.', 'Mix cultural, culinary, and outdoor experiences.', 'Focus on authentic local experiences.', 'Include wellness and evening entertainment.', 'Prioritize unique local tours.'];
            $constraint = "Keep total cost between ₱{$aiBudMin} and ₱{$aiBudMax}. " . $varieties[array_rand($varieties)];
        }

        $departTime = $this->selectedFlight['depart'] ?? $this->selectedMcFlight['depart'] ?? '';

        $args = [
            $dest,
            $this->startDate ?: now()->toDateString(),
            $this->aiEndDate(),
            $aiBudMin, $aiBudMax, $profileBudget,
            $interests,
            $alreadySelected,
            $constraint,
            $departTime,
        ];

        $result = $this->suggestItinerary($args);

        // Keep the options list in sync — this replaces whichever option was
        // active, so the other generated choices are still there to pick if
        // the regenerated one isn't better. Preserve its label since this
        // constraint isn't one of the labeled ITINERARY_OPTION_CONSTRAINTS.
        if ($result) {
            $prevLabel = $this->aiItineraryOptions[$this->selectedItineraryIndex]['_optionLabel'] ?? null;
            if ($prevLabel) $result['_optionLabel'] = $prevLabel;
            $this->aiItineraryOptions[$this->selectedItineraryIndex] = $result;
        }
        $this->aiItinerary = $result;
        $this->aiLoading   = false;
    }

    public function goToSummary(): void
    {
        $this->step = 9;
    }

    // Lets the traveler revisit their food/attraction picks from the itinerary
    // preview without losing what they've already selected (search results and
    // selections are just left as-is — only the visible step changes).
    public function backToAttractions(): void
    {
        $this->step = 5;
    }

    // Buckets an AI-generated itinerary's activities by type into cost
    // totals per category — shared by the step-9 preview, the PDF export,
    // and the saved summary_data, so an AI-suggested hotel/food/attraction
    // lands in that category's total instead of everything but transport
    // getting dumped into "Attractions".
    public function categorizeAiCost(array $days): array
    {
        $totals = ['accommodation' => 0.0, 'food' => 0.0, 'transport' => 0.0, 'attraction' => 0.0];
        foreach ($days as $day) {
            foreach ($day['activities'] ?? [] as $act) {
                if (!isset($act['cost']) || !is_numeric($act['cost'])) continue;
                $cost = (float) $act['cost'];
                $type = strtolower($act['type'] ?? '');
                if ($type === 'accommodation') {
                    $totals['accommodation'] += $cost;
                } elseif (in_array($type, ['food', 'restaurant'], true)) {
                    $totals['food'] += $cost;
                } elseif ($type === 'transport') {
                    $totals['transport'] += $cost;
                } else {
                    $totals['attraction'] += $cost;
                }
            }
        }
        return $totals;
    }

    // Renders the same route/dates/cost-breakdown/itinerary/selection-summary
    // shown on the step-9 preview into a PDF — the trip isn't saved to the
    // DB yet at this point, so this reads straight off component state
    // rather than a Trip model.
    public function downloadPdf()
    {
        $dest = trim($this->manualTo ?: $this->mcTo ?: 'Unknown');
        $from = trim($this->manualFrom ?: 'Manila');
        $sd   = $this->startDate ?: now()->toDateString();
        $ed   = $this->endDate   ?: now()->toDateString();

        $pdfIsMultiCity = $this->flightTripType === 'multi_city' && $this->mcTo;

        // Leg 1's AI days are stashed in $aiItineraryLeg1 once leg 2's
        // options are generated (see generateItineraryLeg2()); $aiItinerary
        // itself then holds leg 2's. For a single-city trip $aiItinerary is
        // the only itinerary there is.
        $leg1AiDays = $pdfIsMultiCity ? ($this->aiItineraryLeg1['days'] ?? []) : ($this->aiItinerary['days'] ?? []);
        $leg2AiDays = $pdfIsMultiCity ? ($this->aiItinerary['days'] ?? []) : [];
        $leg1AiTotals = $this->categorizeAiCost($leg1AiDays);
        $leg2AiTotals = $this->categorizeAiCost($leg2AiDays);
        $aiTotals = ['accommodation' => 0.0, 'food' => 0.0, 'transport' => 0.0, 'attraction' => 0.0];
        foreach ($aiTotals as $k => $v) $aiTotals[$k] = $leg1AiTotals[$k] + $leg2AiTotals[$k];
        $aiCost = array_sum($aiTotals);

        // Base (own booking only, no AI activities mixed in) costs per leg —
        // used for each Selection Summary line item so a pick's displayed
        // price is just its own price.
        $flightBase1 = (float) ($this->selectedFlight['price']   ?? 0);
        $flightBase2 = (float) ($this->selectedMcFlight['price'] ?? 0);
        $hotelBase1  = (float) ($this->selectedHotel['total']    ?? 0);
        $hotelBase2  = (float) ($this->selectedMcHotel['total']  ?? 0);
        // Multi-select: a leg can now hold several venues/attractions, so
        // each leg's base cost is a sum, not one item's price.
        $venueBase1  = (float) array_sum(array_map(fn($v) => $v['priceMax'] ?? $v['priceMin'] ?? 0, $this->selectedVenues));
        $venueBase2  = (float) array_sum(array_map(fn($v) => $v['priceMax'] ?? $v['priceMin'] ?? 0, $this->selectedMcVenues));
        $attrBase1   = array_sum(array_map(fn($a) => ($a['isFree'] ?? false) ? 0 : (int) preg_replace('/[^\d]/', '', $a['price'] ?? '0'), $this->selectedAttractions));
        $attrBase2   = array_sum(array_map(fn($a) => ($a['isFree'] ?? false) ? 0 : (int) preg_replace('/[^\d]/', '', $a['price'] ?? '0'), $this->selectedMcAttractions));

        $flightCost = $flightBase1 + $flightBase2;
        $hotelCost  = $hotelBase1  + $hotelBase2;
        $venueCost  = $venueBase1  + $venueBase2;
        $attrCost   = $attrBase1   + $attrBase2;

        $emergency = (float) $this->emergency;
        $budget    = (int) preg_replace('/[^\d]/', '', $this->manualBudgetMax ?: $this->manualBudgetMin);
        $total     = $flightCost + $hotelCost + $venueCost + $attrCost + $aiCost + $emergency;

        $picks = [];
        if ($this->selectedFlight) $picks[] = ['label' => 'Flight',        'val' => trim(($this->selectedFlight['airline'] ?? '') . ' ' . ($this->selectedFlight['number'] ?? '')), 'cost' => $flightBase1];
        if ($this->selectedHotel)  $picks[] = ['label' => 'Accommodation', 'val' => $this->selectedHotel['name'] ?? 'Hotel', 'cost' => $hotelBase1];
        foreach ($this->selectedVenues as $v) {
            $picks[] = ['label' => 'Food & Dining', 'val' => $v['name'] ?? 'Restaurant', 'cost' => (float) ($v['priceMax'] ?? $v['priceMin'] ?? 0)];
        }
        foreach ($this->selectedAttractions as $a) {
            $picks[] = ['label' => 'Attraction', 'val' => $a['name'] ?? 'Attraction', 'cost' => ($a['isFree'] ?? false) ? 0 : (int) preg_replace('/[^\d]/', '', $a['price'] ?? '0')];
        }

        $picksLeg2 = [];
        if ($pdfIsMultiCity) {
            if ($this->selectedMcFlight) $picksLeg2[] = ['label' => 'Flight',        'val' => trim(($this->selectedMcFlight['airline'] ?? '') . ' ' . ($this->selectedMcFlight['number'] ?? '')), 'cost' => $flightBase2];
            if ($this->selectedMcHotel)  $picksLeg2[] = ['label' => 'Accommodation', 'val' => $this->selectedMcHotel['name'] ?? 'Hotel', 'cost' => $hotelBase2];
            foreach ($this->selectedMcVenues as $v) {
                $picksLeg2[] = ['label' => 'Food & Dining', 'val' => $v['name'] ?? 'Restaurant', 'cost' => (float) ($v['priceMax'] ?? $v['priceMin'] ?? 0)];
            }
            foreach ($this->selectedMcAttractions as $a) {
                $picksLeg2[] = ['label' => 'Attraction', 'val' => $a['name'] ?? 'Attraction', 'cost' => ($a['isFree'] ?? false) ? 0 : (int) preg_replace('/[^\d]/', '', $a['price'] ?? '0')];
            }
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('traveler.reports.trip-plan-pdf', [
            'dest'          => $dest,
            'from'          => $from,
            'startDate'     => $sd,
            'endDate'       => $ed,
            'isMultiCity'   => $pdfIsMultiCity,
            'leg2Dest'      => trim($this->mcTo ?: ''),
            'leg2StartDate' => $this->mcStartDate ?: null,
            'leg2EndDate'   => $this->mcEndDate   ?: null,
            'picks'         => $picks,
            'picksLeg2'     => $picksLeg2,
            'aiDays'        => $leg1AiDays,
            'aiDaysLeg2'    => $leg2AiDays,
            'emergency'     => $emergency,
            'budget'        => $budget,
            'total'         => $total,
            'flightCost'    => $flightCost + $aiTotals['transport'],
            'hotelCost'     => $hotelCost + $aiTotals['accommodation'],
            'venueCost'     => $venueCost + $aiTotals['food'],
            'attrCost'      => $attrCost + $aiTotals['attraction'],
        ]);
        $pdf->setPaper('a4', 'portrait');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'Trip-Summary-' . str_replace(' ', '-', $dest) . '.pdf'
        );
    }

    // The step-9 summary's "Edit" links jump back to an earlier step to
    // change a selection. Just doing $set('step', N) left the previously
    // generated AI itinerary (step 8) sitting in state, priced against the
    // selection that's about to change — a user could edit their flight,
    // then click straight back to the summary via browser/step navigation
    // without regenerating, and see costs computed against the old pick.
    // Clearing it forces a regenerate before the summary can be trusted again.
    public function editFromSummary(int $step): void
    {
        $this->step        = $step;
        $this->aiItinerary = null;
    }

    public function saveItinerary(): void
    {
        // Guard against a fast double-click creating two Trip rows — the
        // client-side wire:loading.attr="disabled" alone doesn't prevent a
        // second request that lands before the first response comes back.
        if ($this->isSaving) return;
        $this->isSaving = true;

        $rawBudget = $this->manualBudgetMax ?: $this->manualBudgetMin;
        $budget    = (float) preg_replace('/[^\d.]/', '', $rawBudget);
        $tripStart = $this->startDate ?: now()->toDateString();
        $tripEnd   = $this->endDate   ?: now()->toDateString();

        // Calculate total cost for saved trips display. Both legs are summed
        // (not just the first one found) so a multi-city trip's second leg
        // isn't silently dropped from the total.
        $flightCost = (int) ($this->selectedFlight['price'] ?? 0) + (int) ($this->selectedMcFlight['price'] ?? 0);
        $hotelCost  = (int) ($this->selectedHotel['total']  ?? 0) + (int) ($this->selectedMcHotel['total']  ?? 0);
        // selectedVenuesCost()/selectedAttractionsCost() already sum across
        // both legs (via selectedVenuesFlat()/selectedAttractionsFlat()) —
        // multi-select never had a single "the one item" to silently drop.
        $venueCost = $this->selectedVenuesCost();
        $attrCost  = $this->selectedAttractionsCost();
        // AI-suggested activities are bucketed by type so the summary can
        // show how much of "Accommodation" / "Food & Dining" / "Attractions"
        // came from the traveler's own pick vs. the AI-suggested itinerary.
        // For multi-city trips, leg 1's options were generated and stashed
        // first (see generateItineraryLeg2()) before leg 2's were generated
        // into $this->aiItinerary — combine both so leg 1 isn't dropped.
        $isMultiCitySave = $this->flightTripType === 'multi_city' && $this->mcTo;
        $leg1AiDays = $isMultiCitySave ? ($this->aiItineraryLeg1['days'] ?? []) : ($this->aiItinerary['days'] ?? []);
        $leg2AiDays = $isMultiCitySave ? ($this->aiItinerary['days'] ?? []) : [];
        $aiDays = array_merge($leg1AiDays, $leg2AiDays);
        $aiTotals = $this->categorizeAiCost($aiDays);
        $aiAccommodationCost = $aiTotals['accommodation'];
        $aiFoodCost          = $aiTotals['food'];
        $aiAttrCost          = $aiTotals['attraction'];
        $aiTransportCost     = $aiTotals['transport'];
        $aiFoodCount = 0; $aiAttrCount = 0;
        foreach ($aiDays as $day) {
            foreach ($day['activities'] ?? [] as $act) {
                $type = strtolower($act['type'] ?? '');
                if (in_array($type, ['food', 'restaurant'], true)) {
                    $aiFoodCount++;
                } elseif ($type !== 'transport' && $type !== 'accommodation') {
                    $aiAttrCount++;
                }
            }
        }
        $aiCost      = array_sum($aiTotals);

        $customCost = 0;
        foreach ($this->customActivities as $ca) { $customCost += (float) ($ca['cost'] ?? 0); }

        $totalCost = $flightCost + $hotelCost + $venueCost + $attrCost + $aiCost + $customCost + (float) ($this->emergency ?? 0);

        $selectedAttractionsList = $this->selectedAttractionsFlat();
        $selectedVenuesList      = $this->selectedVenuesFlat();

        $coverImage  = $this->selectedHotel['image']   ?? $this->selectedMcHotel['image']
                    ?? ($selectedAttractionsList[0]['image'] ?? null) ?? null;

        // Build summary for modal display
        $fromCode = $this->selectedFlight['dep_id'] ?? $this->selectedMcFlight['dep_id'] ?? 'MNL';
        $toCode   = $this->selectedFlight['arr_id'] ?? $this->selectedMcFlight['arr_id'] ?? '';
        $airline  = $this->selectedFlight['airline'] ?? $this->selectedMcFlight['airline'] ?? 'Flight';
        $flightDetail = $airline . ' · Round-trip flight (' . $fromCode . ' - ' . $toCode . ' | ' . $toCode . ' - ' . $fromCode . ')';
        if ($this->selectedFlight && $this->selectedMcFlight) {
            $flightDetail .= ' + ' . ($this->selectedMcFlight['airline'] ?? 'Flight') . ' (' . ($this->selectedMcFlight['dep_id'] ?? '') . ' - ' . ($this->selectedMcFlight['arr_id'] ?? '') . ')';
        }

        $nights      = $this->selectedHotel ? (int)($this->selectedHotel['nights'] ?? 1) : (int)($this->selectedMcHotel['nights'] ?? 1);
        $hotelName   = $this->selectedHotel['name'] ?? $this->selectedMcHotel['name'] ?? null;
        $hotelDetail = $hotelName ? $nights . ' night' . ($nights !== 1 ? 's' : '') . ' at ' . $hotelName : null;
        if ($this->selectedHotel && $this->selectedMcHotel) {
            $mcNights = (int) ($this->selectedMcHotel['nights'] ?? 1);
            $hotelDetail .= ' + ' . $mcNights . ' night' . ($mcNights !== 1 ? 's' : '') . ' at ' . ($this->selectedMcHotel['name'] ?? 'Hotel');
        }

        $venueNames  = array_column($selectedVenuesList, 'name');
        $venueDetail = $venueNames ? implode(', ', $venueNames) : null;

        $attrNames  = array_column($selectedAttractionsList, 'name');
        $attrDetail = $attrNames ? implode(', ', $attrNames) : null;

        // Neither the traveler nor a plain hotel search filled in
        // accommodation — fall back to the AI-suggested one so the summary
        // isn't left showing an empty ₱0 lodging line.
        if (!$hotelDetail) {
            foreach ($aiDays as $day) {
                $aiHotel = collect($day['activities'] ?? [])->first(fn($a) => strtolower($a['type'] ?? '') === 'accommodation');
                if ($aiHotel) { $hotelDetail = $aiHotel['title'] ?? 'AI-suggested stay'; break; }
            }
        }

        $summaryData = [
            'transportation' => ['detail' => $flightDetail,                        'cost' => $flightCost + $aiTransportCost],
            'accommodation'  => ['detail' => $hotelDetail,                         'cost' => $hotelCost + $aiAccommodationCost],
            'food'           => ['detail' => $venueDetail,                         'cost' => $venueCost + $aiFoodCost, 'extra' => $aiFoodCount],
            'attractions'    => ['detail' => $attrDetail,                          'cost' => $attrCost + $aiAttrCost + $customCost, 'extra' => $aiAttrCount],
            'emergency_fund' => ['detail' => 'Safety buffer for unexpected costs', 'cost' => (int)($this->emergency ?? 0)],
        ];

        $isMultiCitySaved = $this->flightTripType === 'multi_city' && $this->mcTo;
        $leg1DestName = trim($this->manualTo ?: $this->mcTo ?: 'Unknown');
        $leg2DestName = $isMultiCitySaved ? trim($this->mcTo) : null;

        // Computed fresh here rather than reused from the wizard's own
        // currency-conversion modal — the trip should remember its
        // destination currency regardless of whether that modal was shown,
        // accepted, or declined during planning (the modal only controls
        // what got SHOWN during the flow, not what the trip permanently is).
        $destCurrencyCode = $this->destinationCurrencyFor($leg1DestName);
        $destBudget       = $this->convertPesosToCurrency($destCurrencyCode, $budget);

        $trip = Trip::create([
            'user_id'          => auth()->id(),
            'destination'      => $leg1DestName,
            'destination_currency' => $destCurrencyCode,
            'destination_budget'   => $destBudget,
            // Multi-city trips are named after both destinations so the
            // trip is identifiable at a glance on the Saved Trips / Savings
            // Goals cards instead of only showing leg 1's city.
            'trip_name'        => $isMultiCitySaved ? "{$leg1DestName} & {$leg2DestName}" : null,
            'start_date'       => $tripStart,
            'end_date'         => $tripEnd,
            'budget_limit'     => $budget ?: 0,
            'travel_type'      => 'Solo',
            'num_travelers'    => 1,
            'total_cost'       => $totalCost,
            'cover_image'      => $coverImage,
            'summary_data'     => $summaryData,
            'origin'           => trim($this->manualFrom ?: 'Manila'),
            'origin_code'      => $fromCode,
            'destination_code' => $toCode,
            'is_multi_city'         => $isMultiCitySaved,
            'leg2_destination'      => $leg2DestName,
            'leg2_destination_code' => $isMultiCitySaved ? ($this->selectedMcFlight['arr_id'] ?? $this->resolveCode($this->mcTo)) : null,
            'leg2_start_date'       => $isMultiCitySaved ? ($this->mcStartDate ?: null) : null,
            'leg2_end_date'         => $isMultiCitySaved ? ($this->mcEndDate   ?: null) : null,
            // Raw traveler-made selections (not the AI-suggested itinerary)
            // — snapshotted so this trip can later be shared via a code/link
            // and copied into another traveler's own Saved Trips.
            'flight_selection'          => $this->selectedFlight ?: null,
            'hotel_selection'           => $this->selectedHotel ?: null,
            'venue_selection'           => $this->selectedVenues ?: null,
            'attraction_selection'      => $this->selectedAttractions ?: null,
            'leg2_flight_selection'     => $isMultiCitySaved ? ($this->selectedMcFlight ?: null) : null,
            'leg2_hotel_selection'      => $isMultiCitySaved ? ($this->selectedMcHotel ?: null) : null,
            'leg2_venue_selection'      => $isMultiCitySaved ? ($this->selectedMcVenues ?: null) : null,
            'leg2_attraction_selection' => $isMultiCitySaved ? ($this->selectedMcAttractions ?: null) : null,
        ]);

        if ($this->draftTripId && $this->draftTripId !== $trip->id) {
            Trip::where('id', $this->draftTripId)
                ->where('user_id', auth()->id())
                ->where('status', 'draft')
                ->delete();
            $this->draftTripId = null;
        }

        // Spread traveler selections across travel dates
        $day1Date  = \Carbon\Carbon::parse($tripStart);
        $lastDate  = \Carbon\Carbon::parse($tripEnd);
        $totalDays = max(1, (int) $day1Date->diffInDays($lastDate) + 1);
        $destLabel = trim($this->manualTo ?: '');
        $origLabel = trim($this->manualFrom ?: 'Manila');

        // Leg 1 (main destination) and leg 2 (multi-city second destination)
        // are kept as separate variables — merging them with `??` would
        // silently drop leg 2 whenever leg 1 also existed.
        $flight  = $this->selectedFlight;
        $hotel   = $this->selectedHotel;
        $flight2 = $this->selectedMcFlight;
        $hotel2  = $this->selectedMcHotel;
        $isRound = $flight && strtolower($flight['type'] ?? '') === 'round trip';
        $hasLeg2 = $flight2 || $hotel2 || !empty($this->selectedMcVenues) || !empty($this->selectedMcAttractions);

        // Day 1 — Arrival: flight in, hotel check-in
        if ($flight) {
            $trip->itinerary()->create([
                'title'          => ($flight['airline'] ?? 'Flight') . ' arrival to ' . $destLabel,
                'type'           => 'Flight',
                'start_datetime' => $day1Date->copy()->setTimeFromTimeString('10:00'),
                'end_datetime'   => $day1Date->copy()->setTimeFromTimeString('12:00'),
                'location'       => $destLabel,
                'notes'          => $flight['number'] ?? null,
            ]);
        }
        if ($hotel) {
            $trip->itinerary()->create([
                'title'          => 'Check-in at ' . ($hotel['name'] ?? 'Hotel'),
                'type'           => 'Hotel',
                'start_datetime' => $day1Date->copy()->setTimeFromTimeString('14:00'),
                'end_datetime'   => $day1Date->copy()->setTimeFromTimeString('15:00'),
                'location'       => $destLabel,
                'notes'          => null,
            ]);
        }

        // Leg 2's start day has to be known before leg 1's activity days are
        // laid out — for a multi-city trip, leg 1 only gets the days up to
        // that point, or its own (day-bucketed) activities could land on
        // the same dates leg 2's flight/hotel/activities get scheduled on
        // further below.
        $leg2Nights = $hotel ? max(1, (int) ($hotel['nights'] ?? 1)) : 1;
        $leg2Day    = $day1Date->copy()->addDays($leg2Nights);
        if ($leg2Day->gt($lastDate)) $leg2Day = $lastDate->copy();
        $leg1LastDate = $hasLeg2 ? $leg2Day : $lastDate;

        // Activity days — every day between arrival and departure (the last day
        // is reserved for checkout/return flight below, unless the trip is too
        // short to spare one, in which case activities share day 1 or the last day).
        $activityDays = [];
        if ($totalDays >= 3) {
            for ($d = $day1Date->copy()->addDay(); $d->lt($leg1LastDate); $d->addDay()) {
                $activityDays[] = $d->copy();
            }
        } elseif ($totalDays === 2) {
            $activityDays[] = $day1Date->copy()->addDay();
        } else {
            $activityDays[] = $day1Date->copy();
        }
        if (empty($activityDays)) $activityDays[] = $day1Date->copy();

        // Reuses the same 3-attractions/2-venues-per-day chunking pattern as
        // selectionDayBuckets() (used by the step 8 preview), but scoped to
        // one leg's own picks — selectionDayBuckets() itself combines both
        // legs, which only matters for a multi-city trip, where each leg
        // needs its own days rather than being interleaved onto the same ones.
        $dayBucketsFor = function (array $attractions, array $venues): array {
            $buckets = [];
            foreach (array_chunk(array_values($attractions), 3) as $i => $chunk) {
                $buckets[$i]['attractions'] = $chunk;
            }
            foreach (array_chunk(array_values($venues), 2) as $i => $chunk) {
                $buckets[$i]['venues'] = $chunk;
            }
            ksort($buckets);
            return $buckets;
        };

        // Spread every selected attraction/venue across those days instead of
        // cramming them all onto one — a handful per day, wrapping to the next
        // available day (extra items beyond the last day pile onto it rather
        // than being dropped).
        $attrTimes  = ['09:00', '11:30', '16:00'];
        $venueTimes = ['12:30', '19:00'];

        foreach ($dayBucketsFor($this->selectedAttractions, $this->selectedVenues) as $dayIdx => $bucket) {
            $day = $activityDays[$dayIdx] ?? end($activityDays);

            foreach ($bucket['attractions'] ?? [] as $slot => $attr) {
                $start = $attrTimes[$slot] ?? '16:00';
                $trip->itinerary()->create([
                    'title'          => 'Visit ' . ($attr['name'] ?? 'Attraction'),
                    'type'           => 'Activity',
                    'start_datetime' => $day->copy()->setTimeFromTimeString($start),
                    'end_datetime'   => $day->copy()->setTimeFromTimeString($start)->addHours(2),
                    'location'       => $destLabel,
                    'notes'          => null,
                ]);
            }

            foreach ($bucket['venues'] ?? [] as $slot => $venue) {
                $start = $venueTimes[$slot] ?? '20:30';
                $label = $slot === 0 ? 'Lunch at ' : 'Dinner at ';
                $trip->itinerary()->create([
                    'title'          => $label . ($venue['name'] ?? 'Restaurant'),
                    'type'           => 'Activity',
                    'start_datetime' => $day->copy()->setTimeFromTimeString($start),
                    'end_datetime'   => $day->copy()->setTimeFromTimeString($start)->addMinutes(90),
                    'location'       => $destLabel,
                    'notes'          => $venue['cuisine'] ?? null,
                ]);
            }
        }

        // Leg 2 (multi-city second destination) — flight arrival + hotel
        // check-in, scheduled on the day after leg 1's hotel stay ends
        // ($leg2Day, computed above alongside leg 1's own activity-day
        // bound), so it doesn't collide with leg 1's own activities above.
        $leg2Label = trim($this->mcTo ?: '');

        if ($hasLeg2) {
            if ($flight2) {
                $trip->itinerary()->create([
                    'title'          => ($flight2['airline'] ?? 'Flight') . ' arrival to ' . $leg2Label,
                    'type'           => 'Flight',
                    'start_datetime' => $leg2Day->copy()->setTimeFromTimeString('10:00'),
                    'end_datetime'   => $leg2Day->copy()->setTimeFromTimeString('12:00'),
                    'location'       => $leg2Label,
                    'notes'          => $flight2['number'] ?? null,
                ]);
            }
            if ($hotel2) {
                $trip->itinerary()->create([
                    'title'          => 'Check-in at ' . ($hotel2['name'] ?? 'Hotel'),
                    'type'           => 'Hotel',
                    'start_datetime' => $leg2Day->copy()->setTimeFromTimeString('14:00'),
                    'end_datetime'   => $leg2Day->copy()->setTimeFromTimeString('15:00'),
                    'location'       => $leg2Label,
                    'notes'          => null,
                ]);
            }

            foreach ($dayBucketsFor($this->selectedMcAttractions, $this->selectedMcVenues) as $dayIdx => $bucket) {
                $day = $leg2Day->copy()->addDays($dayIdx);
                if ($day->gt($lastDate)) $day = $lastDate->copy();

                foreach ($bucket['attractions'] ?? [] as $slot => $attr) {
                    $start = $attrTimes[$slot] ?? '16:00';
                    $trip->itinerary()->create([
                        'title'          => 'Visit ' . ($attr['name'] ?? 'Attraction'),
                        'type'           => 'Activity',
                        'start_datetime' => $day->copy()->setTimeFromTimeString($start),
                        'end_datetime'   => $day->copy()->setTimeFromTimeString($start)->addHours(2),
                        'location'       => $leg2Label,
                        'notes'          => null,
                    ]);
                }

                foreach ($bucket['venues'] ?? [] as $slot => $venue) {
                    $start = $venueTimes[$slot] ?? '20:30';
                    $label = $slot === 0 ? 'Lunch at ' : 'Dinner at ';
                    $trip->itinerary()->create([
                        'title'          => $label . ($venue['name'] ?? 'Restaurant'),
                        'type'           => 'Activity',
                        'start_datetime' => $day->copy()->setTimeFromTimeString($start),
                        'end_datetime'   => $day->copy()->setTimeFromTimeString($start)->addMinutes(90),
                        'location'       => $leg2Label,
                        'notes'          => $venue['cuisine'] ?? null,
                    ]);
                }
            }
        }

        // Last day — Hotel checkout + return flight
        if ($totalDays >= 2) {
            $checkoutHotel = $hotel2 ?: $hotel;
            $checkoutLabel = $hotel2 ? $leg2Label : $destLabel;
            if ($checkoutHotel) {
                $trip->itinerary()->create([
                    'title'          => 'Check-out from ' . ($checkoutHotel['name'] ?? 'Hotel'),
                    'type'           => 'Hotel',
                    'start_datetime' => $lastDate->copy()->setTimeFromTimeString('10:00'),
                    'end_datetime'   => $lastDate->copy()->setTimeFromTimeString('11:00'),
                    'location'       => $checkoutLabel,
                    'notes'          => null,
                ]);
            }
            if ($flight && $isRound) {
                $trip->itinerary()->create([
                    'title'          => ($flight['airline'] ?? 'Flight') . ' departure to ' . $origLabel,
                    'type'           => 'Flight',
                    'start_datetime' => $lastDate->copy()->setTimeFromTimeString('13:00'),
                    'end_datetime'   => $lastDate->copy()->setTimeFromTimeString('15:00'),
                    'location'       => $origLabel,
                    'notes'          => $flight['number'] ?? null,
                ]);
            }
        }

        // Save AI-generated itinerary days.
        // If selections took up activity days (and leg 2, if any), leg 1's
        // AI days start after that. Leg 1's AI days are capped so they never
        // spill into leg 2's date range; leg 2's AI days start on/after
        // leg2Day and are capped to the trip end.
        $writeAiDays = function (array $days, string $location, \Carbon\Carbon $fromDate, \Carbon\Carbon $capDate) use ($trip) {
            foreach ($days as $i => $day) {
                $dayDate = $fromDate->copy()->addDays($i);
                if ($dayDate->gt($capDate)) break; // don't write past the leg's own date range
                foreach ($day['activities'] ?? [] as $act) {
                    $timeStr = $act['time'] ?? '09:00 AM';
                    try { $t = \Carbon\Carbon::parse($dayDate->toDateString() . ' ' . $timeStr); } catch (\Throwable) { $t = $dayDate->copy()->setTimeFromTimeString('09:00'); }
                    $actType = match(strtolower($act['type'] ?? '')) {
                        'transport', 'transportation', 'flight' => 'Transportation',
                        'hotel', 'accommodation'                => 'Hotel',
                        default                                 => 'Activity',
                    };
                    $trip->itinerary()->create([
                        'title'          => $act['title'] ?? 'Activity',
                        'type'           => $actType,
                        'start_datetime' => $t,
                        'end_datetime'   => $t->copy()->addHour(),
                        'location'       => $location,
                        'notes'          => $act['description'] ?? null,
                    ]);
                }
            }
        };

        // AI content starts right after however many days leg 1's own
        // selections actually consumed (clamped to the days we scheduled
        // onto). Scoped to leg 1's own buckets, not the combined
        // selectionDayBuckets() — that mixes in leg 2's picks too, which
        // would overcount this leg's offset on a multi-city trip.
        $leg1SelectionDaysUsed = min(count($activityDays), count($dayBucketsFor($this->selectedAttractions, $this->selectedVenues)));
        $aiDayOffset = 1;
        if ($totalDays >= 2 && $leg1SelectionDaysUsed > 0) $aiDayOffset += $leg1SelectionDaysUsed;
        if ($hasLeg2) $aiDayOffset++;
        $leg1CapDate = $hasLeg2 ? $leg2Day->copy()->subDay() : $lastDate;
        if (!empty($leg1AiDays)) {
            $writeAiDays($leg1AiDays, $destLabel, \Carbon\Carbon::parse($tripStart)->addDays($aiDayOffset), $leg1CapDate);
        }
        if (!empty($leg2AiDays)) {
            // How many days leg 2's own arrival (flight/hotel) + its
            // Explore & Dine buckets actually consumed, so leg 2's AI days
            // start right after rather than colliding with them.
            $leg2SelectionDaysUsed = count($dayBucketsFor($this->selectedMcAttractions, $this->selectedMcVenues));
            $leg2ArrivalDayUsed    = ($flight2 || $hotel2) ? 1 : 0;
            $leg2AiStart = $leg2Day->copy()->addDays($leg2ArrivalDayUsed + $leg2SelectionDaysUsed);
            if ($leg2AiStart->gt($lastDate)) $leg2AiStart = $lastDate->copy();
            $writeAiDays($leg2AiDays, $leg2Label, $leg2AiStart, $lastDate);
        }

        foreach ($this->customActivities as $ca) {
            $dayDate = $day1Date->copy()->addDays(max(0, $ca['day'] - 1));
            if ($dayDate->gt($lastDate)) continue;
            try {
                $t = \Carbon\Carbon::parse($dayDate->toDateString() . ' ' . $ca['time']);
            } catch (\Throwable) {
                $t = $dayDate->copy()->setTimeFromTimeString('09:00');
            }
            $trip->itinerary()->create([
                'title'          => $ca['title'],
                'type'           => $ca['type'] === 'Transport' ? 'Transportation' : 'Activity',
                'start_datetime' => $t,
                'end_datetime'   => $t->copy()->addHour(),
                'location'       => $destLabel,
                'notes'          => $ca['description'] ?: null,
            ]);
        }

        if (session()->pull('ai_edit_return')) {
            $this->redirect(route('trips.plan.ai'));
            return;
        }

        $this->redirect(route('saved-trips'));
    }

    public function autosaveDraft(): void
    {

        $budget = $this->manualBudgetMax !== ''
            ? $this->parseBudgetInput($this->manualBudgetMax)
            : $this->extractBudgetMax($this->manualBudgetMin);
        $hasAllFields = trim($this->manualFrom) !== ''
            && trim($this->manualTo) !== ''
            && $budget > 0
            && $this->startDate !== ''
            && $this->endDate !== '';

        if (!$hasAllFields) return;

        $data = [
            'user_id'          => auth()->id(),
            'destination'      => $this->manualTo,
            'origin'           => trim($this->manualFrom),
            'origin_code'      => $this->resolveCode($this->manualFrom),
            'destination_code' => $this->resolveCode($this->manualTo),
            'start_date'       => $this->startDate,
            'end_date'         => $this->endDate,
            'budget_limit'     => $budget,
            'travel_type'      => 'Solo',
            'num_travelers'    => 1,
            'status'           => 'draft',
        ];

        $existing = $this->draftTripId ? Trip::where('id', $this->draftTripId)
            ->where('user_id', auth()->id())
            ->where('status', 'draft')
            ->first() : null;

        if ($existing) {
            $existing->update($data);
        } else {
            $this->draftTripId = Trip::create($data)->id;
        }
    }

    private function parseBudgetInput(string $val): float
    {
        return (float) preg_replace('/[^\d.]/', '', $val);
    }

    // Same "min - max" / "min to max" / single-value parsing as
    // proceedFromTripDetails(), but only pulls out the max — used by
    // autosaveDraft() to read the still-unsplit raw budget field.
    private function extractBudgetMax(string $raw): float
    {
        if (preg_match('/(\d[\d,]*)\s*(?:[-–to]+)\s*(\d[\d,]*)/i', $raw, $m)) {
            return (float) preg_replace('/[^\d]/', '', $m[2]);
        }
        return $this->parseBudgetInput($raw);
    }

    public function selectScope(string $scope): void
    {
        $this->tripScope = $scope;
        $this->step = 4;
    }

    // ── Step 4 ─────────────────────────────────────────────
    public function selectDestination(int $id): void
    {
        $dest = Destination::findOrFail($id);
        $this->destinationId   = $dest->id;
        $this->destinationName = $dest->name;
        $this->step = 5;
    }

    // ── Step 4: calendar ───────────────────────────────────
    public function prevMonth(): void
    {
        if ($this->calMonth === 1) { $this->calMonth = 12; $this->calYear--; }
        else $this->calMonth--;
    }

    public function nextMonth(): void
    {
        if ($this->calMonth === 12) { $this->calMonth = 1; $this->calYear++; }
        else $this->calMonth++;
    }

    public function selectDay(string $date): void
    {
        if ($date < date('Y-m-d')) return;

        if (!$this->startDate || ($this->startDate && $this->endDate)) {
            $this->startDate = $date;
            $this->endDate   = '';
        } elseif ($date < $this->startDate) {
            $this->endDate   = $this->startDate;
            $this->startDate = $date;
        } else {
            $this->endDate = $date;
        }
    }

    public function proceedFromCalendar(): void
    {
        if (empty($this->startDate)) {
            $this->dispatch('calendar-validation-error');
            return;
        }

        if (empty($this->endDate)) {
            $this->endDate = $this->startDate;
        }

        $this->step = 6;
    }

    // ── Step 6: group + budget ─────────────────────────────
    public function selectGroup(string $group): void
    {
        if ($this->groupType === $group) {
            $this->groupType = '';
            $this->travelers = 1;
            return;
        }
        $this->groupType = $group;
        $this->travelers = match ($group) {
            'Solo'  => 1,
            default => max($this->travelers, 2),
        };
    }

    public function incrementTravelers(): void
    {
        if ($this->travelers < 20) $this->travelers++;
    }

    public function decrementTravelers(): void
    {
        $min = $this->groupType === 'Group' ? 2 : 1;
        if ($this->travelers > $min) $this->travelers--;
    }

    public function selectBudgetTier(string $tier): void
    {
        $this->budgetTier = $this->budgetTier === $tier ? '' : $tier;
    }

    public function calculateAndProceed(): void
    {
        set_time_limit(120);
        $missingGroup  = empty($this->groupType);
        $missingBudget = empty($this->budgetTier);

        if ($missingGroup || $missingBudget) {
            $this->dispatch('validation-error',
                missingGroup:  $missingGroup,
                missingBudget: $missingBudget,
            );
            return;
        }

        $this->calculateEstimate();
        $this->step = 7;
    }

    // ── Step 7: inline category editing ───────────────────
    public function startEditing(string $category): void
    {
        $this->editingCategory = $category;
    }

    public function stopEditing(): void
    {
        $this->editingCategory = '';
        $subtotal = $this->transportation + $this->accommodation + $this->food
                  + $this->attractions + $this->shopping;
        $this->emergency   = round($subtotal * (auth()->user()->default_buffer_pct / 100), 2);
        $this->budgetLimit = round($subtotal + $this->emergency, 2);
    }

    // ── Confirm ────────────────────────────────────────────
    public function confirm(): mixed
    {
        if ($this->isSaving) return null;

        $this->validate([
            'destinationName' => 'required|string',
            'startDate'       => 'required|date',
            'endDate'         => 'required|date|after_or_equal:startDate',
            'groupType'       => 'required|in:Solo,Group',
            'budgetTier'      => 'required|in:Shoestring,Mid-range,Luxury',
            'budgetLimit'     => 'required|numeric|min:1',
            'travelers'       => 'required|integer|min:1|max:20',
        ]);

        $this->isSaving = true;

        $trip = Trip::create([
            'user_id'       => auth()->id(),
            'destination'   => $this->destinationName,
            'start_date'    => $this->startDate,
            'end_date'      => $this->endDate,
            'num_travelers' => $this->travelers,
            'budget_limit'  => $this->budgetLimit,
            'travel_type'   => $this->groupType,
            'notes'         => "Budget tier: {$this->budgetTier}; Scope: {$this->tripScope}",
        ]);

        foreach ([
            'Transportation'      => $this->transportation,
            'Accommodation'       => $this->accommodation,
            'Food'                => $this->food,
            'Tourist Attractions' => $this->attractions,
            'Shopping'            => $this->shopping,
            'Emergency Funds'     => $this->emergency,
        ] as $cat => $amount) {
            TripBudget::create([
                'trip_id'        => $trip->id,
                'category'       => $cat,
                'estimated_cost' => $amount,
                'actual_spent'   => 0,
            ]);
        }

        // Group trips carry their members across, so each of them sees the
        // trip in their own Saved Trips. Only registered accounts can be
        // linked; an email with no account is simply skipped rather than
        // failing the save.
        if ($this->groupType === 'Group' && $this->groupMemberEmails) {
            $memberIds = User::whereIn('email', $this->groupMemberEmails)
                ->where('id', '!=', auth()->id())
                ->pluck('id');
            $inviter = auth()->user()->full_name ?: 'A fellow traveler';
            foreach ($memberIds as $memberId) {
                GroupMember::firstOrCreate(['trip_id' => $trip->id, 'user_id' => $memberId]);
                Notification::create([
                    'user_id' => $memberId,
                    'trip_id' => $trip->id,
                    'type'    => 'trip_shared',
                    'message' => "{$inviter} added you to their trip to {$this->destinationName}. It's now in your Saved Trips.",
                    'is_read' => false,
                ]);
            }
        }

        SavingsGoal::create([
            'user_id'         => auth()->id(),
            'trip_id'         => $trip->id,
            'goal_name'       => $this->destinationName . ' Trip',
            'target_amount'   => $this->budgetLimit,
            'current_savings' => 0,
            'deadline'        => $this->startDate,
        ]);

        return $this->redirect(route('itinerary.index') . '?trip_id=' . $trip->id, navigate: true);
    }

    // ── Computed properties ────────────────────────────────
    #[Computed]
    public function destinations()
    {
        $userCountry = auth()->user()->country ?? 'Philippines';
        $query = Destination::orderBy('name');
        if ($this->tripScope === 'local') {
            $query->where('country', $userCountry);
        } else {
            $query->where('country', '!=', $userCountry);
        }
        if ($this->destSearch) {
            $query->where('name', 'like', "%{$this->destSearch}%");
        }
        return $query->get();
    }

    public function getDaysProperty(): int
    {
        if (!$this->startDate || !$this->endDate) return 0;
        return max(1, (int) Carbon::parse($this->startDate)->diffInDays($this->endDate));
    }

    public function getCalendarDaysProperty(): array
    {
        $first    = Carbon::createFromDate($this->calYear, $this->calMonth, 1);
        $total    = $first->daysInMonth;
        $startDow = $first->dayOfWeek;
        $today    = date('Y-m-d');

        $days = array_fill(0, $startDow, null);
        for ($d = 1; $d <= $total; $d++) {
            $date   = sprintf('%04d-%02d-%02d', $this->calYear, $this->calMonth, $d);
            $days[] = [
                'day'     => $d,
                'date'    => $date,
                'isPast'  => $date < $today,
                'isToday' => $date === $today,
                'isStart' => $date === $this->startDate,
                'isEnd'   => $date === $this->endDate,
                'inRange' => $this->startDate && $this->endDate
                             && $date > $this->startDate && $date < $this->endDate,
            ];
        }
        return $days;
    }

    public function getComfortLevelProperty(): string
    {
        return match ($this->budgetTier) {
            'Shoestring' => 'BUDGET',
            'Luxury'     => 'PREMIUM',
            default      => 'STANDARD',
        };
    }

    public function getSmartTipProperty(): string
    {
        return match ($this->budgetTier) {
            'Shoestring' => 'Opt for hostels, local street food, and free attractions to stretch your funds further.',
            'Luxury'     => 'Consider hiring a private guide for exclusive access and a personalized premium experience.',
            default      => 'Book accommodation 2+ weeks early to unlock better rates and ensure availability.',
        };
    }

    public function getVarianceProperty(): float
    {
        return round($this->budgetLimit * 0.05, 2);
    }

    public function getTravelersLabelProperty(): string
    {
        return $this->travelers . ' ' . match ($this->groupType) {
            'Group' => ($this->travelers === 1 ? 'Person' : 'People'),
            default => 'Adult',
        };
    }

    private function calculateEstimate(): void
    {
        $days  = $this->getDaysProperty();
        $n     = $this->travelers;
        $scope = $this->tripScope ?: 'local';
        $r     = self::RATES[$scope][$this->budgetTier] ?? self::RATES['local']['Mid-range'];
        $rooms = max(1, (int) ceil($n / 2));

        $this->transportation = round(($r['transport_base'] + $r['transport_daily'] * $days) * $n, 2);
        $this->accommodation  = round($r['accommodation_night'] * $days * $rooms, 2);
        $this->food           = round($r['food_day'] * $days * $n, 2);
        $this->attractions    = round($r['attractions_day'] * $days * $n, 2);
        $this->shopping       = round($r['shopping_per_person'] * $n, 2);

        $subtotal = $this->transportation + $this->accommodation + $this->food
                  + $this->attractions + $this->shopping;
        $this->emergency   = round($subtotal * (auth()->user()->default_buffer_pct / 100), 2);
        $this->budgetLimit = round($subtotal + $this->emergency, 2);
    }

    public function render()
    {
        return view('livewire.traveler.trip-planner-wizard');
    }
}
