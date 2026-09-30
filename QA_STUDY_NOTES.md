# Budgetra QA Study Notes

**Trip Planner · Saved Trips · Saving Goals**

These notes are for testing, not for orientation. `STUDY_GUIDE.md` tells you
where the code lives; this tells you what each control does, what the expected
result is, and where the product is most likely to break.

---

## 1. How to read these notes

**Conventions**

- Controls are named **as the user sees them** ("Confirm Amount"), followed by
  the method or property behind them (`confirmEmergencyFund()`).
- References are `path/File.php:line`. Line numbers drift — treat them as a
  starting point, not gospel.
- "Silent no-op" means the click does nothing at all: no message, no error, no
  state change. Budgetra does this a lot, and it is the single most common
  reason a tester thinks something is broken when it is actually refusing.

**Running the suite**

The suite **cannot be run in one process.** `TripPlannerWizard.php:1339` calls
`set_time_limit(60)`. In a browser request that is harmless and per-request;
under PHPUnit the whole suite shares one PHP process, so the clock starts at
that call and kills whatever test is running sixty seconds later. The fatal
lands on a different test every time, always at a framework bootstrap line,
which makes it look like flakiness. It is not.

Run it in chunks instead:

```bash
# per directory
for D in Admin Alert Auth Destination Expense Itinerary Livewire Llm \
         Report Review SavingsGoal Services Trip UI; do
  php vendor/bin/phpunit tests/Feature/$D
done

# then the unit suite and the loose files at the root of tests/Feature
php vendor/bin/phpunit --testsuite Unit tests/Feature/*.php
```

**Current baseline: 921 tests, 919 passing.** The two failures are pre-existing
copy drift, documented in §5.6. Any third failure is yours.

---

## 2. Trip Planner

The largest feature in the app: `app/Livewire/Traveler/TripPlannerWizard.php`
(~3,300 lines) driving `resources/views/livewire/traveler/trip-planner-wizard.blade.php`
(~3,900 lines). One Livewire component holds all ten screens.

### 2.1 Entry points

Every planner route sits inside the `['auth','not-admin']` group
(`routes/web.php:28`). A signed-out visitor is redirected to login; an **admin
account is blocked** by `not-admin` — worth remembering if you test with an
admin login and wonder why you cannot reach the planner.

| Route | Name | Handler |
|---|---|---|
| `GET /trips` | `trips.index` | `TripPlannerWizard` |
| `GET /trips/plan` | `trips.plan` | `TripPlannerWizard` — always starts fresh |
| `GET /trips/plan?draft={id}` | — | Resumes a saved draft |
| `GET /trips/plan?from=&to=&budget_min=…` | — | AI "Edit" deep-link |
| `GET /trips/plan/ai` | `trips.plan.ai` | `Llm` (separate AI planner) |
| `GET /trips/import/{code}` | `trips.import` | `TripImportController@import` |
| `GET /trips/type`, `/trips/create`, `POST /trips` | — | **Legacy** pre-wizard forms |
| `GET /trips/{trip}`, `/edit`, `PUT`, `DELETE` | — | Legacy REST, owner-only (403) |
| `GET/POST /trips/{trip}/budget`, `/estimate` | — | Legacy budget + estimate |

**A third entry point has no URL.** `Llm::proceedToWizardItinerary()` writes
`session('wizard_ai_handoff')` and redirects to `trips.plan`; `mount()` pulls it
(one-shot) and drops the traveler **directly on step 6**, skipping steps 1–5
entirely. If you are testing step 6 and cannot work out how the user got there
with no selections, this is why.

In-app links to `trips.plan` exist on the dashboard, saved trips, multi-trip
hub, itinerary, expenses, savings and notifications pages — ten in total. All
are worth a click in a smoke pass.

### 2.2 The step machine

State is `$step` (0–9) and `$planningMode`. Every screen is gated
`@if ($planningMode !== '' && $step === N)`.

| Step | On screen | Advances via | Back |
|---|---|---|---|
| 0 | Mode select | `selectPlanningMode('manual')` | — (root) |
| 1 | Plan Your Trip | `proceedFromTripDetails()` | — |
| 2 | Select Flight | `selectFlight()` | `backFromEdit(1)` |
| 3 | Select Accommodation | `selectAccommodation()` / `skipAccommodation()` | `backFromEdit(2)` |
| 4 | Select Food & Dining | `continueFromVenues()` / `skipVenue()` | `backFromEdit(3)` |
| 5 | Select Attractions | `continueFromAttractions()` / `skipAttraction()` | `backFromEdit(4)` |
| 6 | Emergency Fund | `confirmEmergencyFund()` | `backFromEdit(5)` |
| 7 | Generate Itinerary | `generateItinerary()` | `backFromEdit(6)` |
| 8 | Itinerary Preview | `continueItinerary()` | `backFromEdit(7)` |
| 9 | Trip Summary | `saveItinerary()` | `$set('step', 8)` |

