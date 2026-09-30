# Budgetra Study Guide

Budgetra is a Laravel 13/PHP application using Blade and Livewire 4. This file separates the application by feature and identifies each feature's routes, frontend, backend, and functions.

## How the application works

A conventional page flows as **route -> controller -> Blade view -> model/service**. A Livewire page flows as **route -> Livewire PHP component <-> Livewire Blade view -> model/service**. In a Livewire view, `wire:click`, `wire:submit`, and `wire:model` invoke or synchronize public PHP component methods—no one-off controller route is required for every click.

```php
// routes/web.php
Route::middleware(['auth', 'not-admin'])->group(function () {
    Route::get('/dashboard', [Traveler\DashboardController::class, '__invoke'])
        ->name('dashboard');
});
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [Admin\DashboardController::class, '__invoke'])
        ->name('dashboard');
});
``
Every traveler URL is behind `auth` and `not-admin`. Every admin URL is
behind `auth` and `admin`. `EnsureUserIsNotBanned` is appended to the web
middleware group in `bootstrap/app.php`.

## 1. Authentication

**Routes:** `GET /login` (`login`), `POST /login`, `GET /register`
(`register`), `POST /register`, `POST /logout` (`logout`).

**Frontend:** `resources/views/auth/login.blade.php`,
`resources/views/auth/register.blade.php`, and `layouts/guest.blade.php`.

```blade
<form method="POST" action="{{ url('/login') }}">
    @csrf
    <input name="email" type="email">
    <input name="password" type="password">