`backFromEdit(N)` is not just a step setter. If `session('ai_edit_section')` is
present it forgets both session keys and **redirects to the AI planner**
instead. Step 9's back button uses a raw `$set('step', 8)` and therefore does
*not* honour that path — a difference worth a test.

### 2.3 The multi-city branch

Choosing **Multi-city** on step 2 and picking a second destination (`$mcTo`)
turns steps 2–5 into **two passes each**. The pass is tracked by a boolean, not
by `$step`:

| Step | Leg-2 flag | Set by | Cleared by |
|---|---|---|---|
| 2 | `mcFlightStep` | `selectFlight()` | `selectMcFlight()`, `searchManualFlights()` |
| 3 | `mcHotelStep` | `selectAccommodation()`, `skipAccommodation()` | `selectMcAccommodation()`, `skipAccommodation()` |
| 4 | `mcVenueStep` | `continueFromVenues()`, `skipVenue()` | `searchVenues()`, `skipVenue()` |
| 5 | `mcAttractionStep` | `continueFromAttractions()`, `skipAttraction()` | `searchAttractionsList()`, `skipAttraction()` |
| 8 | `itineraryLeg` (1/2) | `generateItineraryLeg2()` | `backToLeg1Itinerary()`, `generateItinerary()` |

Full order: `1 → 2a → 2b → 3a → 3b → 4a → 4b → 5a → 5b → 6 → 7 → 8a → 8b → 9`.

**This is the least-tested area of the app** — not one automated test sets
`flightTripType = 'multi_city'` with a second destination. See §5.3 for the
defect the back buttons cause here.

Changing `$mcTo` fires `resetMultiCityLegState()`, which wipes every leg-2
result **and selection**. Switching away from multi-city also clears
`mcTo`/`mcStartDate`/`mcEndDate`. Both are easy to trigger accidentally and
easy to mistake for data loss.

### 2.4 Control inventory

#### Step 0 — Mode select

| Control | Binding | Expected |
|---|---|---|
| **Manual Planning** card | `selectPlanningMode('manual')` | `planningMode='manual'`, step 1 |
| **AI Powered Planning** card | plain `<a href>` to `trips.plan.ai` | Navigates. Does **not** set `planningMode` |
| Trip-code field (max 8 chars) | `wire:model="importCodeInput"` | Enter submits |
| **Import Trip** | `$wire.importCode()` | Imports, or flags the field red |
| **Set Up Your Profile First** | link to `profile.setup` | Only when no `UserProfile` |
| **Skip for now** | `budgetraSkipProfileSetup()` | Pure JS, persists in `localStorage` |

#### Step 1 — Plan Your Trip

| Control | Binding | Expected |
|---|---|---|
| **From** field | opens city dropdown | Origin list is restricted to the traveler's **registration country** |
| From — clear (×) | `clearField('from')` | Empties, closes dropdown |
| **Swap** (⇄) | Alpine `swapCities()` | Swaps From/To. (The server's `swapCities()` is dead code) |
| **To** field | opens city dropdown | Unrestricted |
| **Preferred Budget Range** | `@change → $wire.set('manualBudgetMin')` | Digit-grouped, **12-digit cap**. Accepts `30,000 - 50,000` or a single value |
| **Start Date** / **End Date** | Alpine calendars | Past days disabled; picking one clears the other if the range inverts |
| **Next** | `proceedFromTripDetails()` | All five fields required |

The budget field carries `wire:ignore`, so Livewire never re-renders it —
if you are testing server-driven changes to it, they will not appear.

#### Step 2 — Select Flight

| Control | Binding | Expected |
|---|---|---|
| Search panel From/To/Start/End | `$wire.set(...)` | Editable without going back |
| Leg-2 To / Start / End | `$wire.set('mcTo'…)` | Visible only when trip type is Multi-city |
| **Search Flights** | `searchManualFlights()` | Spinner-only button; results or an error strip |
| **Price: Low→High / High→Low** | Alpine `sortFlights()` | **Client-side DOM reorder only** — no re-query |
| **All Airlines / {carrier}** | Alpine `filterAirline()` | Client-side; pill only appears with >1 airline |
| One-way / Round Trip / Multi-city | `wire:model.live="flightTripType"` | Switching away clears all leg-2 state |
| Card **Select** | `confirmFlightPick($i)` | Opens a confirm modal |
| Modal **Yes, select** | `selectFlight($i)` | Stores the pick, advances (or starts leg 2) |

#### Step 3 — Select Accommodation

| Control | Binding | Expected |
|---|---|---|
| **Search Accommodations** | `searchAccommodations()` | Never returns empty — see `fallbackHotels()` |
| Price sort | `sortAccommodations()` | Client-side |
| **Search stays** + **Search** | `searchHotelResults()` | **Narrows the current results only** — no provider call |
| Hotel / Apartment / Inn / Resort | `$wire.set('hotelType')` | **Does not re-search.** Purely cosmetic until you hit Search Accommodations |
| **Skip this step** | `skipAccommodation()` | Clears the pick, advances |
| Card **Select** | `selectAccommodation($i)` | Advances (or starts leg 2) |

"Guests: 1 Adult" is a hardcoded literal. `$hotelGuests` is never bound to
anything.

#### Step 4 — Food & Dining, Step 5 — Attractions

Both are multi-select and structurally identical:

| Control | Binding | Expected |
|---|---|---|
| **Category** / **Type** pill | `$set('venueCategory')` / `$set('attractionType')` | **Does not re-search** |
| **Search Food & Dining** / **Search Attractions** | `searchVenues()` / `searchAttractionsList()` | Fresh provider call |
| Search box + **Search** | `searchVenueResults()` / `searchAttractionResults()` | Narrows current results |
| Card **Select / ✓ Selected** | `toggleVenue($i)` / `toggleAttraction($i)` | Keyed by **name**, so duplicates collapse |
| **Continue (N selected)** | `continueFromVenues()` / `continueFromAttractions()` | Floating button, bottom-right |
| **Skip this step** | `skipVenue()` / `skipAttraction()` | Clears selections, advances |

#### Step 6 — Emergency Fund

| Control | Binding | Expected |
|---|---|---|
| **Back to Select Attractions** | `backFromEdit(5)` | Top-left, outside the centred block |
| Amount field | `$wire.set('emergency', raw)` | Typed in the **trip's** currency, not pesos. 12-digit cap |
| Clear (×) | `clear()` | Wipes display *and* the Livewire property |
| **Confirm Amount** | `confirmEmergencyFund()` | Converts to pesos, advances to 7 |

**Zero is a valid fund** server-side. The empty-field rejection is Alpine-only
(a red flash), so a Livewire-level test calling `confirmEmergencyFund()`
directly will sail past it.

The fund is added **on top of** the budget, not carved out of it, and has no
ceiling of its own.

#### Step 7 / 8 / 9

| Step | Control | Binding | Expected |
|---|---|---|---|
| 7 | **Generate Itinerary** | `generateItinerary()` | Step 8 + generation starts |
| 8 | **Generate Other Options** | `regenerateItineraryOptions()` | Re-asks the LLM chain |
| 8 | **Save Itinerary** / **Continue to Leg 2** | `continueItinerary()` | Label switches on multi-city leg 1 |
| 8 | Option card | `selectItineraryOption($i)` | Whole card is the target |
| 8 | "Select This Option" button | **none** | Inert — relies on the click bubbling to the card |
| 8 | **Add Custom Activity** | `openCustomActivityModal()` | Only when ≤1 option is showing |
| 8 | Modal **Add Activity** | `addCustomActivity()` | **Silently no-ops on a blank title** |
| 9 | Per-row **Edit** | `editFromSummary(N)` | Jumps back **and nulls `aiItinerary`** — the itinerary must be regenerated |
| 9 | **Confirm Trip** | `saveItinerary()` | The real save |

### 2.5 Validation and error text

| Where | Trigger | What the user sees |
|---|---|---|
| Step 1 | Any of 5 fields empty | Red border + ring on the field. **No text.** Alpine-only, with a server backstop that dispatches `trip-details-missing` |
| Step 1 | From == To | Only the **To** field flags. No message |
| Step 2 | Same city | "Origin and destination cannot be the same city. Please choose a different destination." |
| Step 2 | No results | "We couldn't load flights right now. Try searching again in a moment." |
| Step 2 | Stale pick | "That flight is no longer available. Please pick another." — **no advance** |
| Step 3 | Stale pick | "That stay is no longer available. Please pick another." |
| Step 4 | No results | "We couldn't load dining options right now. Try searching again, or skip this step." |
| Step 5 | No results | "We couldn't load attractions right now. Try searching again, or skip this step." |
| Step 6 | Empty amount | Red flash on the field. Alpine-only, no text |
| Step 6 | Rate unreachable | "I couldn't fetch the live exchange rate just now — please try again in a moment." No advance |
| Modal | Rate unreachable | Same text; modal stays open, `convertedBudget` stays null |
| Step 8 | All providers failed | Full-page "Couldn't generate itinerary suggestions" + **Try Again** |
| Step 9 | Rate unreachable / over ceiling | **Nothing.** See §5.3 |

### 2.6 External calls and failure modes

| Step | Provider chain |
|---|---|
| 2 | SerpAPI → Serper → `[]` + error text |
| 3 | SerpAPI → Serper → **`fallbackHotels()`** (4–8 templated rows, never empty) |
| 4, 5 | SerpAPI → Serper → `[]` + error text |
| 6, 9, modal | `CurrencyConverterService::rateToPhp()` |
| 8 | Mistral → OpenRouter → Groq → Gemini → Cerebras |

**SerpAPI** has a hard cap of **80 requests/day**, counted in `serpapi_usage`.
Over the cap it returns `null` with no error, which the wizard cannot
distinguish from "no results" — so a quota-exhausted tester sees "We couldn't
load flights right now" and no hint of the real cause. Worse, **the counter is
incremented before the success check**, so failed calls burn quota too. Cache
TTLs: flights 6 h, hotels 12 h, maps/images 24 h.

**Currency** uses a 4-second timeout with three cache layers, including a
5-minute *negative* cache so an outage does not cost 4 s × 26 lookups per
render. There are **no hardcoded fallback rates** anywhere — the app refuses
rather than guesses, which is why so many paths hard-stop on a rate failure.

**Itinerary generation** has a 100-second overall deadline, 32 s per option,
25 s per provider, and stops asking when under 5 s remain. Three fixed option
constraints (Balanced Mix / Adventure Focus / Budget Friendly) — but the labels
are **not shown**; cards are relabelled "Option 1/2/3" by cost order.

### 2.7 What gets persisted

`saveItinerary()` runs as **one request with no transaction**. It writes:

- **One `trips` row.** `status` is deliberately **not set** — it lands NULL and
  is derived from the dates by `Trip::resolvedStatus`. `travel_type` is
  hardcoded `'Solo'` and `num_travelers` hardcoded `1`, regardless of anything
  the traveler chose.
- **No `trip_budgets` rows.** Only the dead legacy `confirm()` path writes them.
- **`itinerary` rows**: arrival flight (day 1, 10:00), hotel check-in (day 1,
  14:00), attractions (09:00 / 11:30 / 16:00), venues (12:30 "Lunch at…",
  19:00 "Dinner at…"), leg-2 equivalents, check-out on the last day, a
  departure flight **only for a round trip**, then AI days and custom
  activities.
- **Deletes the autosaved draft** if one exists.

`autosaveDraft()` fires from an Alpine `$watch` on the five step-1 fields and
on `visibilitychange`. It writes a `status='draft'` row, but **only** if all
five fields are present, the rate is reachable, and the peso figure is under
₱99,999,999.99. Otherwise it silently does nothing.

### 2.8 Automated coverage

Roughly 60 tests across `TripPlannerWizardTest`, `WizardEmergencyFundBackTest`,
`WizardItineraryBoardTest`, `PlannerCurrencyFlowTest`, `DraftBudgetCurrencyTest`,
`ForeignCurrencyTravelTest`, `ThirdCountryScenarioTest`, `OriginCityListTest`,
`TripPlannerDatePickerTest`, `ItineraryProviderChainTest` and `tests/Feature/Trip/*`.

Well covered: currency conversion end to end (three-country scenarios, the
conversion modal, refusal without a rate), the emergency-fund conversion,
custom activities, draft budget storage, the origin-city restriction, the
step-1 date picker, and the LLM provider chain's refusal memo.

**Not covered at all** — this is your manual pass:

1. **The entire multi-city branch.** Nothing sets `multi_city` with an `mcTo`.
2. **Flight selection end to end** — search, confirm modal, stale-index guards,
   the price and airline filters.
3. **Accommodation** — search, the fallback list, select, skip, the type radios.
4. `skipVenue()` / `skipAttraction()`.
5. The result-narrowing search boxes on steps 3/4/5.
6. SerpAPI quota exhaustion.
7. `saveItinerary()`'s harder scheduling branches (1-day and 2-day trips, the
   round-trip departure row, AI-day offsetting).