</form>
```

**Backend:** `LoginController` and `RegisterController`.

- `showForm()` returns the respective form.
- `login(Request)` validates credentials, authenticates, and redirects.
- `logout(Request)` ends the session.
- `store(Request)` validates new-user data, creates the user, and starts a session.

## 2. Dashboard and PDF reports

**Routes:** `GET /dashboard` (`dashboard`), `GET /dashboard/report`
(`dashboard.report`), `GET /reports` (`reports.index`), and
`GET /reports/download?trip_id={id}` (`reports.download`).

**Frontend:** `traveler/dashboard/index.blade.php`,
`traveler/dashboard/report-pdf.blade.php`, `traveler/reports/index.blade.php`,
and `traveler/reports/pdf.blade.php`.

**Backend:** `Traveler/DashboardController.php`,
`Traveler/ReportController.php`, `Services/BudgetService.php`, and
`Services/ReportService.php`.

```php
// BudgetService::summary
public function summary(Trip $trip): array
{
    $budgets = $trip->budgets;
    $totalEstimated = $budgets->sum('estimated_cost');
    $totalSpent = $budgets->sum('actual_spent');

    return [
        'total_estimated' => $totalEstimated,
        'total_spent' => $totalSpent,
        'remaining' => $totalEstimated - $totalSpent,
    ];
}
```

- `DashboardController::__invoke()` renders the dashboard.
- `buildData($user)` assembles trip, spending, and status data.
- `categoryChips($activeTrips)` prepares category display values.
- `downloadReport()` produces the dashboard PDF.
- `ReportController::index(BudgetService)` produces a budget summary per trip.
- `download(Request, ReportService)` validates the trip, authorizes
  `canAccessTrip()`, generates the PDF, and sends the download.
- `BudgetService::summary(Trip)` calculates estimated, spent, remaining, and
  per-category values.

## 3. Guided planner and AI itinerary

**Routes:** `GET /trips` (`trips.index`) and `GET /trips/plan` (`trips.plan`)
mount `TripPlannerWizard`. `GET /trips/plan/ai` (`trips.plan.ai`) mounts `Llm`.

**Frontend:** `resources/views/livewire/traveler/trip-planner-wizard.blade.php`
and `llm.blade.php`. These views conditionally render a current wizard step.

```blade
<button wire:click="selectPlanningMode('manual')">Plan manually</button>
<form wire:submit="generateItinerary"> ... </form>
```

**Backend:** `app/Livewire/Traveler/TripPlannerWizard.php` and `Llm.php`.
Provider services: `SerpApiService`, `SerperService`,
`CurrencyConverterService`, `GeminiService`, `GroqService`,
`CerebrasService`, `MistralService`, and `OpenRouterService`.

| Function group | Functions | Explanation |
| --- | --- | --- |
| Initial/drafts | `mount`, `startNewTrip`, `startFromEmpty`, `getMyTripsProperty`, `confirmDelete`, `cancelDelete`, `deleteTrip` | Initializes state, lists drafts, and safely deletes one. |
| Trip basics | `selectPlanningMode`, `selectScope`, `selectDestination`, `selectDay`, `selectGroup`, `incrementTravelers`, `decrementTravelers`, `selectBudgetTier`, `calculateAndProceed` | Captures type, scope, dates, party, and budget. |
| Flights | `iataCode`, `searchManualFlights`, `directOnly`, `selectFlight`, `swapCities` | Resolves codes, fetches/filters flights, selects one, and swaps cities. |
| Stays/food/places | `searchAccommodations`, `selectAccommodation`, `searchVenues`, `toggleVenue`, `searchAttractionsList`, `toggleAttraction` | Retrieves options and maintains selections. |
| Currency | `budgetCurrency`, `displayCurrency`, `tripDisplayAmount`, `acceptCurrencyConversion`, `declineCurrencyConversion`, `budgetInPesos`, `confirmEmergencyFund` | Renders currency and normalizes budget comparison. |
| AI itinerary | `suggestItinerary`, `generateItinerary`, `runItineraryGeneration`, `selectItineraryOption`, `regenerateItinerary` | Uses provider fallback, presents options, saves/regenerates choice. |
| Finish | `goToSummary`, `categorizeAiCost`, `downloadPdf`, `saveItinerary`, `autosaveDraft`, `render` | Shows totals, exports PDF, persists the trip/draft, renders UI. |

`Llm::automateTrip()` handles conversational trip input, `tryProviders()`
runs its AI fallback strategy, and `autosaveDraft()` persists conversation.
The profile-conversation helper methods collect preferences from messages.

## 4. Traditional trips, estimates, and trip dashboard

| Verb | URI | Name | Function |
| --- | --- | --- | --- |
| GET | `/trips/type`, `/trips/create` | `trips.type`, `trips.create` | `type`, `create` |
| POST | `/trips` | `trips.store` | `store` |
| GET/PUT/DELETE | `/trips/{trip}` | `trips.show`, `trips.update`, `trips.destroy` | `show`, `update`, `destroy` |
| GET/POST | `/trips/{trip}/budget` | `trips.budget`, `trips.budgetStore` | `budget`, `budgetStore` |
| GET/POST | `/trips/{trip}/estimate` | `trips.estimate`, `trips.applyEstimates` | `estimate`, `applyEstimates` |
| GET | `/trips/{trip}/dashboard` | `trips.dashboard` | `TripDashboard` |

**Frontend:** `resources/views/traveler/trips/{index,type,create,show,edit,budget,estimate}.blade.php`
and `resources/views/livewire/traveler/trip-dashboard.blade.php`.

```php
// TripController::budgetStore
foreach ($validated['estimated_cost'] as $category => $amount) {
    $trip->budgets()->updateOrCreate(
        ['category' => $category],
        ['estimated_cost' => $amount]
    );
}
```

**Backend functions:** `index`, `type`, and `create` return views. `store`
validates/creates an owned trip. `show` uses `BudgetService`. `edit`/`update`
manage a trip, and `destroy` deletes it. `estimate` applies destination
multiplier, dates, travelers, and daily base costs. `applyEstimates` delegates
to `budgetStore`, which upserts category estimates.

`TripDashboard::mount(Trip)` loads state. Its `getTotalSpentProperty`,
`getRemainingProperty`, `getSpentPctProperty`, `getDaysProperty`,
`getCategorySpendProperty`, `getDailySpendProperty`,
`getBudgetBreakdownProperty`, `getRecentExpensesProperty`, and `render()`
supply the dashboard UI.

## 5. Saved/shared/imported/compared trips

**Routes:** `GET /saved-trips` (`saved-trips`), `GET /multi-trips`
(`multi-trips.index`), `GET /trips/import/{code}` (`trips.import`).

**Frontend:** `livewire/traveler/saved-trips.blade.php` and
`multi-trip-hub.blade.php`.

**Backend:** `SavedTrips.php`, `MultiTripHub.php`,
`TripImportController.php`, `TripImportService.php`.

- `showDetail`/`closeDetail` control a detail modal.
- `openEditName`, `lookupMember`, `removePendingMember`,
  `removeSavedMember`, `saveEditName` manage trip collaborators.
- `openShare`/`closeShareModal` expose sharing UI.
- `confirmDelete`, `cancelDelete`, `deleteTrip` protect deletion.
- `MultiTripHub::toggleCompare`, `runComparison`, `closeComparison`, and
  `clearCompareSelection` manage cross-trip comparison.
- `TripImportController::import()` calls `TripImportService`.
- `isShareable`, `shareCodeFor`, `findByCode`, `import`, `totalCost`, and
  `buildSummaryData` authorize, locate, clone, calculate, and summarize a trip.

## 6. Expense tracker and receipt OCR

**Routes:** `GET /expenses` (`expenses.index`), `GET /expenses/create`,
`POST /expenses`, `GET /expenses/{expense}/edit`, `PUT /expenses/{expense}`,
`DELETE /expenses/{expense}`, and `POST /expenses/ocr` (`expenses.ocr`).

**Frontend:** `traveler/expenses/index.blade.php`, `create.blade.php`, and
`edit.blade.php`. Forms choose trip/category/currency, upload receipts, and
call OCR to prefill inputs.

```php
// ExpenseController::resolveAmountInPesos
if ($code === 'PHP') {
    $validated['amount_original'] = null;
    return $validated;
}
$rate = (new CurrencyConverterService())->rateToPhp($code);
if ($rate === null) throw CurrencyUnavailable::for($code);
$validated['amount'] = round($typed * $rate, 2);
$validated['amount_original'] = $typed;
$validated['amount_currency'] = $code;
```

**Backend:** `ExpenseController.php`, `OcrService.php`,
`CurrencyConverterService.php`, `ExpenseObserver.php`.

- `currencyCodes()` returns allowed codes.
- `resolveAmountInPesos()` preserves typed original amount/currency while
  storing a canonical PHP amount.
- `defaultCurrencyForTrip()` chooses the destination currency for the form.
- `index()` scopes, filters, and paginates accessible-trip ledger rows.
- `create()` supplies trips/categories/default currency.
- `normaliseAmount()` removes separator commas.
- `store()` validates, authorizes, converts, uploads, creates, and syncs budget.
- `edit()`/`update()` authorize and safely replace receipt files.
- `destroy()` removes receipt storage and the row.
- `ocr()` validates the receipt, calls `OcrService::scan` with trip currency
  context, and returns JSON.

## 7. Savings goals

**Routes:** `GET /savings` (`savings.index`), `GET /savings/create`,
`POST /savings`, `GET /savings/{goal}/edit`, `PUT /savings/{goal}`, and
`DELETE /savings/{goal}`.

**Frontend:** `traveler/savings/{index,create,edit}.blade.php` and
`livewire/traveler/savings-goal-manager.blade.php`.

**Backend:** `SavingsGoalController.php` and `SavingsGoalManager.php`.

`SavingsGoalController::index()` creates missing per-user goals for accessible
trips and divides group cost into the traveler’s share. `create`, `store`,
`edit`, `update`, and `destroy` are ownership-checked CRUD.

`SavingsGoalManager::openDeposit`/`closeDeposit` and
`openProjection`/`closeProjection` control dialogs. Currency/rate/display
methods format values. `submitDeposit()` records savings. `getPctProperty`,
`getDailyNeededProperty`, `getDaysLeftProperty`, and `getIsCompletedProperty`
are computed UI values.

## 8. Itinerary map, schedule, and moments

**Routes:** `GET /itinerary` (`itinerary.index`), `POST /itinerary`
(`itinerary.store`), `DELETE /itinerary/{item}` (`itinerary.destroy`), and
`GET /moments` (`moments.index`).

**Frontend:** `livewire/traveler/itinerary-manager.blade.php` supplies calendar,
map/pins, and photo dialogs; `moments.blade.php` is the entry page.

**Backend:** `ItineraryController.php`, `ItineraryManager.php`, and
`MomentService.php`.

- `ItineraryController::index`, `store(StoreItineraryItemRequest)`, and
  `destroy(Itinerary)` render/create/delete scheduled items.
- `selectTrip`, `selectDay`, `getEventsProperty`, and `getDayItemsProperty`
  drive schedule display.
- `openAddPinModal`, `openEditPinModal`, `savePin`, `deletePin` manage map
  moments.
- `getMapCenterProperty`, `getOverviewPinsProperty`, and timeline properties
  provide map/timeline data.
- `MomentService::createPin`, `updatePin`, `attachPhotos`, `deletePhoto`,
  `deleteMoment`, `pinToArray`, `existingPhotosArray` encapsulate photo/pin work.

## 9. Destinations, attractions, comparison, and reviews

**Routes:** `GET /destinations`, `GET /destinations/{destination}`,
`GET /attractions`, `GET /attractions/{attraction}`, `GET /compare`.
Reviews: `GET/POST /reviews`, `PUT/DELETE /reviews/{review}`,
`POST /reviews/{review}/flag`, `POST /reviews/{review}/helpful`.

**Frontend:** `traveler/destinations/`, `traveler/attractions/`,
`traveler/comparison/index.blade.php`, `traveler/reviews/index.blade.php`,
`destination-browser.blade.php`, `attraction-browser.blade.php`.

- Destination and attraction `index`/`show` functions render catalog pages.
  Browser components expose computed destination/country/attraction lists.
- `ComparisonController::index(Request)` prepares side-by-side cost data.
- `ReviewController::index` filters/paginates active reviews. `store`,
  `update`, `destroy` manage the author’s content. `flag` prevents self-flags.
  `markHelpful` ensures one voter has one vote.

```php
$vote = ReviewHelpfulVote::firstOrCreate([
    'review_id' => $review->id, 'user_id' => auth()->id(),
]);
if ($vote->wasRecentlyCreated) $review->increment('helpful_count');
```

## 10. Profile, settings, alerts, and notifications

**Routes:** `GET/PUT /profile`, `GET /profile/setup`; `GET /settings`,
`PATCH /settings/theme`, `PATCH /settings/preferences`,
`PATCH /settings/notifications`; `GET /alerts`,
`PATCH /alerts/read-all`, `PATCH /alerts/{notification}/read`.

**Frontend:** `traveler/profile/edit.blade.php`,
`livewire/traveler/profile-builder.blade.php`,
`traveler/settings/index.blade.php`, `traveler/alerts/index.blade.php`, and
`livewire/traveler/notification-badge.blade.php`.

`ProfileController::edit` redirects a missing profile to setup; `update`
validates name/photo, builds `full_name`, and replaces stored photo.
`ProfileBuilder::nextStep`/`prevStep` navigate setup; selection/toggle methods
collect styles, transport, accommodation, group, interests, and subinterests;
`persistProfile`, `confirmProfile`, `saveAndReturn` persist it.
`SettingsController::edit`, `updateTheme`, `updatePreferences`, and
`updateNotifications` manage settings. `AlertController::index`, `markRead`,
and `markAllRead` list/update only the signed-in user’s notifications.

## 11. Administrator features

All URLs are prefixed `/admin`, named `admin.*`, and require `auth` + `admin`.

| Feature | Routes/functions | Frontend |
| --- | --- | --- |
| Dashboard | `GET /` and `/dashboard`; `DashboardController::__invoke` plus range/metric helpers | `admin/dashboard.blade.php` |
| Users | `GET /users`, export, show, ban, delete; `index`, `export`, `show`, `ban`, `destroy` | `admin/users/`, `livewire/admin/user-table.blade.php` |
| Destinations | resource index/update/destroy + image fetch | `admin/destinations/index.blade.php`, `destination-table.blade.php` |
| Attractions | resource index/store/update/destroy + image fetch | `attraction-table.blade.php` |
| Travel costs | resource except show; `index/create/store/edit/update/destroy` | `admin/travel-costs/` |
| Review moderation | list, hide/show/unflag/delete | `admin/reviews/index.blade.php`, `review-table.blade.php` |
| Config/OCR | `GET/POST /config`, `GET /ocr-logs`; `index/store/ocrLogs` | `admin/config/index.blade.php`, `ocr-monitor.blade.php` |
| Reports/backup | reports, backup, download, restore | `admin/reports/index.blade.php` |
| Admin profile/settings | profile `edit/update` and settings `edit` | `admin/profile/edit.blade.php` |

`PlaceImageService::fetchForDestination` and `fetchForAttraction` retrieve
catalog imagery. `BackupController::download()` builds a backup;
`restore(Request)` validates/restores one, so it is a high-risk admin action.

## 12. Data model to memorize

```text
User 1---* Trip 1---* TripBudget
  |          |  \---* Expense
  |          |  \---* Itinerary
  |          |  \---* SavingsGoal
  |          \---* Moment ---* MomentPhoto
  |---1 UserProfile
  \---* Review ---* ReviewHelpfulVote
```

`User::accessibleTrips()` returns owned/shared trips and
`User::canAccessTrip($id)` protects shared-trip access.
`Trip::resolvedStatus()` derives status. Other model methods define Eloquent
relations consumed by the controller and Livewire features above.

## Suggested study order

1. `routes/web.php`, layouts, and middleware.
2. A conventional flow: `ExpenseController` plus expense views.
3. `Trip`, `Expense`, `TripBudget`, and `BudgetService`.
4. `TripPlannerWizard.php` alongside its Blade view.
5. Admin routes/controllers.

**Route note:** `routes/admin.php` exists but is not registered in
`bootstrap/app.php`. The active `/admin` routes are in `routes/web.php`.