8. The `isSaving` double-click guard.
9. All three `mount()` deep-links.
10. `importCode()` — all four rejection branches.
11. `editFromSummary()` and the fact that it nulls the itinerary.
12. Step 8 option selection and the inert inner button.

---

## 3. Saved Trips

`app/Livewire/Traveler/SavedTrips.php` + `MultiTripHub.php`, plus
`TripImportService` for sharing.

### 3.1 Trip lifecycle

`trips.status` is a **nullable string with no default and no enum**. Values:

| Value | Written by |
|---|---|
| `NULL` | The normal state of a real trip — the wizard's save, `TripController@store`, and imports all omit it |
| `'draft'` | `autosaveDraft()` and the AI planner |
| `'upcoming'` / `'active'` / `'past'` | **Only** the Saved Trips Edit modal |
| anything else | Only by direct DB edit — but see §5.2, nothing validates it |

`Trip::resolvedStatus` returns the stored value if truthy, otherwise derives
from dates: start in the future → `upcoming`, end in the past → `past`,
otherwise `active`. A same-day trip is `active`.

**A stored status never expires.** A trip manually set to "Upcoming" stays
upcoming forever, even years after its end date. This is the main stale-state
scenario to exercise.

### 3.2 Tabs — and why the two screens disagree

| Screen | Active tab means | Draft tab? |
|---|---|---|
| Saved Trips | `whereNotIn(['past','draft'])` | Yes |
| Multi-Trip Hub | `whereIn(['active','upcoming'])` | No — drafts are excluded from the query entirely |

Consequence: a trip with an unrecognised stored status appears in Saved Trips'
Active tab and in **neither** Hub tab, while still being counted in the Hub's
totals.

Both screens search `destination`, `trip_name` and `leg2_destination` with a
500 ms debounce. Saved Trips paginates at 3 cards per tab; the Hub does not
paginate at all.

**Draft cards are blurred and `pointer-events:none`.** The kebab, Share, PDF
and View Details are rendered but unclickable — only the "Continue Editing" /
"Delete Trip" overlay works. Expect bug reports about this.

### 3.3 Control inventory

| Control | Method | Expected |
|---|---|---|
| Kebab **⋮** | Alpine | Opens Edit / Delete |
| **Edit Trip** | `openEditName($id)` | Owner-only. **Silent no-op for members** |
| **Delete Trip** | `confirmDelete($id)` | Opens the confirm dialog — see §5.2 |
| Share icon | `openShare($id)` | Owner-only. **Silent no-op for members** |
| PDF icon | plain `<a>` (deliberately not `wire:navigate`) | File download |
| **View Details / Hide Details** | `showDetail($id)` | Inline panel, no ownership check. No ✕ — you re-click the button |
| **Add Expense** | `<a>` to `expenses.index?trip_id=` | Navigates |
| **Continue Editing** (drafts) | `<a>` to `trips.plan?draft=` | Resumes the wizard |
| Delete dialog **Delete** | `deleteTrip()` | Owner check; **silently does nothing** otherwise |
| Edit modal — Trip Name | `wire:model` (deferred) | Blank name = silent no-op, §5.2 |
| Edit modal — Solo / Group | Alpine + `$set('editType')` | Group→Solo **deletes all members** and forces `num_travelers = 1` |
| Edit modal — member ✕ (saved) | `removeSavedMember($id)` | **Deletes immediately.** Cancel does not undo it |
| Edit modal — member ✕ (pending) | `removePendingMember($i)` | In-memory only |
| Edit modal — **Add** | `lookupMember()` | Four ordered error messages, below |
| Edit modal — status buttons | `$set('editStatus', …)` | Written unvalidated |
| Edit modal — **Save** | `saveEditName()` | Also renames **every** savings goal on the trip |
| Hub — **Compare** | `toggleCompare($id)` | Picking a third **silently drops the oldest** |
| Hub — **Compare Trips** | `runComparison()` | Only fires at exactly 2 |
| Hub — **Clear** | `clearCompareSelection()` | Empties selection |

`lookupMember()` messages, in check order: empty → silent; no such user → "No
registered user found with that email."; yourself → "You are already the trip
owner."; duplicate → "This user is already added."

### 3.4 Sharing and importing

A trip is shareable if **any one** of `flight_selection`, `hotel_selection`,
`venue_selection` or `attraction_selection` is non-null. Leg-2 selections do
not count.

Codes are 8 characters from an alphabet with no `0/O/1/I`, generated on first
share and **never expiring or revocable**. Lookup is case-insensitive and
whitespace-tolerant.

**`import()` copies:** destination, name, dates, travellers, budget limit,
travel type, notes, origin/destination codes, cover image, recomputed total
cost, rebuilt summary, all multi-city fields, all eight selection snapshots,
and every itinerary row.

**`import()` does NOT copy:** `status` (so imports are never drafts),
`share_code`, **`destination_currency` / `budget_currency` / `budget_local`** —
so an imported trip permanently loses its currency and always displays in pesos
— the **emergency fund** (the Saved Trips detail panel renders an Emergency
Fund row that will never populate), expenses, savings goals, group members.

Rejection branches, in order, from `/trips/import/{code}`:

1. Bad code → "That share code or link is no longer valid."
2. **Your own trip → redirect with no message at all.**
3. Not shareable → "This trip has nothing shareable saved on it."
4. Success → "Trip imported!"

The wizard's paste-a-code box runs the same four checks but with **different
text**, and it *does* tell you about your own code: "That's your own trip's
code." Two entry points, two behaviours — worth raising.

### 3.5 Access control

`User::trips()` is owner-only. `User::accessibleTrips()` adds trips you were
added to as a group member, **excluding their drafts** (you always see your
own drafts).

A member **can**: see the trip everywhere, open the detail panel, add/edit/
delete expenses on it, download its PDF, add itinerary items and moments, and
compare it.

A member **cannot**: rename it, change its type or status, delete it, share it,
or manage members. The REST routes (`/trips/{id}`) return 403 for a member even
on a trip they belong to.

**Every refusal on these two screens is a silent no-op**, not a 403. No file
here contains a single `abort()`. This is the most important sentence in this
section: if a control appears to do nothing, check ownership before filing it
as a bug — then file the silence itself as a bug.

Members are added unilaterally, with no invitation or acceptance step, and
there is **no way for a member to leave a trip**.

### 3.6 Currency

`trip_apply_display_currency()` converts only when the trip is `active` or
`upcoming` **and** has a `destination_currency` **and** a live rate resolves.
Anything else stays in pesos. Converted amounts print the **code**, not the
symbol ("JPY 155,039").

**Saved Trips converts; Multi-Trip Hub never does.** The same Japan trip shows
JPY on one screen and ₱ on the other.

### 3.7 Automated coverage

`SavedTripsTest` (12 tests) covers spend percentages and the currency matrix
thoroughly — ongoing/upcoming/draft/past × rate available/unavailable.
`MultiTripHubTest` (9) covers search, the compare toggle and the comparison
modal. `TripCrudTest` (9) covers the legacy REST routes.

**Zero tests** for: `TripImportService` (any method), `/trips/import/{code}`
(any branch), `importCode()`, the share modal, the entire Edit modal (rename,
members, the Group→Solo wipe, the savings-goal rename, the `trip_shared`
notification), delete from Saved Trips, tabs, pagination, Saved Trips search,
`resolved_status` as a unit, or a group member's view of the screen.

---

## 4. Saving Goals

`app/Http/Controllers/Traveler/SavingsGoalController.php` (111 lines) and
`app/Livewire/Traveler/SavingsGoalManager.php`.

### 4.1 The big one: `GET /savings` writes to the database

`SavingsGoalController::index()` (`:11-39`) creates a `SavingsGoal` for **every
accessible trip that lacks one for this user**, on **every page load**. It is
not a migration, not a job — loading the page is a write.

Consequences a tester must internalise:

- **Deleting a goal is not durable.** `DELETE /savings/{goal}` works, then the
  next `/savings` visit recreates it with `current_savings` reset to 0.
- On a group trip, each member gets their **own** row — four people means four
  `savings_goals` rows for one `trip_id`, each created the first time that
  person opens the page.
- Created values: `goal_name` = `destination . ' Trip'` (**not** `trip_name`, so
  a trip called "Honeymoon" to Tokyo gets a goal called "Tokyo Trip"),
  `target_amount` = `max(1, round($total / $heads, 2))`, `current_savings` = 0,
  `deadline` = the trip's `start_date` (which may already be in the past).
- `$heads` splits the cost **only when `travel_type` is literally "Group"**.
  "Family", "Couple" and "Friends" all divide by 1.
- A goal for a **draft** trip is created but never displayed — both tabs filter
  drafts out.

There is a **second, inconsistent** auto-creation path: the wizard
(`TripPlannerWizard.php:3178`) creates a goal at trip-save time using the
**full, undivided** budget. So the origin of `target_amount` differs depending
on which path created the goal.

### 4.2 Which number drives the percentage

The effective target is **`trip->total_cost ?? target_amount`**, applied at six
sites (`SavingsGoalManager.php:163, 215, 222, 236`, the blade's line 15, and
`DashboardController.php:261`).

Two consequences:

- **Editing "Target Amount" has no visible effect** whenever the linked trip
  has a `total_cost`. The form saves, the flash says "Goal updated.", the card
  does not move. Expect this as a bug report.
- A **group member is shown the whole trip cost**, not the share stored in
  their own row. The division at `:35` is effectively dead for any priced trip.

`??` only catches NULL, so a trip priced at exactly `0.00` does **not** fall
back — see §5.1.

### 4.3 Control inventory

The card's primary action is a **three-way branch**; only one ever renders:

| Condition | What renders | Clickable? |
|---|---|---|
| `current_savings >= target` | grey "Goal Reached!" | **No** — a `<div>` |
| trip status is `past` | grey "Trip Finished", `disabled` | **No** |
| otherwise | brown **Add Savings** | Yes → `openDeposit()` |

A completed *past* trip shows "Goal Reached!", not "Trip Finished".

| Deposit dialog | Binding | Expected |
|---|---|---|
| Backdrop | `wire:click.self="closeDeposit"` | Only the overlay itself closes it |
| Amount field | Alpine `$wire.set('depositAmount')` | **Not `wire:model`.** Hard-clamps client-side to the remaining amount |
| Conversion hint | `x-show` | Only for a foreign trip with a resolved rate |
| **Add Savings** | `submitDeposit()` | Disabled until a positive amount is entered |
| **Cancel** | `closeDeposit()` | **Does not reset `depositAmount`** — reopening shows an enabled button over a blank field |

**The create and edit pages are not linked from anywhere in the UI**, and there
is **no delete control at all** — `savings.destroy` is reachable only by
issuing the request directly. If you are asked to test "add a savings goal",
the answer is that the product has no button for it; goals arrive by
auto-creation only.

`openProjection()` / `closeProjection()` / `$showProjection` exist and are
tested, but **there is no projection dialog in any blade**. The test passes
while the feature does not exist.

### 4.4 Computed values

`getPctProperty`, `getDailyNeededProperty`, `getDaysLeftProperty` and
`getIsCompletedProperty` are defined — and **none of them is rendered by any
view**. The card recomputes its own `$cardPct` and `$cardDone` inline. Their
formulas:

| Accessor | Formula | Boundaries |
|---|---|---|
| `pct` | `min(100, round(saved / target * 100, 1))` | Upper clamp only. 150% reads as 100 |
| `dailyNeeded` | `remaining / max(1, daysToDeadline)` | 0 when met. A past deadline gives "all of it, today" |
| `daysLeft` | `max(0, diffInDays)` | Past deadline → 0. "Overdue by N" is not representable |
| `isCompleted` | `saved >= target` | **No zero guard** — see §5.1 |

Bar colour uses `meter_color($pct, 'progress')` — the reversed ramp: ≥70 teal,
≥30 amber, else red. At 0% the bar is zero-width *and* red-coloured, i.e.
invisible.

### 4.5 Currency

Everything in `savings_goals` is stored in **pesos**, always. The create/edit
forms are labelled `₱` and write raw pesos. The deposit dialog is labelled in
the traveler's **home** currency and converts on submit.

`hasCurrencyConversion()` is false for a domestic trip, and false when the
destination currency equals the home currency (a Japanese user on a Tokyo trip
sees no redundant conversion).

If a rate lookup fails for a non-PHP home currency, `displayHomeAmount()` falls
back to printing the **peso** figure with a ₱ sign. And on submit, the deposit
is stored **1:1** — see §5.1.

### 4.6 Automated coverage

`SavingsGoalManagerTest` (13 tests) covers the deposit flow, the two
notification branches, and the currency matrix well (PH user + JPY trip, CAD
user + JPY trip exercising the 4-decimal rate label, domestic no-conversion).
`SavingsGoalTest` (6) covers CRUD, the 403 on someone else's goal, and auth.

**Zero tests** for: the auto-creation loop (nothing asserts that loading
`/savings` creates rows, divides a group cost, or resurrects a deleted goal),
the group-member path, the `total_cost` vs `target_amount` precedence (every
test sets both equal or leaves `total_cost` null), `store()`'s `after:today`
rule or its owner check, **`update()`'s missing trip check**, the
`savings_goal_deposit` notification, the deposit `max` rule, the "Trip
Finished" state, the draft-goal exclusion, or any of the four accessors.

---

## 5. Known defects and risk register

Verified against the code, not inferred.

### 5.1 Saving Goals

| # | Sev | Defect |
|---|---|---|
| S1 | **High** | `SavingsGoalController::update()` (`:91-103`) checks the **goal's** owner but validates `trip_id` with only `exists:trips,id`. A user can re-point their goal at **any trip id in the system**, and the card then renders that trip's cover image, destination, dates, travel type and cost. `store()` (`:71-74`) has the check; `update()` does not. |
| S2 | **High** | `GET /savings` performs INSERTs on every load, so `DELETE` is not durable and the endpoint is not idempotent. |
| S3 | **High** | A non-PHP home currency with a failed rate lookup stores the deposit **1:1** — 500 CAD becomes ₱500 — with no error shown. |
| S4 | Med | A trip priced at exactly `0.00` makes `isCompleted` true (`0 >= 0`) while `pct` is 0. The card reads **"Goal Reached!"** over an empty bar. `??` does not catch `0`. |
| S5 | Med | Editing "Target Amount" has no visible effect when the trip has a `total_cost` (§4.2). |
| S6 | Med | `SavedTrips::saveEditName()` mass-renames **every** goal on a trip, including other users' goals on a group trip — bypassing the per-goal ownership rule. |
| S7 | Low | `deadline` requires `after:today` on create but not on update. |
| S8 | Low | Create/edit unreachable from the UI; delete has no control at all. |
| S9 | Low | `openProjection()` is tested but has no dialog — a green test for a non-existent feature. |
| S10 | Low | A goal created with `trip_id = null` saves successfully but is never displayed (`whereNotNull('trip_id')`). |

### 5.2 Saved Trips

| # | Sev | Defect |
|---|---|---|
| T1 | ~~**High**~~ **Fixed** | `MultiTripHub::fetchCompareData()` — `$trips->firstWhere('id', $id)` returned null if a selected trip left the filtered set, then dereferenced `$trip->budget_limit`. **Was:** select two trips, then type in the search box so one drops out. **Fixed** as a side effect of moving search into the browser: the server-side `where()` that shrank this collection on every keystroke is gone, so a selected id is always still in it. |
| T2 | Med | `confirmDelete()` (`:45-50`) has **no ownership check** and uses an unscoped `Trip::find()`. Any trip id leaks its name into the dialog. The Delete button then silently no-ops. |
| T3 | Med | `removeSavedMember()` deletes the `GroupMember` row **immediately**; "Cancel" does not undo it. |
| T4 | Med | Every non-owner refusal is a **silent no-op** — Share, Edit and Delete all appear on shared-with-me cards and do nothing. |
| T5 | Med | Imported trips lose `destination_currency` / `budget_currency` / `budget_local` and the emergency fund, permanently. |
| T6 | Med | `editStatus` is written **unvalidated**. An unknown value lands in Saved Trips' Active tab and in neither Hub tab. |
| T7 | Med | A stored status never re-derives from dates — a trip stuck on "Upcoming" years later. |
| T8 | Low | `MultiTripHub::showDetail()` has **no UI trigger**; the detail modal is unreachable in the product but green in the tests. |
| T9 | Low | A blank trip name in the Edit modal returns early — modal stays open, no message. |
| T10 | Low | Saved Trips converts currency; Multi-Trip Hub does not. Same trip, two figures. |
| T11 | Low | Importing your own trip via URL redirects with **no feedback**; the same action in the wizard gives a message. |
| T12 | Low | Alpine `page` state is not reset when the search or tab changes. |

### 5.3 Trip Planner

| # | Sev | Defect |
|---|---|---|
| P1 | **High** | `saveItinerary()`'s two hard failures — rate unreachable (`:2470`) and budget over ₱99,999,999.99 (`:2479`) — write to `emergencyError`, which is rendered **only at line 2425, inside step 6**. On step 9 the spinner stops and nothing else happens. No trip is saved and the user is told nothing. |
| P2 | **High** | Multi-city back buttons move `$step` without resetting `mcHotelStep` / `mcVenueStep` / `mcAttractionStep`. "Back to Accommodations" from step 4 lands on step 3 showing **leg 2's** list. |
| P3 | Med | SerpAPI's 80/day cap returns `null` indistinguishably from "no results", and a **failed call still increments the counter**. |
| P4 | Med | Leg-2 dining checks `empty($mcVenueResults)` but iterates the *filtered* set — a search matching nothing renders a blank list with a Continue button and no message. Leg 1 and steps 3/5 check the filtered set correctly. |
| P5 | Med | `saveItinerary()` hardcodes `travel_type = 'Solo'` and `num_travelers = 1`, discarding the group settings `mount()` seeds from the profile. |
| P6 | Low | Step 8's "Select This Option" button has **no handler** — it works only because the click bubbles to the card. |
| P7 | Low | Step 9's back uses `$set('step', 8)`, bypassing `backFromEdit()` and therefore the AI-edit return path. |
| P8 | Low | `addCustomActivity()` silently no-ops on a blank title. |
| P9 | Low | Dead but **tested**: `confirm()`, `selectScope()`, `backToAttractions()`. Dead and untested: `downloadPdf()`, the whole legacy calendar/tier flow, `startNewTrip()`, `deleteTrip()`. |
| P10 | Low | Two dispatched events (`calendar-validation-error`, `validation-error`) have no listener; three `#[On]` listeners (`searchMcHotels`, `searchMcVenues`, `searchMcAttractions`) are never dispatched. |

### 5.4 Infrastructure

`TripPlannerWizard.php:1339` — `set_time_limit(60)` leaks into the shared
PHPUnit process and kills the suite partway. See §1.

### 5.5 The two known test failures

Both are one-word copy drift; the code is right and the assertion is stale.

| Test | Expects | Page renders |
|---|---|---|
| `SavingsGoalManagerTest::test_savings_index_loads:39` | `No savings goals yet` | `No saving goals yet` (`savings/index.blade.php:47`) |
| `MultiTripHubTest::test_empty_state_shown_when_no_trips:139` | `No trips planned yet` | `No trips yet` (`multi-trip-hub.blade.php:24`) |

Note `savings/index.blade.php` is internally inconsistent: the heading says
"saving goals", the paragraph below says "savings goals".

---

## 6. Regression checklist

The manual pass, in priority order. Everything here is uncovered by automation.

**Trip Planner**

- [ ] Multi-city, end to end: two destinations, both legs of flights, hotels,
      dining and attractions, both itinerary legs, then save. Check the
      itinerary rows land in the right date ranges.
- [ ] From each leg-2 step, press Back. Confirm you land on the right leg (P2).
- [ ] Flight search → confirm modal → Cancel → re-pick. Then let results go
      stale and pick an old index.
- [ ] Set an over-ceiling budget on step 9 and press Confirm Trip. Observe that
      nothing at all happens (P1).
- [ ] Skip accommodation, dining and attractions; save; confirm the itinerary
      is still coherent.
- [ ] Resume a draft via `?draft=`, and enter via the AI handoff to step 6.

**Saved Trips**

- [ ] Share a trip from account A, import it into account B by link and by
      pasted code. Compare the two error messages for your own code (T11).
- [ ] Check the imported trip's currency display and its Emergency Fund row (T5).
- [ ] As a group member, click Share, Edit and Delete. Confirm all three do
      nothing and say nothing (T4).
- [ ] In the Hub, select two trips, then type in the search box (T1).
- [ ] Rename a group trip and check whether other members' savings goals were
      renamed too (S6).
- [ ] Remove a saved member, then press Cancel (T3).

**Saving Goals**

- [ ] Delete a goal, reload `/savings`, watch it come back (S2).
- [ ] Edit a goal's Target Amount on a trip that has a `total_cost`; confirm
      the card does not change (S5).
- [ ] As a group member on a priced trip, check whether your target is the
      share or the whole cost (§4.2).
- [ ] With a non-PHP registration country and the rate API unreachable, make a
      deposit and check what was stored (S3).
- [ ] `PUT /savings/{goal}` with another user's `trip_id` (S1).
