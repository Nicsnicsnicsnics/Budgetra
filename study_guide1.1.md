# Budgetra Study Guide v1.1

Budgetra is a **Laravel 13 + Livewire 4** travel-budgeting app (PHP 8.3, PostgreSQL on
Supabase, `barryvdh/laravel-dompdf` for PDFs, Alpine.js + Leaflet + FullCalendar on the
front end). A traveller plans a trip — flights, stays, food, attractions — against a real
budget, optionally fills gaps with AI-generated day-by-day itineraries, then tracks actual
spending against the plan.

This guide walks the app **one feature at a time**. For each feature you get: its routes, the
backend files with every function explained, the frontend files with the important markup /
JS explained, annotated code excerpts for the tricky parts, the data it touches, and the
gotchas baked into the code.

> Companion file: `STUDY_GUIDE.md` is a one-page summary. **This** file (`study_guide1.1.md`)
> is the deep version. Read section 0 first — every feature after it depends on those
> concepts.

---

## How to use this guide

- **Section 0** is the mental model: how a request flows, how auth/middleware works, and the
  single most important rule in the app — *every money column is stored in pesos*.
- **Sections 1–11** are the features. Each one is self-contained and follows the same shape:
  *What it does → Routes → Backend files → Frontend files → Backend walkthrough →
  Frontend walkthrough → Data touched → Gotchas.*
- **Section 12** is a model-by-model data reference.
- **Section 13** lists dead code and traps so you don't waste time on them.
- **Appendix A** is the complete route table; **Appendix B** is a suggested reading order.

File paths are given from the repo root (`C:\jspe1ab13\phpsite\Budgetra\`).

---

## 0. Architecture & cross-cutting concepts

### 0.1 Two kinds of page

| Flow | Shape | Example |
| --- | --- | --- |
| **Conventional** | `route → Controller method → Blade view → Model/Service` | `GET /expenses` → `ExpenseController@index` → `traveler/expenses/index.blade.php` |
| **Livewire** | `route → Livewire PHP component ↔ Livewire Blade view → Model/Service` | `GET /trips/plan` → `TripPlannerWizard` ↔ `livewire/traveler/trip-planner-wizard.blade.php` |

In a Livewire view there is **no per-click controller route**. `wire:click="foo"`,
`wire:submit="bar"`, and `wire:model="baz"` call/synchronise **public methods and public
properties on the component class** over a background AJAX round-trip. A "computed property"
is a method named `getXxxProperty()` (or annotated `#[Computed]`) that the view reads as
`$this->xxx` / `$xxx`.

Routes wire a Livewire component directly:

```php
// routes/web.php
Route::get('/trips/plan', \App\Livewire\Traveler\TripPlannerWizard::class)->name('trips.plan');
```

A Livewire component picks its layout with an attribute:

```php
#[Layout('layouts.app', ['active' => 'trips'])]   // 'active' highlights the sidebar item
class TripPlannerWizard extends Component { /* ... */ }
```

### 0.2 Routing & middleware

All routes live in **`routes/web.php`**. (`routes/admin.php` exists but is **never loaded** —
see §13.) `routes/console.php` holds the scheduler.

`bootstrap/app.php` wires three custom middlewares:

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'admin'     => \App\Http\Middleware\AdminMiddleware::class,
        'not-admin' => \App\Http\Middleware\RedirectAdminFromTraveler::class,
    ]);
    $middleware->appendToGroup('web', \App\Http\Middleware\EnsureUserIsNotBanned::class);
})
```

| Middleware | File | What it does |
| --- | --- | --- |
| `admin` | `app/Http/Middleware/AdminMiddleware.php` | `abort(403)` unless `auth()->user()?->role === 'admin'`. Guards the whole `/admin` group. |
| `not-admin` | `app/Http/Middleware/RedirectAdminFromTraveler.php` | If the user is an admin, `redirect()->route('admin.dashboard')` — admins never see the traveller shell. Guards the whole traveller group. |
| `EnsureUserIsNotBanned` | `app/Http/Middleware/EnsureUserIsNotBanned.php` | Runs on **every** web request. If `auth()->user()->role === 'banned'`, logs them out, invalidates the session, and bounces to `login` with an error. Catches a user who was banned *while logged in*. |

```php
// app/Http/Middleware/EnsureUserIsNotBanned.php
public function handle(Request $request, Closure $next): Response
{
    if (auth()->check() && auth()->user()->role === 'banned') {
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login')->withErrors([
            'email' => 'Your account has been suspended. Contact support if you believe this is a mistake.',
        ]);
    }
    return $next($request);
}
```

Route groups in `routes/web.php`:

```php
Route::middleware('guest')->group(function () { /* login, register */ });
Route::post('/logout', ...)->middleware('auth');
Route::middleware(['auth', 'not-admin'])->group(function () { /* every traveller route */ });
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () { /* every admin route */ });
```

`role` values seen in code: `traveler`, `admin`, `banned`.

### 0.3 The money model — READ THIS

**Every money column in the database is pesos.** `expenses.amount`, `trips.budget_limit`,
`trips.total_cost`, `savings_goals.*`, `user_profiles.daily_budget` — all pesos.

```php
// app/helpers.php  (autoloaded via composer.json "files")
function currency_symbol(): string { return '₱'; }
function currency_code(): string   { return 'PHP'; }
```

There *used to* be a per-account currency setting that only relabelled peso figures without
converting anything (so a user set to "$" typed `100` for a $100 dinner and the app stored
`₱100`, then fed that into budget alerts). It was removed. Two *real* currency concepts remain:

| Concept | Function | Meaning |
| --- | --- | --- |
| **Ledger currency** | `currency_code()` → always `PHP` | What everything is stored/compared in. |
| **Home currency** | `home_currency()` / `home_currency_symbol()` | The traveller's own currency, from their registration `country` via `PlaceCatalog::COUNTRY_CURRENCIES`. A Canadian *plans* in CAD; Budgetra *stores* pesos. |
| **Destination currency** | `trips.destination_currency` + a live rate | How an Upcoming/Ongoing trip's money is *displayed* on cards. |

Live rates come from **`app/Services/CurrencyConverterService.php`**. There is **no hardcoded
rate table anywhere** — if the provider is unreachable the caller is told `null` and must
decide (show pesos, or refuse to save). Callers must **never** treat a foreign amount as
pesos.

```php
// app/Services/CurrencyConverterService.php
public function rateToPhp(string $code): ?float
{
    if (empty($this->key) || $code === 'PHP') return null;

    if (array_key_exists($code, self::$memo)) return self::$memo[$code];       // per-request memo

    $cacheKey = "currency_rate_{$code}_php";
    $cached = Cache::get($cacheKey);
    if ($cached !== null) return self::$memo[$code] = $cached;                  // 6h cache

    if (Cache::get("{$cacheKey}_miss") !== null) return self::$memo[$code] = null;  // 5min miss-cache

    try {
        $response = Http::timeout(4)->get('https://api.twelvedata.com/exchange_rate', [
            'symbol' => "{$code}/PHP",
            'apikey' => $this->key,
        ]);
    } catch (\Throwable) {
        return $this->rememberMiss($code, $cacheKey);
    }
    if (!$response->successful()) return $this->rememberMiss($code, $cacheKey);

    $rate = $response->json('rate');
    if (!is_numeric($rate) || $rate <= 0) return $this->rememberMiss($code, $cacheKey);

    Cache::put($cacheKey, (float) $rate, now()->addHours(6));
    return self::$memo[$code] = (float) $rate;
}
```

- **`self::$memo`** is a *static* array — a fresh `CurrencyConverterService` is `new`-ed at
  nearly every call site, and the trip-planner blade alone calls `tripDisplayAmount()` 26
  times per render. The memo collapses all of those to one cache round-trip.
- **Miss-cache**: a provider outage used to cost one 10-second request per call. Failures are
  cached for 5 minutes so a recovered provider is picked up quickly but an outage doesn't
  stall every page.
- `forgetMemo()` exists only for tests (multiple provider states in one process).

`app/Exceptions/CurrencyUnavailable.php` — `CurrencyUnavailable::for($code)` — is thrown by
`ExpenseController` when it cannot get a rate for a typed foreign amount.

### 0.4 `app/helpers.php` — global helpers used app-wide

| Function | Purpose |
| --- | --- |
| `currency_symbol()` / `currency_code()` | Always `₱` / `PHP`. Kept as functions because ~40 call sites use them. |
| `home_currency()` / `home_currency_symbol()` | Traveller's own currency from `auth()->user()->country`. Falls back to `PHP` / the bare code, **never** to `₱` — labelling a CAD field with a peso sign is the exact bug this prevents. |
| `place_with_country(?string)` | `"Toronto, Canada"` for display; wrapper over `PlaceCatalog::withCountry()`. |
| `display_tz()` / `local_time($date)` | Timestamps are stored UTC; convert to `Asia/Manila` (config `app.display_timezone`) on the way out. |
| `meter_color(float $percent, string $kind = 'spend')` | CSS var for a progress meter. `spend` runs teal→amber→red as it fills (more = worse). `progress` runs the opposite way (more = better). Not clamped for `spend` so over-budget stays red. |
| `trip_apply_display_currency($trip)` | Hangs `display_currency_code` / `display_currency_symbol` / `display_rate` off a Trip model. Only for `active`/`upcoming` trips *with* a reachable live rate; Draft/Past stay in pesos. |
| `trip_amount($trip, float $pesoAmount, int $decimals = 0)` | Formats a peso amount for a trip card — converted when `display_rate` is set, plain `₱` otherwise. |
| `trip_display_name($trip)` | A trip's card title: `trip_name` (user-set) ?: destination (unless literally `"Draft"`) ?: `"No destination set"`. |

```php
// app/helpers.php — the currency-display decision, shared by Saved Trips AND the dashboard
function trip_apply_display_currency($trip): void
{
    $trip->setAttribute('display_currency_code', null);
    $trip->setAttribute('display_currency_symbol', null);
    $trip->setAttribute('display_rate', null);

    if (! in_array($trip->status, ['active', 'upcoming'], true) || ! $trip->destination_currency) {
        return;
    }
    $rate = (new \App\Services\CurrencyConverterService())->rateToPhp($trip->destination_currency);
    if ($rate === null) return;   // no live rate → stay in pesos, don't error on a passive card

    $trip->setAttribute('display_currency_code', $trip->destination_currency);
    $trip->setAttribute('display_currency_symbol',
        \App\Support\PlaceCatalog::CURRENCY_SYMBOLS[$trip->destination_currency] ?? $trip->destination_currency);
    $trip->setAttribute('display_rate', $rate);
}
```

### 0.5 Support catalogs (pure static data, no behaviour)

- **`app/Support/PlaceCatalog.php`** — `IATA_CODES` (city → airport code), `DESTINATION_CURRENCIES`
  (city → ISO currency), `DESTINATION_COUNTRIES`, `COUNTRY_CURRENCIES` (country → ISO),
  `CURRENCY_SYMBOLS`, `CURRENCY_NAMES`, `PACKAGE_DATA` (synthetic per-city trip package used as
  a fallback when the search APIs return nothing). Methods: `originCountryFor(?string)`,
  `cityOptionsFor(?string $country)` (the grouped Local/International city list the planner's
  From/To dropdowns are built from — "Local" means the traveller's *own* country),
  `countryFor(?string)`, `withCountry(?string)`.
- **`app/Support/ProfileCatalog.php`** — `INTERESTS` (9 categories → sub-interests), `ICONS`,
  `IMAGES`, `TRAVEL_STYLES` (Solo/Group), `TRANSPORTATION_OPTIONS`, `ACCOMMODATION_OPTIONS`,
  plus `*_IMAGES`. `ProfileBuilder` re-exports these as class consts so `profile/edit.blade.php`
  can read `ProfileBuilder::ICONS` directly. The lists live here (not in `ProfileBuilder`) so
  the AI planner can resolve a free-text answer ("beach stuff") against the *same* canonical
  labels the form offers as buttons.

### 0.6 Observers, provider, scheduler

`app/Providers/AppServiceProvider.php`:

```php
public function register(): void
{
    // Teaches Laravel that Supabase's pooled-backend terminations are retryable, not fatal.
    $this->app->singleton(LostConnectionDetectorContract::class, ResilientLostConnectionDetector::class);
}
public function boot(): void
{
    \App\Models\Expense::observe(\App\Observers\ExpenseObserver::class);
    \App\Models\Trip::observe(\App\Observers\TripObserver::class);
}
```

- **`ExpenseObserver`** keeps `trip_budgets.actual_spent` in sync and fires budget-alert
  notifications (§6).
- **`TripObserver`** creates a "trip saved" notification (not for drafts), mirrors the
  destination into the `destinations` table, and deletes uploaded files before a trip's DB
  rows cascade away (§4 / §12).
- **`app/Database/ResilientLostConnectionDetector.php`** — Supabase retry logic.

`routes/console.php`:

```php
Schedule::command('app:send-trip-reminders')->daily();       // 3 days before a trip starts
Schedule::command('app:send-itinerary-reminders')->hourly(); // 1–2 h before an itinerary stop
Schedule::command('app:fill-destination-images')->daily();
Schedule::command('app:fill-attraction-images')->daily();
```

Commands live in `app/Console/Commands/` (`SendTripReminders`, `SendItineraryReminders`,
`FillDestinationImages`, `FillAttractionImages`, `BackfillItineraryNotes`).

### 0.7 Layouts & shells

| Layout | File | Used by |
| --- | --- | --- |
| `layouts.app` | `resources/views/layouts/app.blade.php` | Every traveller page. Renders `<x-sidebar :active>`, loads `css/style.css` + `js/basemap.js`, `@livewireStyles/Scripts`, and a `<head>` script that applies the "skip profile setup" localStorage flag before paint. Reads `auth()->user()->theme` onto `data-theme` (`auto` resolves via `prefers-color-scheme`). |
| `layouts.admin` | `resources/views/layouts/admin.blade.php` | Every admin page. Renders `<x-admin-sidebar :active>`. |
| `layouts.guest` | `resources/views/layouts/guest.blade.php` | Bare auth shell. |

Shared components: `components/sidebar.blade.php` (traveller nav; embeds
`<livewire:traveler.notification-badge/>`), `components/admin-sidebar.blade.php`,
`components/modal.blade.php`, `components/stat-card.blade.php`.

---

## 1. Authentication

### What it does

Email/password login and registration. New users are always `role = 'traveler'`. Login
routes admins to the admin dashboard and travellers to the planner; banned users are refused
at the door and, if already logged in, on their next request.

### Routes

| Verb | URI | Name | Handler |
| --- | --- | --- | --- |
| GET | `/` | — | closure: redirect `/dashboard` if authed, else `view('welcome')` |
| GET | `/features` | `features` | closure: `welcome` with `scrollToFeatures` |
| GET | `/login` | `login` | `Auth\LoginController@showForm` |
| POST | `/login` | — | `Auth\LoginController@login` |
| GET | `/register` | `register` | `Auth\RegisterController@showForm` |
| POST | `/register` | — | `Auth\RegisterController@store` |
| POST | `/logout` | `logout` | `Auth\LoginController@logout` (middleware `auth`) |

The login/register GET+POST routes are inside `Route::middleware('guest')` — an already-logged-in
user visiting them is redirected away.

### Backend files

- `app/Http/Controllers/Auth/LoginController.php`
- `app/Http/Controllers/Auth/RegisterController.php`
- `app/Http/Controllers/Controller.php` — empty base class.
- `app/Models/User.php` (§12).

### Frontend files

- `resources/views/auth/login.blade.php` — split-panel form, its own `<html>` (not the guest
  layout), posts to `route('login')`.
- `resources/views/auth/register.blade.php` — first/last name, email, password + confirm,
  country dropdown.
- `resources/views/welcome.blade.php` — marketing landing page.
- `resources/views/layouts/guest.blade.php`.

### Backend walkthrough

**`LoginController`**

- `showForm()` — returns `auth.login`.
- `login(Request $request)`:
  ```php
  $credentials = $request->validate(['email' => 'required|email', 'password' => 'required|string']);

  if (auth()->attempt($credentials, $request->boolean('remember'))) {
      if (auth()->user()->role === 'banned') {
          auth()->logout();
          return back()->withErrors(['email' => 'Your account has been suspended. ...'])
                       ->withInput($request->only('email'));
      }
      $request->session()->regenerate();
      $request->session()->put('collapse_sidebar', true);         // first view starts collapsed
      if (auth()->user()->role === 'admin') {
          return redirect()->route('admin.dashboard');
      }
      return redirect()->intended(route('trips.index'));
  }
  return back()->withErrors(['email' => 'These credentials do not match our records.'])
               ->withInput($request->only('email'));
  ```
  Note: the banned check is *after* a successful password match, so the message only shows to
  someone who actually owns the account.
- `logout(Request $request)` — `auth()->logout()`, `session()->invalidate()`,
  `session()->regenerateToken()`, redirect `login`.

**`RegisterController`**

- `showForm()` — returns `auth.register`.
- `store(Request $request)`:
  ```php
  $validated = $request->validate([
      'first_name' => 'required|string|max:100',
      'last_name'  => 'required|string|max:100',
      'email'      => 'required|email|max:255|unique:users,email',
      'password'   => 'required|string|min:8|confirmed',
      'country'    => 'nullable|string|max:255',
  ]);
  $user = User::create([
      'first_name' => $validated['first_name'],
      'last_name'  => $validated['last_name'],
      'full_name'  => trim($validated['first_name'].' '.$validated['last_name']),
      'email'      => $validated['email'],
      'password'   => $validated['password'],           // hashed by the model cast
      'country'    => $validated['country'] ?? null,
      'role'       => 'traveler',
  ]);
  return redirect()->route('login');   // no auto-login; user signs in next
  ```
  `country` is nullable on purpose — it only feeds `home_currency()`, which defaults to `PHP`.

### Frontend walkthrough

Plain `<form method="POST">` with `@csrf`. `login.blade.php` shows `$errors->first()` in an
`.alert-danger`. Both views carry their own `<head>` and load `css/style.css` directly (they
don't extend `layouts.app` since the sidebar shouldn't render pre-auth).

### Data touched

Reads/writes `users`. `password` is `'hashed'` in the model cast so `User::create` /
`update` hash automatically.

### Gotchas

- Password is never `Hash::make()`-d in the controller — the `'password' => 'hashed'` cast in
  `User` does it. Double-hashing would break login.
- `session('collapse_sidebar')` is set here and read by the traveller layout on the first
  post-login page.

---

## 2. Traveler Dashboard & PDF Reports

### What it does

The dashboard is the traveller's home page: KPI strip (total budget / projected cost / spent),
a spend-by-category donut, a 6-month spend bar chart, "active trip" stubs styled like boarding
passes, and recommended attractions. It can export itself as a PDF. Separately, **Budget
Reports** lists every trip with an estimated-vs-spent summary and a per-trip PDF download.

### Routes

| Verb | URI | Name | Handler |
| --- | --- | --- | --- |
| GET | `/dashboard` | `dashboard` | `Traveler\DashboardController@__invoke` |
| GET | `/dashboard/report` | `dashboard.report` | `Traveler\DashboardController@downloadReport` |
| GET | `/reports` | `reports.index` | `Traveler\ReportController@index` |
| GET | `/reports/download` | `reports.download` | `Traveler\ReportController@download` (`?trip_id=`) |

### Backend files

- `app/Http/Controllers/Traveler/DashboardController.php` — dashboard + its PDF.
- `app/Http/Controllers/Traveler/ReportController.php` — budget-report list + per-trip PDF.
- `app/Services/BudgetService.php` — `summary(Trip)` estimated/spent/remaining + per-category.
- `app/Services/ReportService.php` — builds the per-trip PDF from `summary_data`.

### Frontend files

- `resources/views/traveler/dashboard/index.blade.php` — the dashboard (charts drawn with
  inline SVG/JS; `.stats-row-4` is a 4-up modifier of the shared 3-up `.stats-row`).
- `resources/views/traveler/dashboard/report-pdf.blade.php` — dashboard PDF template.
- `resources/views/traveler/reports/index.blade.php` — report list.
- `resources/views/traveler/reports/pdf.blade.php` — per-trip budget PDF.
- `resources/views/traveler/reports/trip-plan-pdf.blade.php` — used by the planner's
  `downloadPdf()` (§3), not by this feature.

### Backend walkthrough

**`DashboardController`** (const `CATEGORY_COLORS` — the same category→hex map used by the
itinerary calendar and trip summary, so the donut speaks the same visual language).

- `__invoke()` — top-4 `recommended` attractions by rating; if no user, returns an empty
  scaffold; otherwise `view('traveler.dashboard.index', array_merge($this->buildData($user), compact('recommended')))`.
- `downloadReport()` — `buildData($user)` + `generatedAt`, `Pdf::loadView('traveler.dashboard.report-pdf', $data)`,
  A4 portrait, downloads `dashboard-report-{Y-m-d}.pdf`.
- `buildData($user)` *(private)* — the heart of the page:
  - trips = non-draft `accessibleTrips()` with `withSum('expenses','amount')`, each given
    computed attrs `total_spent`, `pct_used`, and `status` (from `resolved_status`).
    ```php
    $trips = $user->accessibleTrips()
        ->where(fn ($q) => $q->where('status', '!=', 'draft')->orWhereNull('status'))
        ->withSum('expenses', 'amount')->latest()->get()
        ->map(function (Trip $trip) {
            $spent = $trip->expenses_sum_amount ?? 0;
            $trip->setAttribute('total_spent', $spent);
            $trip->setAttribute('pct_used', $trip->budget_limit > 0 ? round($spent / $trip->budget_limit * 100) : 0);
            $trip->setAttribute('status', $trip->resolved_status);
            return $trip;
        });
    ```
    The `orWhereNull('status')` matters: in SQL `NULL != 'draft'` is *unknown*, not true, so a
    real trip that predates a status default would silently vanish without it.
  - `$totalCost = $trips->sum(fn ($t) => (float) ($t->total_cost ?? $t->budget_limit ?? 0))` —
    projected cost, not budget cap. Same fallback the Saved Trips cards and the PDF use, so the
    three agree.
  - `categorySpend` — donut data: one grouped `Expense` query over non-draft trips,
    `category → SUM(amount)`, mapped to `{label,value,color}`.
  - `monthlySpend` — last 6 months of total spend, **bucketed in PHP** (`groupBy(fn($e) => Carbon::parse($e->expense_date)->format('Y-m'))`)
    not by the DB. The old version grouped on `to_char(expense_date,'YYYY-MM')`, which is
    Postgres-only and made the dashboard un-renderable on SQLite (tests).
  - `activeTrips` = trips not `past`/`draft`, sorted by departure, each run through
    `trip_apply_display_currency($t)` so the stubs agree with the Saved Trips cards.
  - `nextDeparture` = first `upcoming` active trip.
  - `tripChips` via `categoryChips($activeTrips)`.
- `categoryChips($activeTrips)` *(private)* — top-3 spend categories **per** trip, in **one**
  grouped query (`Expense::whereIn('trip_id', $ids)->selectRaw('trip_id, category, SUM(amount)...')`)
  rather than one query per card.

**`ReportController`**

- `index(BudgetService $budgetService)` — `$trips = auth()->user()->trips()->with('budgets')->latest()->get()`;
  `$summaries = $trips->mapWithKeys(fn($t) => [$t->id => $budgetService->summary($t)])`;
  view `traveler.reports.index`.
- `download(Request $request, ReportService $reportService)`:
  ```php
  $request->validate(['trip_id' => 'required|exists:trips,id']);
  $trip = Trip::findOrFail($request->trip_id);
  abort_if(!auth()->user()->canAccessTrip((int) $trip->id), 403);   // access, not ownership
  $pdf = $reportService->generatePdf($trip);
  return $pdf->download('budget-report-' . Str::slug($trip->destination) . '-' . now()->format('Y-m-d') . '.pdf');
  ```
  It's `canAccessTrip` (not owner-only) because Saved Trips lists trips shared *with* you and
  each card has a download button.

**`BudgetService`**

```php
public function summary(Trip $trip): array
{
    $budgets = $trip->budgets;
    $totalEstimated = $budgets->sum('estimated_cost');
    $totalSpent     = $budgets->sum('actual_spent');
    return [
        'total_estimated' => $totalEstimated,
        'total_spent'     => $totalSpent,
        'remaining'       => $totalEstimated - $totalSpent,
        'categories'      => $budgets->map(fn($b) => [
            'category' => $b->category, 'estimated_cost' => $b->estimated_cost,
            'actual_spent' => $b->actual_spent, 'remaining' => $b->estimated_cost - $b->actual_spent,
        ])->values()->all(),
    ];
}
```
Reads the `trip_budgets` table. Note that table is only populated for trips whose budget was
set through the *traditional* trip flow (`TripController::budgetStore`, §4) — planner-wizard
trips store their breakdown in `trips.summary_data` instead (see `ReportService` below and §13).

**`ReportService`** (ctor `__construct(private BudgetService $budget)`; const `COST_LABELS`
maps `transportation/accommodation/food/attractions/emergency_fund` to display labels).

- `generatePdf(Trip $trip): \Barryvdh\DomPDF\PDF` — assembles `summary` (`BudgetService`),
  ordered `expenses`, `itinerary` grouped by `Y-m-d`, `costRows`, `totals`; loads
  `traveler.reports.pdf` A4 portrait.
- `costRows(Trip $trip)` *(private)* — the estimated breakdown, **read from `trip->summary_data`**,
  not `trip_budgets`:
  ```php
  // The trip_budgets table is only written by TripPlannerWizard::confirm(), a legacy path
  // no button reaches any more, so it holds no rows and can't back this section.
  $data = $trip->summary_data ?? [];
  foreach (self::COST_LABELS as $key => $label) {
      $row = $data[$key] ?? null;
      $cost = (float) ($row['cost'] ?? 0);
      $detail = $row['detail'] ?? null;
      if (!$cost && !$detail) continue;   // a line the planner never filled → omitted, not shown as ₱0
      $rows[] = ['label' => $label, 'detail' => $detail, 'cost' => $cost, 'extra' => (int) ($row['extra'] ?? 0)];
  }
  ```
- `totals(Trip $trip, float $spent)` *(private)* — `estimated` (`total_cost ?? budget_limit`),
  `budget_limit`, `spent`, `remaining`, `heads` (Solo = 1; Group = `max(num_travelers, members+1)`),
  `per_person`.

### Frontend walkthrough

`dashboard/index.blade.php`: KPI cards use the shared `.stat-card`; the donut and bar chart
are drawn inline from the `categorySpend` / `monthlySpend` arrays (no chart library). The
"active trip" stubs are the boarding-pass cards (`.trip-stub`, a 2-column grid with a
punched-perforation border). PDF templates are plain tables styled for print (dompdf supports
only a CSS subset).

### Data touched

Reads `trips`, `expenses`, `trip_budgets`, `attractions`, `itinerary`. Writes nothing.

### Gotchas

- `buildData()` and `categoryChips()` deliberately keep aggregate queries to a fixed count —
  don't reintroduce per-trip queries in a loop.
- The dashboard donut, the reports list, and the PDF must all agree on "cost" =
  `total_cost ?? budget_limit ?? 0`. Keep the fallback identical if you touch any of them.
- `BudgetService::summary` returns zeros for planner-wizard trips (no `trip_budgets` rows) —
  that's why the per-trip PDF uses `summary_data` instead.

---

## 3. Trip Planner Wizard + AI Itinerary

### What it does

The core of the app. Two entry points to plan a trip:

- **Manual planner** (`TripPlannerWizard`) — a 9-step wizard: trip details → flights →
  accommodation → food → attractions → emergency fund/budget → generate AI itinerary → review
  itinerary options → cost summary → save. Multi-city adds a parallel "leg 2".
- **AI planner "TARA"** (`Llm`) — a chat interface that slot-fills from/to/budget/dates/
  travellers/currency in natural language, builds a full trip package, then hands off to the
  wizard.

Both lean on: external search APIs (SerpApi → Serper fallback) for real flights/hotels/
restaurants/attractions; a chain of AI providers for itineraries; and `CurrencyConverterService`
for any non-peso budget.

### Routes

| Verb | URI | Name | Handler |
| --- | --- | --- | --- |
| GET | `/trips` | `trips.index` | Livewire `TripPlannerWizard` (shows trip list or empty state) |
| GET | `/trips/plan` | `trips.plan` | Livewire `TripPlannerWizard` (fresh wizard) |
| GET | `/trips/plan/ai` | `trips.plan.ai` | Livewire `Llm` |
| GET | `/trips/type` | `trips.type` | `TripController@type` (a static "Solo / Group" chooser page) |

### Backend files

- `app/Livewire/Traveler/TripPlannerWizard.php` (~3,270 lines) — the manual wizard state machine.
- `app/Livewire/Traveler/Llm.php` (~3,790 lines) — the conversational planner.
- `app/Services/GeminiService.php`, `GroqService.php`, `CerebrasService.php`,
  `MistralService.php`, `OpenRouterService.php` — AI providers, uniform interface.
- `app/Services/SerpApiService.php` (~970 lines) — flights/hotels/restaurants/attractions/images
  with a DB cache + 80-calls/day cap.
- `app/Services/SerperService.php` — the fallback search provider.
- `app/Services/KlookService.php` — `getActivities()` (used by `TripController@estimate`, §4).
- `app/Services/CurrencyConverterService.php` (§0.3).
- `app/Support/PlaceCatalog.php` — IATA codes, currencies, `PACKAGE_DATA` synthetic fallback.
- `app/Models/AiConversationDraft.php` (one live draft per user), `AiConversationHistory.php`
  (archived completed conversations), `Trip`, `TripBudget`, `SavingsGoal`, `GroupMember`,
  `Notification`, `Destination`.
- `config/services.php` — provider keys + endpoints.

### Frontend files

- `resources/views/livewire/traveler/trip-planner-wizard.blade.php` (~3,600 lines) — one giant
  `$step`-driven file, heavy Alpine.js. `STEP N` comment banners mark each section:

  | `$step` | Section |
  | --- | --- |
  | 0 | mode select (Manual / AI cards + "have a trip code?") |
  | 1 | Trip details (From / To / Budget / Dates) |
  | 2 | Select flight |
  | 3 | Select accommodation |
  | 4 | Select food & dining (multi-select) |
  | 5 | Select attractions (multi-select) |
  | 6 | Emergency fund / budget confirmation + currency modal |
  | 7 | Generate itinerary |
  | 8 | Itinerary preview (options) |
  | 9 | Trip summary & cost estimation → Save |

- `resources/views/livewire/traveler/llm.blade.php` — the chat UI (`$aiStep`-driven:
  landing → chat → loading → `results`).
- `resources/views/traveler/trips/type.blade.php` — the Solo/Group chooser.
- `resources/views/traveler/reports/trip-plan-pdf.blade.php` — the wizard's PDF export.

### Backend walkthrough — `TripPlannerWizard`

Because the class is 3k lines, methods are grouped. Signatures are as in the source.

**Init / list / drafts**

- `mount()` — sets the calendar to today; seeds Group state from the user's profile; decides
  `showList` vs `showEmpty` (`/trips` shows the list, `/trips/plan` always starts fresh); then
  handles **three mutually-exclusive deep-links**, checked in this order:
  1. `session('wizard_ai_handoff')` — the AI planner's "Next": a full package is pulled from
     the session, `selectedFlight/Hotel/Venues/Attractions` are synthesised from it, and the
     wizard jumps straight to **step 6**. `pull` (one-time) so a refresh behaves normally.
  2. `?draft=<id>` — "Continue Editing" from a Draft card: re-loads that draft Trip row so
     autosave keeps updating it rather than spawning a duplicate. Budget field is re-seeded
     from `budget_local` (what was typed), **not** `budget_limit` (the converted pesos).
  3. `?from=&to=` — the AI planner's "Edit": prefills route/budget/dates and calls
     `proceedFromTripDetails()` to land on flight selection.
- `getMyTripsProperty()` — the user's trips with budget/expense sums for the list view.
- `startNewTrip()`, `startFromEmpty()`, `confirmDelete(int)`, `cancelDelete()`, `deleteTrip()` —
  list-view actions.

**Trip basics**

- `selectPlanningMode(string $mode)` — `'manual'|'ai'`, → step 1.
- `importCode()` — the inline "have a trip code?" field. Looks the code up via
  `TripImportService`, and on any rejection calls `reject($msg)` which sets the error and
  dispatches `code-rejected` (Alpine shakes the field). On success it imports and redirects
  away — control never returns to the wizard.
- `proceedFromTripDetails()` — validates From/To/Budget/Dates; parses the budget field
  (`"min - max"`, `"min to max"`, or a single value); calls `autosaveDraft()`; honours
  `session('ai_edit_section')` (jump to the edited step); → step 2 + `searchManualFlights()`.
- `selectScope(string)`, `selectDestination(int)`, `prevMonth()`, `nextMonth()`,
  `selectDay(string)`, `proceedFromCalendar()`, `selectGroup(string)`, `incrementTravelers()`,
  `decrementTravelers()`, `selectBudgetTier(string)`, `calculateAndProceed()` — the older
  scope/destination/calendar/group/tier flow. Const `RATES` holds the ₱ cost tables
  (local vs international × Shoestring / Mid-range / Luxury).
- `startEditing(string $category)`, `stopEditing()` — inline budget-line editing.

**Flights**

- `searchManualFlights()` — SerpApi first, Serper fallback; multi-city also searches leg 2.
- `directOnly()` — toggles a non-stop filter on the already-fetched results.
- `selectFlight(int $index)` / `confirmFlightPick(int)` / `cancelFlightPick()` — a
  confirm-modal wrapper around picking a flight; multi-city variants `selectMcFlight` etc.
- `swapCities()` — swap From/To.
- `iataCode(string $city)` / static `staticIataCode(string)` — resolve a city to an airport
  code from `PlaceCatalog::IATA_CODES`.
- `updatedFlightTripType()`, `updatedMcTo()`, `updatedStartDate/EndDate/McStartDate/McEndDate()` —
  Livewire lifecycle hooks that re-validate/clear dependent state.

**Stays / food / places**

- `searchAccommodations()` / `selectAccommodation(int)` / `skipAccommodation()` — SerpApi/Serper
  hotels with a `fallbackHotels()` template set.
- `searchVenues()` / `toggleVenue(int)` / `skipVenue()` / `continueFromVenues()` — multi-select
  dining; `#[On('searchMcVenues')] searchMcVenues()` for leg 2.
- `searchAttractionsList()` / `toggleAttraction(int)` / `skipAttraction()` /
  `continueFromAttractions()` — multi-select attractions; `#[On('searchMcAttractions')]` variant.
- `searchHotelResults()` / `searchVenueResults()` / `searchAttractionResults()` +
  `clearHotelSearch()` etc. + `filterResults(array $rows, string $needle)` — client-side
  narrowing of already-fetched result lists.
- `selectedVenuesFlat()`, `selectedAttractionsFlat()`, `selectedVenuesCost()`,
  `selectedAttractionsCost()`, `selectionDayBuckets()` — selection accounting (all sum across
  both legs).
- `openCustomActivityModal()`, `closeCustomActivityModal()`, `addCustomActivity()`,
  `removeCustomActivity(int)` — user-added itinerary items.

**Currency (step 6)**

- `budgetCurrency()` / `budgetCurrencySymbol()` — the traveller's own currency (from country),
  or a currency named inline.
- `displayCurrency()` / `tripDisplayAmount(int|float $pesoAmount)` — render a peso figure in
  the display currency (called ~26× per render — hence the static memo in
  `CurrencyConverterService`).
- `budgetInPesos(float $typed): ?float` — converts a typed budget to pesos; **`null` when no
  live rate**:
  ```php
  private function budgetInPesos(float $typed): ?float
  {
      $code = $this->budgetCurrency();
      if ($code === 'PHP' || $typed <= 0) return $typed;
      $rate = (new CurrencyConverterService())->rateToPhp($code);
      return $rate === null ? null : round($typed * $rate, 2);
  }
  ```
- `confirmEmergencyFund()`, `updatedEmergency()` — the emergency-fund step.
- `acceptCurrencyConversion()` / `declineCurrencyConversion()` — the "show this trip in its
  destination currency?" modal (`showCurrencyConvertModal` / `currencyConversionAsked` — asked
  once; declining is an answer).

**AI itinerary (steps 7–8)**

- `generateItinerary()` — resets leg state, → step 8, calls `runItineraryGeneration($dest, $start, $end)`.
- `generateItineraryLeg2()` — stashes leg 1's chosen option, generates leg 2's.
- `continueItinerary()` — from step 8's "Save Itinerary": for a multi-city trip still on leg 1
  it generates leg 2 instead of proceeding; otherwise it shows the destination-currency modal
  (once) then `goToSummary()`.
- `backToLeg1Itinerary()`, `regenerateItineraryOptions()`, `regenerateItinerary()`,
  `selectItineraryOption(int $index)`, `openBudgetAdjust()`, `applyBudgetAdjust()`.
- `suggestItinerary(array $args, ?float $deadline = null): ?array` — **the provider fallback**:
  ```php
  private function suggestItinerary(array $args, ?float $deadline = null): ?array
  {
      $deadline ??= microtime(true) + 60;
      $needsAccommodation = !$this->selectedHotel && !$this->selectedMcHotel;
      // Mistral / OpenRouter / Groq first — Gemini's project has no free quota (429) and
      // Cerebras's key has no billing (402), so both are pushed to the end.
      foreach ([MistralService::class, OpenRouterService::class, GroqService::class,
                GeminiService::class, CerebrasService::class] as $serviceClass) {
          $remaining = $deadline - microtime(true);
          if ($remaining < 5) break;
          try {
              $result = (new $serviceClass())->suggestAdditionalItinerary(
                  ...$args, needsAccommodation: $needsAccommodation, timeout: (int) min(25, floor($remaining)));
          } catch (\Throwable) { $result = null; }
          if ($result) return $this->applyDepartureCost($result);
      }
      return null;
  }
  ```
  `$deadline` is a `microtime(true)` cutoff for the **entire** call. `generateItinerary()`
  calls this up to 5× (one per option); trying every provider at a fixed 30s timeout per
  option is how a single request used to blow past PHP's 120s `max_execution_time` and fatal
  out. Once the deadline is hit, that option is skipped.
- `applyDepartureCost(array $result): array` — the AI adds a "Head to Airport / Departure"
  entry with cost 0. For a one-way trip it's dropped (no return flight); for a round trip its
  cost becomes half the ticket price (the return leg).
- `categorizeAiCost(array $days): array` — buckets the AI itinerary's per-activity costs by
  type into `accommodation` / `food` / `attractions` so the summary can show how much of each
  came from the AI vs the traveller's own picks.

**Finish / save**

- `goToSummary()`, `backToAttractions()`, `editFromSummary(int $step)` — step 9 navigation.
- `downloadPdf()` — renders `traveler.reports.trip-plan-pdf`.
- `autosaveDraft()` — creates/updates a `status = 'draft'` Trip row whenever From/To/Budget/
  Dates are all filled. Converts the budget to pesos first; if the rate is unreachable it just
  **doesn't save a draft** (a draft is a convenience, not worth interrupting the user):
  ```php
  $budgetCurrency = $this->budgetCurrency();
  $pesoBudget     = $this->budgetInPesos($budget);
  if ($pesoBudget === null || $pesoBudget > self::MAX_PESO_BUDGET) return;
  $data = [ /* ... */ 'budget_limit' => $pesoBudget, 'budget_currency' => $budgetCurrency,
            'budget_local' => $budgetCurrency !== 'PHP' ? $budget : null, 'status' => 'draft' ];
  $existing = $this->draftTripId ? Trip::where('id', $this->draftTripId)->where('user_id', auth()->id())
      ->where('status', 'draft')->first() : null;
  $existing ? $existing->update($data) : ($this->draftTripId = Trip::create($data)->id);
  ```
- `saveItinerary()` — **the real save.** Guards against a double-click creating two Trip rows
  (`if ($this->isSaving) return;`); converts the typed budget to pesos (aborts with a message
  if no rate, or if it exceeds `MAX_PESO_BUDGET` — `trips.budget_limit` is `decimal(10,2)`);
  sums flight/hotel/venue/attraction costs across both legs; runs `categorizeAiCost()`; writes
  the final `Trip` with `summary_data`, the four `*_selection` JSON snapshots, `itinerary`
  rows, a `SavingsGoal`, `GroupMember` rows, and a notification (via `TripObserver`).
- `confirm()` — the **legacy** save path; it's the only writer of the `trip_budgets` table and
  **no live button reaches it** (see §13). `ReportService` reads `summary_data` instead.
- Computeds: `#[Computed] destinations()`, `getDaysProperty()`, `getCalendarDaysProperty()`,
  `getComfortLevelProperty()`, `getSmartTipProperty()`, `getVarianceProperty()`,
  `getTravelersLabelProperty()`.
- `render()` → `livewire.traveler.trip-planner-wizard`.

### Backend walkthrough — `Llm` ("TARA", the AI chat planner)

Conversational slot-filling. State (messages, `ai_from/to`, `ai_budget_min/max`,
`ai_date_from/to`, `ai_days`, `ai_travelers`, `ai_currency`, `awaiting_slot`, `ai_package`,
`ai_step`, profile-draft) is persisted to a single `AiConversationDraft` row per user on
**every** request via `dehydrate()`.

- `mount()` — restores the `AiConversationDraft`.
- History UI: `getConversationHistoryProperty()`, `openHistory()`, `closeHistory()`,
  `viewHistoryEntry(int)`, `backToHistoryList()`, `confirmDeleteHistoryEntry(int)`,
  `cancelDeleteHistoryEntry()`, `deleteHistoryEntry()`.
- `automateTrip()` — **the main message handler.** Appends the user message; handles `/reset`;
  branches into the profile sub-conversation if one is active; otherwise parses the text,
  fills the next missing slot, runs place verification, detects/converts an inline currency,
  and — once every slot is filled — offers to build the package.
- `#[On('ai-process-trip')] processAiTrip()` — builds the actual package (flights/hotel/food/
  attractions) via SerpApi/Serper with `PlaceCatalog::PACKAGE_DATA` as the synthetic fallback;
  helpers `buildSerpApiPackage()`, `capPackageToBudget()`, `matchKnownPlace()`, `aiPlaceFallback()`.
- `showResults()`, `regeneratePackage()`, `proceedToWizardItinerary()` — stashes
  `session('wizard_ai_handoff')` and redirects to `trips.plan`.
- `backToConversation()`, `editWithWizard(?string $section)` — sets `session('ai_edit_section')`
  and redirects to the wizard.
- Profile sub-flow (`pf*` prefix, ~40 private methods): `startProfileConversation(?string $focusSlot)`,
  `pickInterestsInForm()`, `pfApplyAnswer()`, `pfQuestionFor()`, `pfSummary()`,
  `finalizeProfileSave()` (persists through `UserProfileSaver`, the same path `ProfileBuilder`
  uses). A second, parallel slot-filling flow that returns early throughout so the trip state
  is never touched while it runs.
- `tryProviders(\Closure $invoke): mixed` — the AI fallback used for non-itinerary AI calls
  (extraction, place verification, destination suggestion):
  ```php
  private const PROVIDER_ORDER = [
      MistralService::class, OpenRouterService::class, GroqService::class, GeminiService::class,
  ];
  private function tryProviders(\Closure $invoke): mixed
  {
      foreach (self::PROVIDER_ORDER as $class) {
          try { $result = $invoke(new $class()); }
          catch (\Throwable $e) { Log::warning('AI provider failed', [...]); continue; }
          if ($result) return $result;
      }
      return null;
  }
  ```
- `decodeAiJson(?string $raw): ?array` — strips ```` ```json ```` fences, `json_decode`, returns
  array or null.
- `dehydrate()` — `updateOrCreate` the `AiConversationDraft`; also calls private
  `autosaveDraft()` which creates a `status = 'draft'` `Trip` once enough slots are filled.
- `iataCode(string)`, `displayAmount(int|float, ?string $currencyCode)`.
- `render()` → `livewire.traveler.llm`.

### AI provider services (`app/Services/*Service.php`)

All five share `const MAX_AI_ITINERARY_DAYS = 10` and this shape:

| Method | Purpose |
| --- | --- |
| `__construct()` | reads `config('services.<name>.key')` / `.endpoint` |
| `generate(string $prompt, int $timeout = 18): ?string` | raw completion; returns `null` on any HTTP failure (dead key, 429, 402, timeout) |
| `planTrip(string $userPrompt): ?array` | full trip extraction + package (Mistral/OpenRouter/Groq/Gemini) |
| `enrichPackage(array, $destination, $days, $budget): ?array` | fills gaps in a package |
| `suggestAdditionalItinerary(...)` | day-by-day itinerary (all five) |

Extras: `GeminiService::buildItinerary(string $destination, int $days, string $dateFrom, array $attractions)`
and `GeminiService::parseReceipt(string $ocrText): ?array` (used by `OcrService`, §6).
`CerebrasService` only has `generate` + `suggestAdditionalItinerary`.

```php
// config/services.php — note the deliberate "latest" alias
'gemini' => [
    'key'      => env('GEMINI_API_KEY', ''),
    // Deliberately the "latest" alias rather than a pinned version: gemini-2.0-flash was
    // decommissioned and 404'd on every call... The alias tracks whichever flash model is
    // current, so a retirement stops silently killing this link in the fallback chain.
    'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent',
],
```

`GroqService::generate()` retries once on a 429 with a short wait — Groq's free tier
rate-limits bursts and the planner fires several option requests back-to-back.

### Search services

- **`SerpApiService`** — `searchFlights()`, `searchHotels()`, `searchRestaurants()`,
  `searchAttractions()`, `searchPlaceImage(string $name, ?string $context, string $hint)`, each
  with a `*Raw()` sibling. `cachedRequest(array $params)` (private) hashes params (minus the
  API key so rotation doesn't bust the cache), checks `serpapi_cache` (TTL 6–24 h by query
  type), enforces a **global 80 calls/day** cap via `serpapi_usage`, and returns `null` when
  the cap is hit (callers degrade gracefully).
- **`SerperService`** — same four search methods, the fallback provider.
- **`KlookService`** — `getActivities(string $destination, int $limit = 5): array`.

### Frontend walkthrough

`trip-planner-wizard.blade.php` is one file; each step is `@if ($planningMode !== '' && $step === N)`.
Alpine.js holds transient UI state (dropdowns, calendars, the shake animation on empty
required fields); `wire:click` advances steps and `wire:model.live` binds inputs. Required
fields get a red ring + shake via a `profile-missing` / `code-rejected` dispatch pattern shared
with other forms.

`llm.blade.php` is `$aiStep`-driven: a landing prompt, the message thread, a loading state,
and a `results` screen showing the package with a budget meter, "Back to Chat", "Edit", and
"Next" (→ wizard). A floating history button (`wire:click="openHistory"`) is present on every
state except `results`.

### Data touched

Writes `trips` (drafts + the final save), `trip_budgets` (legacy `confirm()` only), `itinerary`,
`savings_goals`, `group_members`, `notifications`, `destinations` (via `TripObserver`),
`ai_conversation_drafts`, `ai_conversation_histories`, `serpapi_cache`, `serpapi_usage`.
Reads `user_profiles`, `destinations`.

### Gotchas

- **Provider order is intentional**: Mistral → OpenRouter → Groq → Gemini → Cerebras. Per the
  code comments Gemini's key 401s / has no free quota and Cerebras has no billing (402); both
  are at the end so they fail fast.
- Everything downstream of the planner is pesos. `saveItinerary()` / `autosaveDraft()` convert
  the typed budget exactly once, and **refuse** rather than guess if there's no rate.
- The `trip_budgets` table is effectively dead here — planner trips store their cost breakdown
  in `trips.summary_data`.
- `SerpApiService` silently returns `null` past 80 calls/day — the wizard then falls back to
  `PlaceCatalog::PACKAGE_DATA` synthetic results.
- The AI itinerary generation has a hard wall-clock deadline; a slow provider gets an option
  skipped, not the whole request killed.

---

## 4. Traditional Trips: create / edit / show / budget / estimate + Trip Dashboard

### What it does

A plain CRUD path for a trip, plus a per-trip budget editor and an auto-estimator, plus a
Livewire **Trip Dashboard** showing spend-vs-budget for one trip. This is the older, simpler
alternative to the planner wizard; the routes still exist and work.

### Routes

| Verb | URI | Name | Handler |
| --- | --- | --- | --- |
| GET | `/trips/type` | `trips.type` | `TripController@type` |
| GET | `/trips/create` | `trips.create` | `TripController@create` |
| POST | `/trips` | `trips.store` | `TripController@store` |
| GET | `/trips/{trip}` | `trips.show` | `TripController@show` |
| GET | `/trips/{trip}/edit` | `trips.edit` | `TripController@edit` |
| PUT | `/trips/{trip}` | `trips.update` | `TripController@update` |
| DELETE | `/trips/{trip}` | `trips.destroy` | `TripController@destroy` |
| GET | `/trips/{trip}/budget` | `trips.budget` | `TripController@budget` |
| POST | `/trips/{trip}/budget` | `trips.budgetStore` | `TripController@budgetStore` |
| GET | `/trips/{trip}/estimate` | `trips.estimate` | `TripController@estimate` |
| POST | `/trips/{trip}/estimate` | `trips.applyEstimates` | `TripController@applyEstimates` |
| GET | `/trips/{trip}/dashboard` | `trips.dashboard` | Livewire `TripDashboard` |

Note: `TripController@index` exists but **no route points to it** (the wizard owns `trips.index`).
`traveler/trips/index.blade.php` embeds `@livewire('traveler.multi-trip-hub')`.

### Backend files

- `app/Http/Controllers/Traveler/TripController.php` — all CRUD + budget + estimate.
- `app/Livewire/Traveler/TripDashboard.php` — per-trip spend dashboard.
- `app/Services/BudgetService.php`, `app/Services/KlookService.php`.
- `app/Models/Trip`, `TripBudget`, `DestinationCost`, `Expense`.
- `app/Observers/TripObserver.php`, `app/Observers/ExpenseObserver.php`.

### Frontend files

- `resources/views/traveler/trips/{type,create,show,edit,budget,estimate}.blade.php`
- `resources/views/livewire/traveler/trip-dashboard.blade.php`

### Backend walkthrough — `TripController`

Every method that touches a specific trip guards ownership with
`abort_if($trip->user_id !== auth()->id(), 403)` — **owner-only** (not `canAccessTrip`).

- `index()` → `traveler.trips.index` (unrouted).
- `type()` → `traveler.trips.type` (Solo/Group chooser).
- `create()` → `traveler.trips.create`.
- `store(Request $request)`:
  ```php
  $validated = $request->validate([
      'destination'   => 'required|string|max:255',
      'start_date'    => 'required|date',
      'end_date'      => 'required|date|after_or_equal:start_date',
      'num_travelers' => 'nullable|integer|min:1|max:50',
      'budget_limit'  => 'nullable|numeric|min:0',
      'travel_type'   => 'required|in:Solo,Group',
      'notes'         => 'nullable|string|max:1000',
  ]);
  $trip = auth()->user()->trips()->create($validated);
  return redirect()->route('trips.show', $trip);
  ```
- `show(Trip $trip, BudgetService $budgetService)` — owner guard; `$summary = $budgetService->summary($trip->load('budgets'))`; view `traveler.trips.show`.
- `edit(Trip $trip)` / `update(Request, Trip $trip)` — owner guard; same validation as `store`.
- `destroy(Trip $trip)` — owner guard; delete; redirect `dashboard`. (File cleanup handled by `TripObserver::deleting`.)
- `estimate(Trip $trip, KlookService $klook)` — owner guard; computes 6 category estimates:
  ```php
  $destInfo   = DestinationCost::where('destination', $trip->destination)->first();
  $multiplier = $destInfo ? (float) $destInfo->multiplier : 1.0;
  $days       = max(1, (int) Carbon::parse($trip->start_date)->diffInDays(Carbon::parse($trip->end_date)));
  $travelers  = max(1, $trip->num_travelers ?? 1);
  $baseDaily  = 2500;
  $categories = [
      'Transportation'      => round($baseDaily * 0.20 * $multiplier * $travelers * $days, 2),
      'Accommodation'       => round($baseDaily * 0.30 * $multiplier * $travelers * $days, 2),
      'Food'                => round($baseDaily * 0.20 * $multiplier * $travelers * $days, 2),
      'Tourist Attractions' => round($baseDaily * 0.15 * $multiplier * $travelers * $days, 2),
      'Shopping'            => round($baseDaily * 0.10 * $multiplier * $travelers * $days, 2),
      'Emergency Funds'     => round($baseDaily * 0.05 * $multiplier * $travelers * $days, 2),
  ];
  $activities = $klook->getActivities($trip->destination);
  return view('traveler.trips.estimate', compact('trip','destInfo','categories','total','activities','days'));
  ```
- `applyEstimates(Request, Trip $trip)` — just `return $this->budgetStore($request, $trip)`.
- `budget(Trip $trip)` — owner guard; 6 fixed categories + existing `trip->budgets` map; view `traveler.trips.budget`.
- `budgetStore(Request $request, Trip $trip)` — owner guard; validates `estimated_cost` (array
  of numerics); upserts one `TripBudget` per category:
  ```php
  foreach ($validated['estimated_cost'] as $category => $amount) {
      $trip->budgets()->updateOrCreate(['category' => $category], ['estimated_cost' => $amount]);
  }
  return redirect()->route('trips.show', $trip)->with('success', 'Budget saved.');
  ```

### Backend walkthrough — `TripDashboard` (Livewire)

```php
#[Layout('layouts.app', ['active' => 'trips'])]
class TripDashboard extends Component
{
    public Trip $trip;

    public function mount(Trip $trip): void
    {
        abort_if($trip->user_id !== auth()->id(), 403);
        $this->trip = $trip;
    }
    // ...
}
```

Computed properties (all read live from `trip->expenses()`):

| Property | Returns |
| --- | --- |
| `getTotalSpentProperty()` | `SUM(expenses.amount)` for the trip |
| `getRemainingProperty()` | `budget_limit − totalSpent` |
| `getSpentPctProperty()` | percent of `budget_limit` used (0 if no limit) |
| `getDaysProperty()` | `max(1, start→end diff in days)` |
| `getCategorySpendProperty()` | `category → SUM(amount)` map |
| `getDailySpendProperty()` | `expense_date → SUM(amount)` map, date-ordered |
| `getBudgetBreakdownProperty()` | `trip->budgets()->get()` |
| `getRecentExpensesProperty()` | latest 5 expenses by `expense_date` |

`render()` → `livewire.traveler.trip-dashboard`.

### Frontend walkthrough

`trips/create.blade.php` / `edit.blade.php` are straight forms. `trips/estimate.blade.php`
shows the computed category table with an "Apply" button that POSTs to `trips.applyEstimates`.
`trips/budget.blade.php` posts an `estimated_cost[Category]` array. `trip-dashboard.blade.php`
opens with a "Scan Expense" banner linking to `expenses.create?trip_id={id}`, then stat cards
+ a category breakdown drawn from the computed properties.

### Data touched

Reads/writes `trips`, `trip_budgets`. Reads `expenses`, `destination_costs`.

### Gotchas

- These routes use **owner-only** guards; the planner/dashboard/saved-trips features use
  `canAccessTrip` (group-aware). Don't "fix" one to match the other without checking intent.
- `budgetStore` is the only place `trip_budgets` gets populated in the live app — a trip
  created through the wizard has no rows here, so `TripController@show` / `BudgetService`
  show zeros for it.
- The estimate categories (`Tourist Attractions`, `Emergency Funds`) differ in wording from
  the expense categories (`Activities`, `Emergency Expenses`) — `ExpenseObserver::CATEGORY_MAP`
  bridges them.

---

## 5. Saved / Shared / Imported / Compared Trips

### What it does

- **Saved Trips** — the traveller's trip cards, grouped by status (Draft / Upcoming / Active /
  Past), with rename, status override, group-member management, share-code generation, and
  delete.
- **Multi Trip Hub** — a lighter trip list that can pick **two** trips and compare their
  category spend side-by-side.
- **Trip import** — open a share link / code and clone that trip into your own Saved Trips.

### Routes

| Verb | URI | Name | Handler |
| --- | --- | --- | --- |
| GET | `/saved-trips` | `saved-trips` | Livewire `SavedTrips` |
| GET | `/multi-trips` | `multi-trips.index` | Livewire `MultiTripHub` |
| GET | `/trips/import/{code}` | `trips.import` | `TripImportController@import` |

### Backend files

- `app/Livewire/Traveler/SavedTrips.php`
- `app/Livewire/Traveler/MultiTripHub.php`
- `app/Livewire/Traveler/BudgetComparison.php` — **stub** (`render()` only); comparison logic
  actually lives in `MultiTripHub`.
- `app/Http/Controllers/Traveler/TripImportController.php`
- `app/Services/TripImportService.php`
- `app/Models/Trip` (`generateUniqueShareCode()`), `GroupMember`, `Notification`, `SavingsGoal`,
  `Itinerary`, `User`.

### Frontend files

- `resources/views/livewire/traveler/saved-trips.blade.php`
- `resources/views/traveler/saved-trips.blade.php` — thin wrapper: `@livewire('traveler.saved-trips')`.
- `resources/views/livewire/traveler/multi-trip-hub.blade.php`
- `resources/views/livewire/traveler/budget-comparison.blade.php` — stub.
- `resources/views/components/traveler/⚡saved-trips.blade.php`

### Backend walkthrough — `SavedTrips`

- `showDetail(int)` / `closeDetail()` — the expandable per-card detail panel.
- `confirmDelete(int)` / `cancelDelete()` / `deleteTrip()` — owner-checked delete
  (`if ($trip && $trip->user_id === auth()->id())`).
- `openEditName(int)` — loads a trip into the edit modal: name, `editType` (Solo/Group),
  `editStatus` (upcoming/active/past — from `getRawOriginal('status')` or computed from dates),
  and the current `savedMembers` (from `GroupMember`). Owner-only.
- `lookupMember()` — resolves `memberEmail` to a registered `User`; validates not-self,
  not-already-added; pushes to `pendingMembers`.
- `removePendingMember(int $index)` / `removeSavedMember(int $userId)` (the latter deletes the
  `GroupMember` row immediately).
- `saveEditName()`:
  ```php
  $trip->update(['trip_name' => trim($this->editNameValue), 'travel_type' => $this->editType, 'status' => $this->editStatus]);
  $trip->savingsGoals()->update(['goal_name' => $newName]);
  if ($this->editType === 'Solo') {                 // back to Solo → drop members, reset head count
      GroupMember::where('trip_id', $trip->id)->delete();
      $trip->update(['num_travelers' => 1]);
  }
  if ($this->editType === 'Group') {
      foreach ($this->pendingMembers as $m) {
          $link = GroupMember::firstOrCreate(['trip_id' => $trip->id, 'user_id' => $m['id']]);
          if ($link->wasRecentlyCreated) $this->notifyAddedToTrip($trip, $m['id']);  // only notify genuinely new links
      }
  }
  ```
- `openShare(int)` / `closeShareModal()` — owner-only; if `TripImportService::isShareable($trip)`
  is false sets `shareNotAvailable = true`, else `shareCode = $importer->shareCodeFor($trip)` and
  `shareLink = route('trips.import', $code)`.
- `notifyAddedToTrip(Trip, int $memberId)` *(private)* — creates a `trip_shared` notification
  for the new member (the trip then appears in *their* Saved Trips).
- `displayAmount(Trip $trip, float $pesoAmount)` — wrapper over `trip_amount()` (§0.4).
- `fetchTrips()` *(private)* — the card list. Owner trips + group-member trips (others' drafts
  excluded); `ilike` search across `destination` / `trip_name` / `leg2_destination`;
  `withSum('expenses','amount')`, `withCount('groupMembers')`. Per trip it computes: `days`,
  `status` (`resolved_status`), `actual_spent`, `spend_pct`, `head_count` (Solo = 1; Group =
  `max(num_travelers, group_members_count + 1)`), `cost_per_person`, `spent_per_person`,
  `shared_with_me`, and runs `trip_apply_display_currency($trip)`. Sorts so a multi-city trip
  wins over a same-start-date single-leg duplicate.
- `render()` → `livewire.traveler.saved-trips` with `trips` + `detailTrip`.

### Backend walkthrough — `MultiTripHub`

- `showDetail(int)` / `closeDetail()`.
- `toggleCompare(int $tripId)` — two-step selection: toggles the id in `compareIds`, capped at
  2 (a third pick drops the oldest).
- `runComparison()` — opens the comparison **only** when exactly 2 are picked.
- `closeComparison()` / `clearCompareSelection()`.
- `fetchTrips()` *(private)* — `accessibleTrips()`, non-draft (NULL-safe), `withSum` expenses,
  `ilike` search; per-trip `total_spent` / `pct_used` / `days` / `status`.
- `fetchCompareData(Collection $trips)` *(private)* — for each of the 2 ids: category sums over
  `compareCategories` (`Transportation`, `Accommodation`, `Food`, `Tourist Attractions`), and a
  `budget` figure that is the trip's **savings-goal `current_savings`**, falling back to
  `budget_limit` only when there's no goal.
- `render()` → `livewire.traveler.multi-trip-hub` with `trips`, `totals`, `detailTrip`, `compareData`.

### Backend walkthrough — `TripImportController` + `TripImportService`

```php
// TripImportController::import
public function import(string $code, TripImportService $importer)
{
    $sourceTrip = $importer->findByCode($code);
    if (!$sourceTrip)                          return redirect()->route('saved-trips')->with('error', 'That share code or link is no longer valid.');
    if ($sourceTrip->user_id === auth()->id()) return redirect()->route('saved-trips');           // your own trip — silently blocked
    if (!$importer->isShareable($sourceTrip))  return redirect()->route('saved-trips')->with('error', 'This trip has nothing shareable saved on it.');
    $importer->import($sourceTrip, auth()->user());
    return redirect()->route('saved-trips')->with('success', 'Trip imported!');
}
```

`TripImportService`:

- `isShareable(Trip): bool` — true iff any of the four `*_selection` snapshot columns is
  non-null (older trips predate the Share feature and can't be shared).
- `shareCodeFor(Trip): string` — lazily generates `Trip::generateUniqueShareCode()` (8 chars,
  alphabet without `0/O/1/I`). **Never revoked** — same code forever, by product decision.
- `findByCode(string): ?Trip` — uppercase/trim lookup on `share_code`.
- `import(Trip $sourceTrip, User $recipient): Trip` — creates a brand-new Trip on the
  recipient from the source's core fields + all 8 selection snapshots + `total_cost` +
  `summary_data`, then clones every `itinerary` row. **No** emergency fund, no group members.
- `totalCost(Trip)` *(private)* — sums flight + hotel + venue + attraction costs across both
  legs (skipping `isFree` attractions), stripping non-numerics from price strings.
- `buildSummaryData(Trip)` *(private)* — builds the `transportation/accommodation/food/attractions`
  cost-and-detail structure that `ReportService::costRows` later reads.

### Frontend walkthrough

`saved-trips.blade.php` renders status-grouped card sections, a search box (`wire:model.live`),
and modals for detail / rename+members / share / delete (`wire:click` on every action).
Each card also embeds `@livewire('traveler.savings-goal-manager', ['goal' => ...])` where a
goal exists. `multi-trip-hub.blade.php` shows a lighter list with a "Compare" toggle per card
and a floating bar that appears once 2 are picked.

### Data touched

Reads/writes `trips`, `group_members`, `notifications`, `savings_goals`; on import also
`itinerary`. Reads `expenses`.

### Gotchas

- Share codes never expire and can't be revoked.
- `MultiTripHub`'s "budget" is savings-goal progress, not `budget_limit` — different meaning
  from every other trip list.
- `BudgetComparison` the component and its view are dead stubs.
- Imported trips are independent copies — editing the source later does not propagate.

---

## 6. Expense Tracker + Receipt OCR

### What it does

Log spending against a trip — manually, or by uploading a receipt photo that an OCR API reads
to pre-fill the amount / date / merchant / currency (and category, if the user opted in). Any
non-peso amount is converted to pesos on save, keeping the original alongside. Each expense
updates the trip's per-category `actual_spent` and can fire budget-warning notifications.

### Routes

| Verb | URI | Name | Handler |
| --- | --- | --- | --- |
| GET | `/expenses` | `expenses.index` | `ExpenseController@index` |
| GET | `/expenses/create` | `expenses.create` | `ExpenseController@create` |
| POST | `/expenses` | `expenses.store` | `ExpenseController@store` |
| GET | `/expenses/{expense}/edit` | `expenses.edit` | `ExpenseController@edit` |
| PUT | `/expenses/{expense}` | `expenses.update` | `ExpenseController@update` |
| DELETE | `/expenses/{expense}` | `expenses.destroy` | `ExpenseController@destroy` |
| POST | `/expenses/ocr` | `expenses.ocr` | `ExpenseController@ocr` (AJAX, returns JSON) |

### Backend files

- `app/Http/Controllers/Traveler/ExpenseController.php`
- `app/Services/OcrService.php`
- `app/Services/CurrencyConverterService.php` (§0.3)
- `app/Observers/ExpenseObserver.php`
- `app/Models/Expense`, `OcrLog`, `Trip`, `TripBudget`, `Notification`.
- `app/Livewire/Traveler/ExpenseTracker.php` — **stub** (unused).
- `app/Support/PlaceCatalog.php` — `CURRENCY_SYMBOLS` (drives the currency dropdown).

### Frontend files

- `resources/views/traveler/expenses/index.blade.php` — the ledger (transaction cards,
  trip/category/date filters, per-trip totals).
- `resources/views/traveler/expenses/create.blade.php` — the add form + receipt dropzone.
- `resources/views/traveler/expenses/edit.blade.php`.

### Backend walkthrough — `ExpenseController`

`const CATEGORIES = ['Transportation','Accommodation','Food','Activities','Shopping','Emergency Expenses']`.

- `currencyCodes()` *(private static)* — `array_keys(PlaceCatalog::CURRENCY_SYMBOLS)`; the
  allowed set for `amount_currency`.
- `resolveAmountInPesos(array $validated): array` *(private)* — **the core conversion**:
  ```php
  $code  = strtoupper((string) ($validated['amount_currency'] ?? 'PHP')) ?: 'PHP';
  $typed = (float) $validated['amount'];
  if ($code === 'PHP') {
      $validated['amount_currency'] = 'PHP';
      $validated['amount_original'] = null;     // nothing was converted
      return $validated;
  }
  $rate = (new CurrencyConverterService())->rateToPhp($code);
  if ($rate === null) throw CurrencyUnavailable::for($code);
  $validated['amount']          = round($typed * $rate, 2);   // canonical pesos
  $validated['amount_original'] = $typed;                     // what was actually spent
  $validated['amount_currency'] = $code;
  return $validated;
  ```
  On the way in, `amount` = what they typed in `amount_currency`. On the way out, `amount` =
  pesos and `amount_original` / `amount_currency` record the real spend — so an **edit
  re-converts from the original** rather than converting an already-converted number twice.
- `defaultCurrencyForTrip(?Trip $trip): string` *(public static)* — `$trip?->destination_currency ?: 'PHP'`
  (a traveller in Japan is handing over yen, so the form defaults to yen).
- `normaliseAmount(Request $request)` *(private)* — strips thousands-separator commas from
  `amount` server-side (a no-JS form submit sends `"1,234.50"`, which fails `numeric`).
- `index(Request $request)` — trip selector from `accessibleTrips()` (active/upcoming/past);
  the ledger is **scoped by trip, not by who logged it** — on a group trip everyone sees the
  whole group's spend. `$tripId = $request->filled('trip_id') ? ... : $trips->first()?->id`;
  optional `category` / `date_from` / `date_to` filters (dates guarded with
  `strtotime(...) !== false` so a malformed value can't crash the Postgres query); paginate 20.
- `create(Request $request)` — accessible trips + categories + `defaultCurrency` for the
  preselected trip.
- `store(Request $request)`:
  ```php
  $this->normaliseAmount($request);
  $validated = $request->validate([
      'trip_id' => 'required|exists:trips,id',
      'amount'  => 'required|numeric|min:0.01',
      'amount_currency' => ['nullable','string','size:3', Rule::in(self::currencyCodes())],
      'category' => 'required|in:' . implode(',', self::CATEGORIES),
      'description' => 'nullable|string|max:500',
      'expense_date' => 'required|date',
      'receipt' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240',
  ]);
  abort_if(!auth()->user()->canAccessTrip((int) $validated['trip_id']), 403);
  try { $validated = $this->resolveAmountInPesos($validated); }
  catch (CurrencyUnavailable $e) { return back()->withInput()->withErrors(['amount' => $e->getMessage()]); }
  if ($request->hasFile('receipt')) $validated['receipt_path'] = $request->file('receipt')->store('receipts', 'public');
  unset($validated['receipt']);
  $validated['user_id'] = auth()->id();
  try { $expense = Expense::create($validated); }
  catch (\Throwable $e) {                                   // orphan-file cleanup
      if (!empty($validated['receipt_path'])) Storage::disk('public')->delete($validated['receipt_path']);
      throw $e;
  }
  \App\Observers\ExpenseObserver::syncBudgetForExpense($expense);  // explicit, see below
  return redirect()->route('expenses.index')->with('success', 'Expense recorded.');
  ```
- `edit(Expense $expense)` / `update(Request, Expense $expense)` — `canAccessTrip` guard
  (**membership, not authorship** — a shared trip's ledger is shared, but the row still shows
  who recorded it). The edit form shows the *original* amount, so `update` re-converts from
  scratch. Safe receipt swap: store the new file first, delete the old one **only after**
  `update()` succeeds; on failure clean up only the just-stored new file.
- `destroy(Expense $expense)` — `canAccessTrip` guard; deletes the receipt file then the row.
- `ocr(Request $request, OcrService $ocrService)`:
  ```php
  $request->validate(['receipt' => 'required|file|mimes:jpeg,png,jpg,webp,pdf|max:10240', 'trip_id' => 'nullable|exists:trips,id']);
  $trip = ... // only if canAccessTrip
  $result = $ocrService->scan($request->file('receipt'), auth()->id(), $trip?->destination_currency);
  $result['currency'] = $result['currency'] ?? self::defaultCurrencyForTrip($trip);
  if (! auth()->user()->ocr_auto_categorize) unset($result['category']);
  return response()->json($result);
  ```

### Backend walkthrough — `OcrService`

- `scan(UploadedFile $file, int $userId, ?string $destinationCurrency = null): array` — reads
  the file **bytes only** (no permanent copy — the real receipt is stored once by
  `ExpenseController::store`); base64s it; POSTs to OCR.space (`config('services.ocr.endpoint')`,
  engine 2, `language=auto`, **25s timeout** so a hung endpoint can't trip PHP's execution
  limit); logs every attempt to `OcrLog`. Then:
  ```php
  $parsed = $this->parseReceiptText($text);
  $parsed['category'] = $this->guessCategory($text);
  if ($parsed['amount'] === null) {                        // AI fallback ONLY when regex found nothing
      $aiAmount = $this->extractAmountWithAi($text);
      if ($aiAmount !== null) { $parsed['amount'] = $aiAmount; $usedAiFallback = true; }
  }
  $confidence = $parsed['amount'] ? ($usedAiFallback ? 70.0 : 85.0) : 30.0;
  $parsed['currency'] = ($parsed['currencyCode'] ?? null) ?: self::currencyFromSymbol($parsed['symbol'] ?? null, $destinationCurrency);
  ```
- `parseReceiptText(string)` *(private)* — regex parser for amount / date / merchant / currency
  symbol.
- `guessCategory(string)` *(private)* — keyword→category via `CATEGORY_KEYWORDS` (coarse, just
  a suggestion).
- `extractAmountWithAi(string $text)` *(private)* — asks a provider (via `tryProviders`,
  `PROVIDER_ORDER` = Mistral → OpenRouter → Groq → Gemini) to identify the grand total from
  noisy OCR text; expects `{"total": number|null}`.
- `currencyFromSymbol(?string $symbol, ?string $destinationCurrency)` *(private static)* —
  `UNAMBIGUOUS_SYMBOLS` (`₱$€£₩₫฿`) map directly; `AMBIGUOUS_SYMBOLS` (`¥` → JPY/CNY, `kr` →
  DKK/NOK/SEK) are resolved against the trip's `destination_currency`, or to `null` if there's
  no trip to disambiguate.

### Backend walkthrough — `ExpenseObserver`

`const CATEGORY_MAP` translates expense categories to `trip_budgets` categories
(`Activities → Tourist Attractions`, `Emergency Expenses → Emergency Funds`, …).

- `created(Expense)` — creates an `expense_added` notification; calls `checkOverallBudgetExceeded()`.
- `updated(Expense)` — if `trip_id` / `category` / `amount` changed, reverses the old value's
  contribution to `actual_spent`, then re-applies via `syncBudgetForExpense` — one pair of
  calls covers an amount fix, a category change, and moving the expense to another trip.
  (Needed because `ExpenseController::update` has no budget-sync logic of its own.)
- `deleted(Expense)` — `adjustActualSpent(-amount)`.
- `syncBudgetForExpense(Expense)` *(public static)* — `increment('actual_spent', $expense->amount)`
  on the matching `TripBudget`; then, when `user->notify_budget_alerts`:
  - ≥50 % & <80 % of `estimated_cost` → one `budget_warning` notification (deduped by message LIKE);
  - ≥80 % → one `budget_alert` notification.
- `checkOverallBudgetExceeded(?Trip)` *(private)* — total spend over `trips.budget_limit` → one
  `budget_alert` about the overall budget.

### Frontend walkthrough — `expenses/create.blade.php`

This view carries three notable inline-JS blocks (all in `@push('scripts')`):

1. **Themed `<select>`** — a native `<option>` list can't be styled, so the real `<select>`
   (source of truth for the POST, `checkValidity()` and OCR autofill) is hidden behind a
   trigger + menu built from theme tokens. `is-invalid` / `ocr-filled` classes land on the
   hidden select and are reflected onto the visible trigger via adjacent-sibling CSS.
2. **Receipt dropzone + OCR** — drag/drop or click uploads a file, shows a preview, and does:
   ```js
   var formData = new FormData();
   formData.append('receipt', file);
   formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
   if (tripSelect && tripSelect.value) formData.append('trip_id', tripSelect.value);  // for ¥ disambiguation
   fetch('/expenses/ocr', { method:'POST', body:formData, headers:{ 'Accept':'application/json' } })
     .then(r => r.ok ? r.json() : r.json().then(b => { throw new Error(b?.errors?.receipt?.[0] ?? "Couldn't scan this receipt."); }))
     .then(data => { /* fill amount/currency/date/description/category, markOcrFilled() each */ });
   ```
   `markOcrFilled(field)` highlights a field the scan filled and clears the highlight the
   moment the traveller edits it.
3. **Amount formatting** — `formatAmount()` groups the whole part with commas as you type and
   keeps up to 2 decimal places; a `submit` listener strips the commas before POST (server
   also strips via `normaliseAmount`).

The form is `novalidate` (native bubbles suppressed) with a custom "shake the empty required
field" handler on submit.

### Data touched

Writes `expenses`, `trip_budgets` (`actual_spent`), `notifications`, `ocr_logs`, receipt
files on the `public` disk. Reads `trips`.

### Gotchas

- `amount` is **always pesos**; `amount_original` + `amount_currency` preserve the real spend.
  Never convert `amount` again.
- Editing an expense re-converts from `amount_original` — re-converting `amount` is a
  double-conversion bug this shape exists to prevent.
- `store()` calls `ExpenseObserver::syncBudgetForExpense` **explicitly** (the `created` hook
  doesn't do budget sync — it only sends the "logged" notification and the overall-budget
  check). `update`/`delete` are handled by the hooks.
- OCR AI fallback only runs when the regex parser found no amount at all — don't make it
  unconditional.
- The ledger is scoped by trip, not by logger — a group member sees the whole group's spend.

---

## 7. Savings Goals

### What it does

Every trip you can access automatically gets a savings goal (target = your per-head share of
the trip cost, deadline = trip start). You can also create standalone goals. Each goal card
shows progress, days left, and a daily-savings-needed figure, lets you log deposits (validated
and displayed in your **home** currency, optionally also shown in the destination currency),
and notifies you at milestones.

### Routes

| Verb | URI | Name | Handler |
| --- | --- | --- | --- |
| GET | `/savings` | `savings.index` | `SavingsGoalController@index` |
| GET | `/savings/create` | `savings.create` | `SavingsGoalController@create` |
| POST | `/savings` | `savings.store` | `SavingsGoalController@store` |
| GET | `/savings/{goal}/edit` | `savings.edit` | `SavingsGoalController@edit` |
| PUT | `/savings/{goal}` | `savings.update` | `SavingsGoalController@update` |
| DELETE | `/savings/{goal}` | `savings.destroy` | `SavingsGoalController@destroy` |

### Backend files

- `app/Http/Controllers/Traveler/SavingsGoalController.php`
- `app/Livewire/Traveler/SavingsGoalManager.php` — embedded per goal.
- `app/Services/CurrencyConverterService.php` (§0.3), `app/Support/PlaceCatalog.php`.
- `app/Models/SavingsGoal`, `Trip`, `Notification`.
- `app/Http/Controllers/Traveler/SavingsController.php` — **dead stub**, not routed.

### Frontend files

- `resources/views/traveler/savings/{index,create,edit}.blade.php`
- `resources/views/livewire/traveler/savings-goal-manager.blade.php`

### Backend walkthrough — `SavingsGoalController`

- `index()` — **auto-creates** a goal for every accessible trip that has none *for this user*:
  ```php
  $tripsWithoutGoals = auth()->user()->accessibleTrips()
      ->whereDoesntHave('savingsGoals', fn ($q) => $q->where('user_id', auth()->id()))
      ->withCount('groupMembers')->get();
  foreach ($tripsWithoutGoals as $trip) {
      $isGroup = strcasecmp($trip->travel_type ?? 'Solo', 'Group') === 0;
      $heads   = $isGroup ? max(1, (int) $trip->num_travelers, $trip->group_members_count + 1) : 1;
      $total   = (float) ($trip->total_cost ?: $trip->budget_limit ?: 0);
      SavingsGoal::create([
          'user_id' => auth()->id(), 'trip_id' => $trip->id,
          'goal_name' => $trip->destination . ' Trip',
          'target_amount' => max(1, round($total / $heads, 2)),   // this member's SHARE
          'current_savings' => 0, 'deadline' => $trip->start_date,
      ]);
  }
  ```
  Scoped by `user_id` **and** `trip_id` because on a group trip each member saves toward their
  own share. Goals list is then sorted so a multi-city trip's goal beats a same-start-date
  single-leg duplicate.
- `create()` — accessible trips for the trip dropdown.
- `store(Request)` — validates `goal_name` / `target_amount` (min 1) / `current_savings` /
  `deadline` (`after:today`) / `trip_id`; owner guard on the trip if given; creates on
  `auth()->user()->savingsGoals()`.
- `edit(SavingsGoal $goal)` / `update(Request, SavingsGoal $goal)` / `destroy(SavingsGoal $goal)` —
  all `abort_if($goal->user_id !== auth()->id(), 403)`. `update`'s `deadline` rule drops the
  `after:today` (you can edit a goal whose trip already started).

### Backend walkthrough — `SavingsGoalManager` (embedded `@livewire('traveler.savings-goal-manager', ['goal' => $goal])`)

- `openDeposit()` / `closeDeposit()` / `openProjection()` / `closeProjection()` — dialog toggles.
- **Currency helpers** (all via `CurrencyConverterService` + `PlaceCatalog`):
  `homeCurrency()`, `homeCurrencySymbol()`, `destinationCurrency()` (trip's
  `destination_currency`, else `PlaceCatalog::DESTINATION_CURRENCIES[destination]`, else null),
  `destinationCurrencySymbol()`, `homeRate()`, `destinationRate()`, `hasCurrencyConversion()`,
  `conversionRateLabel()` (`"1 JPY ≈ ₱0.37 PHP"`).
- **Display helpers**: `displayHomeAmount(float $pesoAmount)`, `displayDestinationAmount(float)`
  (null if no rate), `displayAmount(float)` (= home).
- `submitDeposit()`:
  ```php
  abort_if($this->goal->user_id !== auth()->id(), 403);
  $homeCode = $this->homeCurrency();  $homeRate = $this->homeRate() ?? 1.0;
  $targetCost = $this->goal->trip?->total_cost ?? $this->goal->target_amount;
  $remainingPesos = max(0, $targetCost - $this->goal->current_savings);
  $remainingHome  = $homeRate > 0 ? round($remainingPesos / $homeRate, 2) : $remainingPesos;
  $this->validate(['depositAmount' => ['required','numeric','min:0.01','max:' . max(0.01, $remainingHome)]], [...]);
  $pesoDeposit = $homeCode === 'PHP' ? (float) $this->depositAmount : round((float) $this->depositAmount * $homeRate, 2);
  $this->goal->increment('current_savings', $pesoDeposit);
  // then: 'savings_goal_reached' notification if this deposit completed it, else 'savings_goal_deposit'
  $this->dispatch('goalUpdated');
  ```
  Deposit is entered in home currency, converted to pesos for storage, and capped at the
  remaining amount needed.
- Computed properties: `getPctProperty()`, `getDailyNeededProperty()` (remaining ÷ days left),
  `getDaysLeftProperty()`, `getIsCompletedProperty()` — all measure against
  `goal->trip?->total_cost ?? goal->target_amount`.
- `render()` → `livewire.traveler.savings-goal-manager`.

### Frontend walkthrough

`savings/index.blade.php` renders one `@livewire('traveler.savings-goal-manager', ['goal' => $goal], key(...))`
per goal. The manager view is a boarding-pass-style card with a progress meter
(`meter_color($pct, 'progress')` — red→teal), a deposit modal (`wire:model="depositAmount"`,
`wire:click="submitDeposit"`), and a projection modal.

### Data touched

Writes `savings_goals`, `notifications`. Reads `trips`, `group_members`.

### Gotchas

- Target is the **per-head share**, not the whole trip cost, for group trips.
- Goals are per `(user_id, trip_id)` — every group member gets their own goal for a shared trip.
- Deposits are home-currency in / pesos stored — consistent with the app-wide pesos rule.
- Renaming a trip in Saved Trips also renames its goal (`saveEditName` does
  `$trip->savingsGoals()->update(['goal_name' => ...])`).

---

## 8. Itinerary Schedule + Moments Map

### What it does

- **Itinerary** — a calendar of scheduled trip items (Flight / Hotel / Activity /
  Transportation) with add/delete, plus a one-click template generator that fills a day with a
  sensible arrival/sightseeing/departure schedule.
- **Moments** — geo-tagged photo pins on a Leaflet map, either per-trip or across all trips.
  Pins can only be added while a trip is *Ongoing*.

Both are served by the **same** Livewire component (`ItineraryManager`) and the **same**
Blade view, switched by a `tab` prop.

### Routes

| Verb | URI | Name | Handler |
| --- | --- | --- | --- |
| GET | `/itinerary` | `itinerary.index` | `ItineraryController@index` |
| POST | `/itinerary` | `itinerary.store` | `ItineraryController@store` |
| DELETE | `/itinerary/{item}` | `itinerary.destroy` | `ItineraryController@destroy` |
| GET | `/moments` | `moments.index` | `MomentController@index` |

### Backend files

- `app/Http/Controllers/Traveler/ItineraryController.php`
- `app/Http/Controllers/Traveler/MomentController.php`
- `app/Http/Requests/StoreItineraryItemRequest.php`
- `app/Livewire/Traveler/ItineraryManager.php` (~776 lines) — `use WithFileUploads`.
- `app/Services/MomentService.php`
- `app/Models/Itinerary` (table `itinerary`), `Moment`, `MomentPhoto`, `Trip`, `Attraction`.
- `app/Console/Commands/SendItineraryReminders.php`, `BackfillItineraryNotes.php`.

### Frontend files

- `resources/views/traveler/itinerary/index.blade.php` — hosts the component; loads FullCalendar
  + Leaflet from a CDN; `@section('title')` swaps between "Itinerary" and "Moments".
- `resources/views/livewire/traveler/itinerary-manager.blade.php`
- `resources/views/livewire/traveler/moments.blade.php`
- `public/js/basemap.js` — shared Leaflet basemap config.

### Backend walkthrough — controllers

**`ItineraryController`**

- `index()` — `auth()->user()->accessibleTrips()->with(['itinerary' => fn($q) => $q->orderBy('start_datetime')])`;
  view `traveler.itinerary.index`.
- `store(StoreItineraryItemRequest $request)` — validated data; `Trip::findOrFail` +
  `abort_if($trip->user_id !== auth()->id(), 403)` (**owner-only** here); `Itinerary::create`.
- `destroy(Itinerary $item)` — `abort_if($item->trip->user_id !== auth()->id(), 403)`; delete.

**`MomentController`**

- `index()` — same `accessibleTrips` load, renders `traveler.itinerary.index` with
  `tab='moments'`, `active='moments'`.

**`StoreItineraryItemRequest`** — `authorize()` returns `true` (ownership checked in the
controller *after* validation, matching the app's validate-then-authorize convention).
`rules()`: `trip_id` exists, `title` required, `type` in `Flight,Hotel,Activity,Transportation`,
`start_datetime` required date, `end_datetime` nullable + `after_or_equal:start_datetime`.

### Backend walkthrough — `ItineraryManager`

Injected service via `boot()` (runs every request, unlike `mount()` — the correct hook for DI
in Livewire):

```php
public function boot(MomentService $momentService): void { $this->momentService = $momentService; }
```

- `mount()` — picks the selected trip (deep-link `?trip_id`, else first accessible trip);
  `momentsMode` = `'trip'` for a deep link, else `'overview'`.
- Trip/day navigation: `updatedSelectedTripId()`, `selectTrip(int $tripId)` (scoped to
  `accessibleTrips()`), `showTripOnMap(int)`, `backToOverview()`, `goBack()`,
  `selectDay(string)`, `closeModals()`.
- `deleteItem(int $itemId)` — removes an itinerary row (owner-checked via the trip).
- `generateItinerary()` — **template-based, not AI.** Deletes that day's existing items, then
  builds a schedule from `Attraction` rows for the destination:
  ```php
  $isFirst = $dateStr === $trip->start_date->toDateString();
  $isLast  = $dateStr === $trip->end_date->toDateString();
  if ($isFirst) { /* Arrival, Hotel Check-in, Dinner */ }
  elseif ($isLast) { /* Breakfast, Check-out, Departure */ }
  else { /* Breakfast + 3 attractions (nextAttraction()) + lunch + dinner */ }
  ```
  `createItem()` / `nextAttraction($attractions, $idx, $dest)` are the private helpers.
- **Moments pins** — two sets of open/edit/delete handlers, one for the all-trips overview map
  and one for the per-trip map:
  - overview: `openAddPinModalFromOverview(float $lat, float $lng)` (uses
    `resolveNearestTrip()` / `focusTripForMoment()`), `openEditPinModalFromOverview(int)`,
    `confirmDeletePinFromOverview(int)`.
  - per-trip: `openAddPinModal(float, float)`, `openEditPinModal(int)`, `closePinModal()`,
    `removeNewPhoto(int)`, `removeExistingPhoto(int)`, `savePin()`, `confirmDeletePin(int)`,
    `cancelDeletePin()`, `deletePin()`.
  ```php
  public function savePin(): void
  {
      $trip = $this->selectedTrip;
      abort_if(!$trip, 403);
      abort_if(!$this->canPostMomentFor($trip), 403);   // re-checked server-side: trip must be Ongoing
      $validated = $this->validate([
          'pinPlaceName' => 'required|string|max:255', 'pinDescription' => 'nullable|string|max:2000',
          'pinVisitedDate' => 'required|date', 'pinPhotos' => 'nullable|array|max:6',
          'pinPhotos.*' => 'image|max:5120', 'pinLat' => 'required|numeric', 'pinLng' => 'required|numeric',
      ]);
      $moment = ($this->pinModalMode === 'edit' && $this->editingPinId)
          ? $this->momentService->updatePin(Moment::where('id', $this->editingPinId)->where('trip_id', $trip->id)->firstOrFail(), $data, $this->pinPhotos)
          : $this->momentService->createPin($trip, $data, $this->pinPhotos);
      $this->dispatch('pin-saved', pin: $this->momentService->pinToArray($moment));
  }
  ```
- `canPostMomentFor(Trip)` *(private)* — true only when the trip is Ongoing.
  `getHasOngoingTripProperty()` exposes whether any accessible trip qualifies.
- **Computed properties** feed the calendar and the map:
  `getTripsProperty()`, `getSelectedTripProperty()`, `getEventsProperty()` (FullCalendar events),
  `getDayItemsProperty()`, `getMapCenterProperty()` (from `DEST_COORDS` or the PH centroid),
  `getOverviewPinsProperty()`, `getAllMomentPinsProperty()`, `getAllMomentsTimelineProperty()`,
  `getInitialPinsProperty()`, `getTimelineMomentsProperty()`.
- `render()` → `livewire.traveler.itinerary-manager`.

### Backend walkthrough — `MomentService`

- `createPin(Trip, array $data, array $photoUploads): Moment` / `updatePin(Moment, array, array): Moment`
  — write the `Moment` then `attachPhotos()`.
- `attachPhotos(Moment, array)` *(private)* — each upload `->store('moment-photos', 'public')`
  into a `MomentPhoto` row.
- `deletePhoto(MomentPhoto)` — delete file + row.
- `deleteMoment(Moment)` — delete every photo file, then delete the moment (FK cascade removes
  the `moment_photos` rows).
- `pinToArray(Moment): array` — the map-marker payload (`lat`, `lng`, `place_name`, formatted
  `visited_date`, `photo_urls`).
- `existingPhotosArray(Moment): array` — `[{id, url}]` for the edit modal.

### Frontend walkthrough

`itinerary/index.blade.php` conditionally loads Leaflet only for the Moments tab. The Livewire
view renders a FullCalendar instance (fed `getEventsProperty()` via a dispatched `trip-selected`
/ `trip-changed` event with `start`, `end`, `events`), a day-detail modal, and — on the Moments
tab — a Leaflet map that the Livewire side talks to through dispatched events (`pin-saved`,
`pin-deleted`, `trip-selected`). Photo uploads use Livewire's `WithFileUploads` (`wire:model="pinPhotos"`).

### Data touched

Writes `itinerary`, `moments`, `moment_photos`, photo files on the `public` disk. Reads
`trips`, `attractions`.

### Gotchas

- `generateItinerary()` is a **fixed template**, not an AI call — don't confuse it with the
  planner wizard's `suggestItinerary()`.
- Itinerary CRUD is **owner-only**; the list is `accessibleTrips` (a group member sees the
  shared trip but can't add to it via this path).
- Moments can only be pinned while the trip is **Ongoing** — enforced in both
  `openAddPinModal` and again in `savePin` (never trust the client).
- Deleting a trip cleans up moment photo files via `TripObserver::deleting` (the DB cascade
  alone wouldn't touch disk).

---

## 9. Destinations / Attractions / Comparison / Reviews

### What it does

Reference catalogues the traveller can browse: **Destinations** (cities, each with its
attractions), **Attractions** (points of interest, each with reviews and an average rating),
a **cost Comparison** tool (pick up to 3 destinations, see estimated total/day for N
travellers over M days), and **Reviews** (travellers rate a destination or attraction, flag
others' reviews, and mark reviews helpful).

### Routes

| Verb | URI | Name | Handler |
| --- | --- | --- | --- |
| GET | `/destinations` | `destinations.index` | `DestinationController@index` |
| GET | `/destinations/{destination}` | `destinations.show` | `DestinationController@show` |
| GET | `/attractions` | `attractions.index` | `AttractionController@index` |
| GET | `/attractions/{attraction}` | `attractions.show` | `AttractionController@show` |
| GET | `/compare` | `compare.index` | `ComparisonController@index` |
| GET | `/reviews` | `reviews.index` | `ReviewController@index` |
| POST | `/reviews` | `reviews.store` | `ReviewController@store` |
| PUT | `/reviews/{review}` | `reviews.update` | `ReviewController@update` |
| DELETE | `/reviews/{review}` | `reviews.destroy` | `ReviewController@destroy` |
| POST | `/reviews/{review}/flag` | `reviews.flag` | `ReviewController@flag` |
| POST | `/reviews/{review}/helpful` | `reviews.helpful` | `ReviewController@markHelpful` |

### Backend files

- `app/Http/Controllers/Traveler/DestinationController.php`, `AttractionController.php`,
  `ComparisonController.php`, `ReviewController.php`.
- `app/Livewire/Traveler/DestinationBrowser.php`, `AttractionBrowser.php`.
- `app/Services/PlaceImageService.php`.
- `app/Models/Destination`, `Attraction`, `DestinationCost`, `Review`, `ReviewHelpfulVote`.

### Frontend files

- `resources/views/traveler/destinations/{index,show}.blade.php`
- `resources/views/traveler/attractions/{index,show}.blade.php`
- `resources/views/traveler/comparison/index.blade.php`
- `resources/views/traveler/reviews/index.blade.php`
- `resources/views/livewire/traveler/{destination-browser,attraction-browser}.blade.php`

### Backend walkthrough

**`DestinationController`**

- `index()` → `traveler.destinations.index` (the browser Livewire component does the work).
- `show(Destination $destination)` — `$destination->attractions()->orderBy('name')->get()`
  (the relation is keyed `destination` → `name`, a **string** join, not an id).

**`AttractionController`**

- `index()` → `traveler.attractions.index`.
- `show(Attraction $attraction)`:
  ```php
  $reviews    = Review::with('user')->where('attraction_id', $attraction->id)->where('status', 'active')->latest()->get();
  $avgRating  = $reviews->avg('rating') ?? 0;
  $myReview   = auth()->user()->reviews()->where('attraction_id', $attraction->id)->first();
  $hasReviewed = (bool) $myReview;
  return view('traveler.attractions.show', compact('attraction','reviews','avgRating','hasReviewed','myReview'));
  ```

**`ComparisonController`** (`const BASE_DAILY_COST = 2500`)

- `index(Request $request)` — up to 3 selected `DestinationCost` names; `days` / `travelers`
  from the query; per destination
  `$baseTotal = 2500 * $travelers * $days * $dest->multiplier`, returning `{destination,
  cost_level, multiplier, total, per_day}`.

**`ReviewController`**

- `index(Request $request)` — active reviews, newest first; if `?destination=` is set it
  matches **both directions** (does the review's destination appear inside the search, or vice
  versa) so a free-text Moment place name still finds curated reviews:
  ```php
  $term = strtolower(trim($request->destination));
  $query->where(function ($q) use ($term) {
      $q->whereRaw('LOWER(destination) LIKE ?', ["%{$term}%"])
        ->orWhereRaw("? LIKE ('%' || LOWER(destination) || '%')", [$term]);
  });
  $reviews = $query->paginate(15)->appends($request->except('write'));  // 'write=1' only auto-opens the modal
  ```
- `store(Request)` — validates `destination` / `rating` (1–5) / `body` (10–2000) /
  `attraction_id` / `trip_type` (`Solo,Couple,Family,Barkada`) / `pax_count` / `spent_amount`;
  creates with `status = 'active'`; redirects to the attraction page if `attraction_id` was set.
- `update(Request, Review)` / `destroy(Request, Review)` — `abort_if($review->user_id !== auth()->id(), 403)`
  ("You can only edit/delete your own review.").
- `flag(Request, Review)` — `abort_if($review->user_id === auth()->id(), 403)` (can't flag
  your own); `reason` in `inappropriate,improvement`; idempotent per flagger
  (`if ($review->flagged_by === auth()->id()) return back()->with('success', "You've already flagged...")`);
  sets `flag_reason` / `flagged_at` / `flagged_by`.
- `markHelpful(Review)` — one vote per traveller per review:
  ```php
  $vote = ReviewHelpfulVote::firstOrCreate(['review_id' => $review->id, 'user_id' => auth()->id()]);
  if ($vote->wasRecentlyCreated) $review->increment('helpful_count');
  return back();
  ```

**`DestinationBrowser`** (Livewire)

- `plannerCityNames()` *(private)* — from `config('planner_cities')` (same list the planner's
  From/To dropdowns use, so the two never drift).
- `getDestinationsProperty()` — `Destination::withCount('attractions')->withAvg('attractions','rating')`
  **restricted to planner cities**, filtered by `search` / `country`.
- `getCountriesProperty()` — distinct countries among those cities.

**`AttractionBrowser`** (Livewire)

```php
public function getAttractionsProperty()
{
    $query = Attraction::query();
    if ($this->search)      $query->where('name', 'like', "%{$this->search}%");
    if ($this->destination) $query->where('destination', $this->destination);
    return $query->orderBy('name')->get();
}
public function getDestinationsProperty()
{
    return Attraction::orderBy('destination')->pluck('destination')->unique()->values();
}
```

**`PlaceImageService`**

- `fetchForDestination(Destination): bool` / `fetchForAttraction(Attraction): bool` — call
  `SerpApiService::searchPlaceImage()`, download the image into `destination-images` /
  `attraction-images` on the `public` disk (not a bare external URL, so it survives the source
  link breaking), and set `model->image`. Shared by the admin "Fetch Photo" buttons and the
  daily `fill-*-images` console commands.

### Frontend walkthrough

`destinations/index.blade.php` and `attractions/index.blade.php` each just host their browser
Livewire component (`@livewire('traveler.destination-browser')` etc.) with a `wire:model.live`
search box and filter dropdowns. `attractions/show.blade.php` renders the review list + a
write-review form (posts to `reviews.store` with the `attraction_id`), a per-review flag menu
(posts to `reviews.flag`), and a "helpful" button (posts to `reviews.helpful`).
`comparison/index.blade.php` is a GET form — the selections are query params.

### Data touched

Writes `reviews`, `review_helpful_votes`; (admin/console) writes `destinations.image` /
`attractions.image` + image files. Reads `destinations`, `attractions`, `destination_costs`.

### Gotchas

- `Destination::attractions()` joins on the **name string**, not a foreign key — a typo in
  `attractions.destination` orphans it from its destination page.
- Reviews are `status = 'active' | 'hidden'`; the traveller-facing list only shows `active`.
- `helpful_count` is kept in sync manually via `firstOrCreate` + `wasRecentlyCreated` — the
  unique `(review_id, user_id)` index is what makes a repeat click a no-op.
- The comparison tool's cost is a rough `2500 × multiplier × travellers × days` estimate, same
  base as `TripController@estimate`.

---

## 10. Profile / Settings / Alerts / Notifications

### What it does

- **Profile** — name + photo (conventional form) plus a 7-step **Profile Builder** wizard
  (home city, daily budget, travel style, interests + sub-interests, preferred transport,
  preferred accommodation, review). The profile steers AI suggestions.
- **Settings** — theme, a budget-buffer percentage, and four notification toggles.
- **Alerts** — the notifications inbox (mark one / mark all read).
- **Notification badge** — the unread count in the sidebar (Livewire).

### Routes

| Verb | URI | Name | Handler |
| --- | --- | --- | --- |
| GET | `/profile` | `profile.edit` | `ProfileController@edit` |
| PUT | `/profile` | `profile.update` | `ProfileController@update` |
| GET | `/profile/setup` | `profile.setup` | Livewire `ProfileBuilder` |
| GET | `/settings` | `settings.edit` | `SettingsController@edit` |
| PATCH | `/settings/theme` | `settings.theme` | `SettingsController@updateTheme` |
| PATCH | `/settings/preferences` | `settings.preferences` | `SettingsController@updatePreferences` |
| PATCH | `/settings/notifications` | `settings.notifications` | `SettingsController@updateNotifications` |
| GET | `/alerts` | `alerts.index` | `AlertController@index` |
| PATCH | `/alerts/read-all` | `alerts.read-all` | `AlertController@markAllRead` |
| PATCH | `/alerts/{notification}/read` | `alerts.read` | `AlertController@markRead` |

### Backend files

- `app/Http/Controllers/Traveler/ProfileController.php`, `SettingsController.php`, `AlertController.php`.
- `app/Livewire/Traveler/ProfileBuilder.php`, `NotificationBadge.php`.
- `app/Services/UserProfileSaver.php` — the single writer of `UserProfile`.
- `app/Support/ProfileCatalog.php`, `app/Support/PlaceCatalog.php`.
- `app/Models/UserProfile`, `User`, `Notification`.
- `config/country_cities.php`.

### Frontend files

- `resources/views/traveler/profile/edit.blade.php` — reads `ProfileBuilder::ICONS` /
  `::TRAVEL_STYLES` / `::INTERESTS` directly.
- `resources/views/traveler/settings/index.blade.php`
- `resources/views/traveler/alerts/index.blade.php`
- `resources/views/livewire/traveler/profile-builder.blade.php`
- `resources/views/livewire/traveler/notification-badge.blade.php`
- `resources/views/components/sidebar.blade.php`

### Backend walkthrough

**`ProfileController`**

- `edit()` — **`if (! $profile) return redirect()->route('profile.setup');`** — the check
  lives here (not on the sidebar href) because the sidebar is `@persist`-ed and
  `wire:navigate` would freeze a stale href. Otherwise renders `traveler.profile.edit`.
- `update(Request)` — validates `first_name` / `last_name` / `profile_photo` (image ≤2MB);
  rebuilds `full_name` keeping any existing middle name; stores/replaces the photo on
  `profile-photos` disk `public`; `unset` the key when no new file so a save doesn't wipe the
  existing photo.

**`SettingsController`**

- `edit()` → `traveler.settings.index` with `user`.
- `updateTheme(Request)` — `theme` in
  `daylight,nightflight,terracotta,retro-wanderlust,sakura-bloom,original,auto`; JSON if
  `wantsJson()`, else redirect back.
- `updatePreferences(Request)` — **only** `default_buffer_pct` (0–100). Currency fields are
  deliberately rejected here (see §0.3 — they only ever relabelled peso figures).
- `updateNotifications(Request)` — `field` in
  `notify_budget_alerts,notify_trip_reminders,notify_itinerary_reminders,ocr_auto_categorize`,
  boolean `value`; returns JSON.

**`AlertController`**

- `index()` — the user's non-draft trips (NULL-safe filter) + **all** their notifications
  (`$user->notifications()->with('trip')->latest()->paginate(20)`), including trip-less ones.
  (Two old bugs were fixed here: narrowing to a single "active" trip, and blanking the list
  for anyone who owned no trips — which hid the very notification telling a new member they'd
  been added to a trip.)
- `markRead(Notification)` — `abort_if($notification->user_id !== auth()->id(), 403)`; `is_read = true`.
- `markAllRead()` — bulk-update the user's unread notifications.

**`ProfileBuilder`** (Livewire, 7 steps; re-exports `ProfileCatalog::INTERESTS/ICONS/TRAVEL_STYLES/…`
as class consts)

- `mount()` — loads the existing profile / `AiConversationDraft`; whitelists `?return=` to
  `trips.plan.ai` or `profile.edit` (no open redirect); allows `?step=N` deep-linking;
  `applyTaraDraftIfHandedOver()` overlays answers TARA collected mid-chat (via
  `session('tara_interests_return')`).
- `missingForStep(int $step): array` *(private)* — required-field keys still missing for a
  step, in the vocabulary the browser's shake handler expects. Shared by `nextStep()` and
  `saveAndReturn()`.
- `nextStep()` — `if ($missing = $this->missingForStep($this->step)) { $this->dispatch('profile-missing', fields: $missing); return; }` then `$this->step++`.
- `prevStep()`, `selectTravelStyle(string)`, `selectTransportation(string)`,
  `selectAccommodation(string)` — toggling selectors.
- Group members: `addGroupMember(int)`, `addMemberRow()`, `removeMemberRow(int)`,
  `removeGroupMember(string $email)`.
- Interests: `syncInterests(array $interests, array $subs)` (the step-4 cards are an Alpine
  island — the browser sends the **whole** selection every change; anything not in
  `INTERESTS` is dropped and canonical order is imposed), `toggleInterest(string)`,
  `toggleSubInterest(string)`.
- `persistProfile(): bool` *(private)* — delegates to `UserProfileSaver::save()`; returns
  `false` (and sets `saveError`) only when a foreign daily budget can't be converted.
- `confirmProfile()` — `persistProfile()`; on success `redirect(route($this->returnTo ?: 'trips.plan'))`.
- `saveAndReturn()` — the quick-edit path (edit one step and return). Runs `missingForStep()`
  (because it bypasses `nextStep()`), persists, sets `session('tara_interests_picked')` when
  `fromTara`, and redirects to `returnTo ?: 'dashboard'`.
- `render()` → `livewire.traveler.profile-builder`.

**`NotificationBadge`**

```php
public function render()
{
    $count = auth()->check() ? auth()->user()->notifications()->where('is_read', false)->count() : 0;
    return view('livewire.traveler.notification-badge', ['count' => $count]);
}
```

**`UserProfileSaver`** — the one place a `UserProfile` is written (extracted verbatim from
`ProfileBuilder` so the AI planner saves through the identical path).

- `currencyForHomeCity(string): ?string` / `budgetSymbolForHomeCity(string): string` (static) —
  currency **by city** (a profile budget is always in the traveller's home currency, derived
  from where they live). First `config('country_cities')`, then
  `PlaceCatalog::DESTINATION_CURRENCIES`.
- `save(User $user, array $attributes): array` → `['ok', 'profile', 'error']`:
  ```php
  $currencyCode = self::currencyForHomeCity($homeCity);
  if ($currencyCode !== null && $currencyCode !== 'PHP' && $dailyBudget > 0) {
      $localCurrency = $currencyCode; $localBudget = $dailyBudget;      // always kept
      $liveRate = (new CurrencyConverterService())->rateToPhp($currencyCode);
      $sameAsSaved = $existing && $existing->daily_budget_currency === $currencyCode
                     && (float) $existing->daily_budget_local === (float) $dailyBudget;
      if ($liveRate !== null)      $pesoBudget = round($dailyBudget * $liveRate, 2);
      elseif ($sameAsSaved)        $pesoBudget = (float) $existing->daily_budget;   // reuse a previously live-derived figure
      else return ['ok' => false, 'profile' => null, 'error' => "I couldn't convert your {$currencyCode} budget..."];
  }
  $profile = UserProfile::updateOrCreate(['user_id' => $user->id], [ /* ... */ ]);
  if ($travelStyle === 'Group') $this->notifyNewCompanions($user, $groupEmails, $previousEmails);
  ```
- `notifyNewCompanions()` *(private)* — sends a `group_member_added` notification to newly
  listed emails that belong to a registered account (unknown emails are skipped). Reads the
  previous email list straight from the table (not the cached `$user->userProfile` relation)
  so a double-save doesn't re-notify.

### Frontend walkthrough

`profile/edit.blade.php` is a form + a read-only summary of the built profile (interest icons
from `ProfileBuilder::ICONS`), with per-section "Edit" links that deep-link into
`ProfileBuilder` at a specific `?step=`. `settings/index.blade.php` fires `PATCH` requests via
`fetch` on toggle/select change (each endpoint returns JSON). `alerts/index.blade.php` is a
paginated list with `wire`-free `PATCH` forms. `notification-badge.blade.php` shows the count
bubble; it re-renders on Livewire navigation.

### Data touched

Writes `users` (name/photo/theme/`default_buffer_pct`/notify flags), `user_profiles`,
`notifications`, profile-photo files. Reads `ai_conversation_drafts` (TARA handoff).

### Gotchas

- `ProfileController::edit` redirects to setup **on every request** when there's no profile —
  the check can't live on a `@persist`-ed sidebar link.
- Currency settings are **not** editable — `updatePreferences` only accepts `default_buffer_pct`.
- The profile daily budget is stored in pesos (`daily_budget`) with the typed original kept in
  `daily_budget_local` / `daily_budget_currency`; a foreign budget with no rate and no prior
  conversion **refuses to save**.
- `ProfileBuilder` and the AI planner both write through `UserProfileSaver` — never add a
  second write path.

---

## 11. Administrator Features

### What it does

An admin console (all URLs `/admin/*`, all named `admin.*`, all behind `auth` + `admin`) for:
platform analytics dashboard, user management (list/search/sort/export/ban/delete), curating
Destinations / Attractions / Travel Costs, moderating Reviews, key/value app config + OCR log
viewer, a summary report page, a DB backup/restore page, and the admin's own profile/settings.

### Routes (the `admin.` group in `routes/web.php`)

| Verb | URI | Name | Handler |
| --- | --- | --- | --- |
| GET | `/admin/` | `admin.home` | `Admin\DashboardController@__invoke` |
| GET | `/admin/dashboard` | `admin.dashboard` | `Admin\DashboardController@__invoke` |
| GET | `/admin/users` | `admin.users.index` | `Admin\UserController@index` |
| GET | `/admin/users/export` | `admin.users.export` | `Admin\UserController@export` |
| GET | `/admin/users/{user}` | `admin.users.show` | `Admin\UserController@show` |
| PATCH | `/admin/users/{user}/ban` | `admin.users.ban` | `Admin\UserController@ban` |
| DELETE | `/admin/users/{user}` | `admin.users.destroy` | `Admin\UserController@destroy` |
| — | `destinations` resource `only(index,update,destroy)` | `admin.destinations.*` | `Admin\DestinationController` |
| POST | `/admin/destinations/{destination}/fetch-image` | `admin.destinations.fetch-image` | `Admin\DestinationController@fetchImage` |
| — | `travel-costs` resource `except(show)` | `admin.travel-costs.*` | `Admin\TravelCostController` |
| — | `attractions` resource `only(index,store,update,destroy)` | `admin.attractions.*` | `Admin\AttractionController` |
| POST | `/admin/attractions/{attraction}/fetch-image` | `admin.attractions.fetch-image` | `Admin\AttractionController@fetchImage` |
| GET | `/admin/reviews` | `admin.reviews.index` | `Admin\ReviewModerationController@index` |
| PATCH | `/admin/reviews/{review}/hide` \| `/show` \| `/unflag` | `admin.reviews.hide/show/unflag` | `Admin\ReviewModerationController` |
| DELETE | `/admin/reviews/{review}` | `admin.reviews.destroy` | `Admin\ReviewModerationController@destroy` |
| GET/POST | `/admin/config` | `admin.config.index` / `admin.config.store` | `Admin\ConfigController` |
| GET | `/admin/ocr-logs` | `admin.ocr.index` | `Admin\ConfigController@ocrLogs` |
| GET | `/admin/reports` | `admin.reports.index` | `Admin\ReportController@index` |
| GET/POST/POST | `/admin/backup` \| `/backup/download` \| `/backup/restore` | `admin.backup.*` | `Admin\BackupController` |
| GET/PUT | `/admin/profile` | `admin.profile.edit` / `admin.profile.update` | `Admin\ProfileController` |
| GET | `/admin/settings` | `admin.settings.index` | `Admin\SettingsController@edit` |

### Backend files

All under `app/Http/Controllers/Admin/`: `DashboardController`, `UserController`,
`DestinationController`, `TravelCostController`, `AttractionController`,
`ReviewModerationController`, `ConfigController`, `ReportController`, `BackupController`,
`ProfileController`, `SettingsController`. Plus `app/Services/PlaceImageService.php`.
Views under `resources/views/admin/`. Layout `layouts/admin` + `components/admin-sidebar`.

**Dead admin code — do not study:** `routes/admin.php` (never loaded),
`app/Http/Controllers/Admin/ReviewController.php`, `Admin/IntegrationController.php`, and all
six `app/Livewire/Admin/*` components (`UserTable`, `AttractionTable`, `DestinationTable`,
`ReviewTable`, `OcrMonitor`, `KlookConfig`) + their views — every one is a `render()`-only
stub and **no blade references them**.

### Backend walkthrough

**`DashboardController::__invoke(Request)`** — a date-range analytics page.

- `resolveRange(Request)` *(private)* — parses `?range=` (`7/30/90/365`, `ytd`, `custom` with
  `start`/`end`); unknown → last 30 days; returns `[from, to, key, label, customStart, customEnd]`.
- Builds a **previous window** of equal length so every "vs. previous" figure is comparable.
- `periodStat($query, $from, $to, $prevFrom, $prevTo)` — `{total, change}` for trips / users /
  attractions / reviews.
- `budgetMetrics(...)` *(private)* — avg budget, total planned budget, avg spend, avg per
  destination, budget-overrun rate.
- `tripStatusBreakdown()` *(private)* — a live snapshot (not per-period) of Upcoming / In
  progress / Completed / Drafts, mirroring `Trip::resolvedStatus` in SQL.
- `pctChange(float $old, float $new)` *(private)* — `{pct, up, flat}`.
- Also: top destinations, trips-by-type, a 7-bucket trend, recent trips, popular attractions,
  recently curated destinations, `$isFreshInstall` (blank install → guidance instead of empty
  panels).

**`UserController`** (`const SORT_DEFAULTS`, `SORT_EXPRESSIONS`)

- `filteredQuery(Request)` *(private)* — `User::withCount('trips')->withSum('expenses','amount')`
  excluding admins (`where('role','!=','admin')->orWhereNull('role')` — NULL-safe), + optional
  name/email search.
- `applySort($query, ?string $sort, string $dir)` *(private)* — whitelisted sort (`trips` /
  `total` / `average`) via raw `ORDER BY` subquery expressions with `NULLS LAST`; falls back to
  newest-first.
- `index(Request)` — paginate 25 + totals (`totalUsers`, `bannedUsers`, `tripsThisMonth`).
- `export(Request): StreamedResponse` — CSV of the filtered users.
- `show(User)` — loads `trips`, `reviews`, `expenses`.
- `ban(User)` — `abort_if($user->id === auth()->id(), 403)`; toggles `role` between `banned`
  and `traveler`.
- `destroy(string $user)` — takes the **raw id** (not a route-model-bound `User`) on purpose:
  binding would `firstOrFail()` and 404 on a double-click; `User::find($id)?->delete()` makes a
  repeat DELETE a success. Can't delete yourself.

**`DestinationController`** — `index` (search + region filter, ordered by active-trip count
then name), `update` (name/country/region/description/image ≤5MB → `destination-images`),
`destroy`, `fetchImage(Destination, PlaceImageService)`.

**`TravelCostController`** (`const SORT_DEFAULTS`) — full resource except `show`. `index` sorts
`cost_level` / `category` by a **ranked** `CASE` expression (alphabetical would put
'Budget-friendly' above 'Very Expensive'). `store`/`update` validate `destination` (unique),
`cost_level` in `Budget-friendly,Moderate,Pricey,Very Expensive`, `multiplier` 0.1–10.
Route-model binding uses the param name `travel_cost`.

**`AttractionController`** — `index` (destination/category/region filters), `store`, `update`
(keeps stored `rating`/`estimated_cost` when the field is absent — the edit dialog has no
`estimated_cost` input and writing null wiped it), `destroy`, `fetchImage`.

**`ReviewModerationController`** — `index` (flagged first via
`orderByRaw('CASE WHEN flag_reason IS NOT NULL THEN 0 ELSE 1 END')`, filters
status/flagged/destination, only `whereNotNull('attraction_id')`), `hide` (`status='hidden'`),
`show` (`status='active'`), `destroy`, `unflag` (clears the three flag columns).

**`ConfigController`** — `index` (`AppConfig::pluck('config_value','config_key')`), `store`
(`AppConfig::updateOrCreate(['config_key' => ...], ['config_value' => ...])`), `ocrLogs`
(`OcrLog::with('user')->latest()->paginate(50)`).

**`ReportController`** — `index` — recent trips + platform stats (`total_trips`,
`gross_expenditures` = sum of recent trips' `budget_limit`, `total_reviews`, `ocr_success`) +
top destinations + a per-trip activity row pairing a trip with that traveller's matching review.

**`BackupController`** — `download()` runs `mysqldump` via `Process::run` and streams a `.sql`
attachment; `restore(Request)` uploads a `.sql` and pipes it into `mysql`. **Reads
`config('database.connections.mysql')`** — see §13, the app actually runs on Postgres.

**`Admin\ProfileController`** — `edit` / `update` (first/last name + `profile_photo`), same
pattern as the traveller one.

**`Admin\SettingsController`** — `edit()` only.

### Frontend walkthrough

`admin/dashboard.blade.php` renders a KPI strip + inline SVG charts from the controller's
arrays and a range picker (GET query params). The list pages (`users`, `destinations`,
`attractions`, `travel-costs`, `reviews`) are paginated tables with sortable column headers
(links carry `?sort=&dir=`) and modal-based create/edit forms. `admin/backup/index.blade.php`
has two POST forms (download / restore).

### Data touched

Writes/reads `users`, `destinations`, `attractions`, `destination_costs`, `reviews`,
`app_config`, `ocr_logs`; reads `trips`, `expenses`. `BackupController` shells out to the DB.

### Gotchas

- **`routes/admin.php` is not loaded** — the live admin routes are the `admin.` group at the
  bottom of `routes/web.php`.
- Admin table Livewire components are all stubs — ignore them.
- `BackupController` targets a `mysql` connection that doesn't match the Postgres/Supabase
  runtime — treat it as non-functional/legacy.
- `UserController::destroy` deliberately takes a raw id (double-click safety).

---

## 12. Data Model reference

### Relations at a glance

```text
User 1───* Trip 1───* TripBudget
  │          │   \──* Expense ──(observer)──▶ TripBudget.actual_spent, Notification
  │          │   \──* Itinerary
  │          │   \──* SavingsGoal            (also: User 1──* SavingsGoal)
  │          │   \──* GroupMember *──1 User  ← backs User::accessibleTrips()
  │          │   \──* Moment 1──* MomentPhoto
  │          │   \──* Notification
  │          \── share_code, *_selection (JSON snapshots), summary_data (JSON)
  │
  ├──1 UserProfile
  ├──* Review 1──* ReviewHelpfulVote        Review *──1 Attraction, Review *──1 User(flagged_by)
  ├──* Notification
  ├──* OcrLog
  ├──1 AiConversationDraft                  (one live row per user)
  └──* AiConversationHistory

Attraction *──1 Destination   (joined on destination string == name, NOT an id)
DestinationCost               (standalone reference table: multiplier, cost_level)
AppConfig                     (key/value store)
```

### The two authorization primitives (memorise these)

```php
// app/Models/User.php
public function trips() { return $this->hasMany(Trip::class); }   // OWNER-ONLY — backs ownership checks + admin counts

public function accessibleTrips(): \Illuminate\Database\Eloquent\Builder
{
    return Trip::where(function ($q) {
        $q->where('user_id', $this->id)
          ->orWhere(fn ($m) => $m
              ->whereHas('groupMembers', fn ($g) => $g->where('user_id', $this->id))
              ->where(fn ($d) => $d->whereNull('status')->orWhere('status', '!=', 'draft')));  // others' drafts hidden
    });
}

public function canAccessTrip(int $tripId): bool
{
    return $this->accessibleTrips()->whereKey($tripId)->exists();
}
```

- **Owner-only guards** (`abort_if($trip->user_id !== auth()->id(), 403)`): `TripController`,
  `TripDashboard`, `ItineraryController`, `SavedTrips` edit/delete/share,
  `SavingsGoalController`.
- **`canAccessTrip` guards** (group-aware): `ExpenseController`, `Traveler\ReportController@download`,
  `ItineraryManager::selectTrip`.
- **List scopes**: `accessibleTrips()` is used by the dashboard, expenses, itinerary, moments,
  savings, saved-trips, multi-trips — so a member added to a group trip isn't met with empty
  states everywhere.

### `Trip::resolvedStatus`

```php
protected function resolvedStatus(): Attribute
{
    return Attribute::make(get: function () {
        if ($stored = $this->getRawOriginal('status')) return $stored;   // a stored status always wins
        $today = \Carbon\Carbon::today();
        if ($this->start_date->gt($today)) return 'upcoming';
        if ($this->end_date->lt($today))   return 'past';
        return 'active';
    });
}
```

Read as `$trip->resolved_status`. Statuses: `draft` (wizard autosave), `upcoming`, `active`,
`past`. The dashboard, Saved Trips and Multi Trip Hub all read this accessor so their lists
can't disagree.

### Model-by-model

| Model | Table | Key points |
| --- | --- | --- |
| `User` | `users` | `role` (`traveler`/`admin`/`banned`); `password` cast `'hashed'`; notify_* + `ocr_auto_categorize` boolean; `theme`; `default_buffer_pct`. Relations: `trips` (owner), `expenses`, `savingsGoals`, `notifications`, `reviews`, `ocrLogs`, `userProfile`. Methods `accessibleTrips()`, `canAccessTrip()`. |
| `Trip` | `trips` | Money cols pesos; `budget_limit`/`budget_local` `decimal:2`; `summary_data` + all 8 `*_selection` cast `array`; `is_shared`/`is_multi_city` boolean; dates cast `date`. `static generateUniqueShareCode()`. Accessor `resolved_status`. Relations: `user`, `budgets`, `expenses`, `itinerary`, `savingsGoals`, `groupMembers`, `moments`. |
| `TripBudget` | `trip_budgets` | `category`, `estimated_cost`, `actual_spent` (`decimal:2`). Only written by `TripController::budgetStore` (+ dead `TripPlannerWizard::confirm`); kept in sync by `ExpenseObserver`. |
| `Expense` | `expenses` | `amount` always pesos; `amount_original` + `amount_currency` = real spend; `decimal:2`. `isForeign()`, `originalAmountLabel()`. Relations `trip`, `user`. Observed. |
| `SavingsGoal` | `savings_goals` | `target_amount`/`current_savings` `decimal:2`; `deadline` `date`. Relations `user`, `trip`. |
| `Itinerary` | `itinerary` *(singular)* | `type` (Flight/Hotel/Activity/Transportation); `start_datetime`/`end_datetime` `datetime`. Relation `trip`. |
| `Moment` | `moments` | `visited_date` `date`; `lat`/`lng` `decimal:7`. Relations `trip`, `photos`. |
| `MomentPhoto` | `moment_photos` | `photo_path`. Relation `moment`. FK cascade on moment delete. |
| `GroupMember` | `group_members` | `trip_id`, `user_id`. Join table for `accessibleTrips()`. |
| `Notification` | `notifications` | `type` (`expense_added`, `budget_warning`, `budget_alert`, `trip_created`, `trip_shared`, `group_member_added`, `savings_goal_reached`, `savings_goal_deposit`, `trip_reminder`, `itinerary_reminder`); `is_read` boolean. `trip_id` nullable (`nullOnDelete`). |
| `Review` | `reviews` | `status` (`active`/`hidden`); `rating`; `helpful_count` int; `flag_reason`/`flagged_at`/`flagged_by`. Relations `user`, `attraction`, `flagger`, `helpfulVotes`. |
| `ReviewHelpfulVote` | `review_helpful_votes` | Unique `(review_id, user_id)`. |
| `Attraction` | `attractions` | `rating` `decimal:1`, `estimated_cost` `decimal:2`. Relation `reviews`. |
| `Destination` | `destinations` | `attractions()` = `hasMany(Attraction, 'destination', 'name')` — **string join**. |
| `DestinationCost` | `destination_costs` | `multiplier` `decimal:3`; `cost_level`. No relations — reference table for estimates/comparison. |
| `UserProfile` | `user_profiles` | `interests`/`sub_interests`/`group_member_emails` cast `array`; `daily_budget`/`daily_budget_local` float; `daily_budget_currency`. Relation `user`. |
| `AiConversationDraft` | `ai_conversation_drafts` | One row per user (`updateOrCreate` in `Llm::dehydrate`). `messages`/`ai_package`/`profile_draft` array; `pending_profile_offer`/`building_profile` boolean. |
| `AiConversationHistory` | `ai_conversation_histories` | Archived completed AI conversations. `messages`/`ai_package` array. |
| `AppConfig` | `app_config` | `config_key` / `config_value`. |
| `OcrLog` | `ocr_logs` | `status` (`success`/`partial`/`failed`), `confidence` `decimal:2`. Relation `user`. |

### Observers (registered in `AppServiceProvider::boot()`)

- **`ExpenseObserver`** — §6.
- **`TripObserver`**:
  - `created(Trip)` — `trip_created` notification **unless** `status === 'draft'` (a draft
    autosave is not a finished trip; and once the draft is deleted a trip-less notification
    would be orphaned/permanently-unread).
  - `saved(Trip)` — mirrors `destination` (+ `leg2_destination`) into the `destinations` table
    via `syncDestination()` so Admin ▸ Destinations reflects what travellers actually pick.
  - `deleting(Trip)` — deletes receipt files + moment-photo files from disk **before** the DB
    cascade fires (the cascade never touches disk).

### Migrations

`database/migrations/` — 68 files, `2026_07_01_*` core tables → `2026_09_08_*`. Notable
evolution: `trips` gained `cover_image` / `total_cost` / `summary_data` / `status` / `trip_name`
/ `share_code` (↔ `is_shared`) / multi-city `leg2_*` / the four `*_selection` snapshots /
`destination_currency` / `budget_currency` / `budget_local`; `users` gained name fields,
`theme` (enum widened several times), `preferences`, `default_buffer_pct`; `user_profiles`
gained `travel_style` / `group_member_emails` / `preferred_*` / `daily_budget_local`;
`reviews` gained `attraction_id`, flag columns, trip fields, and `review_helpful_votes`;
`ai_conversation_drafts` / `_histories` plus incremental columns; `moments` / `moment_photos`.

Tests run against **SQLite `:memory:`** (`phpunit.xml`) — this is why several queries are
deliberately bucketed in PHP rather than using Postgres-only SQL.

---

## 13. Known quirks / dead code / gotchas

| # | Thing | Detail |
| --- | --- | --- |
| 1 | **Pesos everywhere** | Every money column is pesos. `currency_symbol()` is always `₱`. The old per-account currency setting was removed; it only relabelled without converting. |
| 2 | **`trip_budgets` is mostly empty** | Only `TripController::budgetStore` (traditional flow) and the dead `TripPlannerWizard::confirm()` write it. Planner-wizard trips store their breakdown in `trips.summary_data`, which is what `ReportService` reads. `BudgetService::summary` therefore returns zeros for wizard trips. |
| 3 | **`routes/admin.php` not loaded** | `bootstrap/app.php` only registers `web.php` + `console.php`. Live admin routes are the `admin.` group in `web.php`. |
| 4 | **`TripController::index()` is unrouted** | `trips.index` points to the wizard. `traveler/trips/index.blade.php` embeds `@livewire('traveler.multi-trip-hub')`. |
| 5 | **`BackupController` targets MySQL** | It reads `config('database.connections.mysql')` and shells `mysqldump`/`mysql`, but the app runs on Postgres/Supabase. Treat as non-functional. |
| 6 | **AI provider order is deliberate** | Mistral → OpenRouter → Groq → Gemini → Cerebras. Per code comments Gemini's key 401s / has no free quota and Cerebras has no billing (402); they're last so they fail fast. `config/services.php` uses a `gemini-flash-latest` alias so a model retirement doesn't silently kill the link. |
| 7 | **SerpApi 80/day cap** | `SerpApiService` returns `null` past 80 calls/day; callers fall back to `SerperService` then `PlaceCatalog::PACKAGE_DATA` synthetic data. |
| 8 | **Stub Livewire components** | `Traveler\ExpenseTracker`, `Traveler\BudgetComparison`, and all six `Livewire\Admin\*` are `render()`-only stubs. Real logic lives in the controllers / `MultiTripHub`. |
| 9 | **Dead controllers** | `Admin\ReviewController`, `Admin\IntegrationController`, `Traveler\SavingsController` — not routed. |
| 10 | **Owner-only vs `canAccessTrip`** | Some trip features are owner-only, some are group-aware. This is intentional (e.g. expenses are shared on a group trip, but renaming a trip is not). Check `User::accessibleTrips()` / `canAccessTrip()` before changing a guard. |
| 11 | **`Itinerary` table is singular** | `protected $table = 'itinerary';` |
| 12 | **`Destination::attractions()` is a string join** | `hasMany(Attraction::class, 'destination', 'name')` — a mismatch in `attractions.destination` orphans the row. |
| 13 | **`itinerary` "AI generate" is a template** | `ItineraryManager::generateItinerary()` builds a fixed daily schedule from `Attraction` rows — no LLM. Only the *planner wizard* calls real AI (`suggestItinerary`). |
| 14 | **`ai_conversation_drafts` = one row per user** | `Llm::dehydrate()` `updateOrCreate`s it on every request; completed conversations are copied to `ai_conversation_histories`. |
| 15 | **Timestamps are UTC** | Stored without offset; convert on display with `local_time()` / `display_tz()` (`Asia/Manila`). |

---

## Appendix A — Full route table (`routes/web.php`)

### Public / guest

| Verb | URI | Name |
| --- | --- | --- |
| GET | `/` | — (redirect or `welcome`) |
| GET | `/features` | `features` |
| GET | `/login` | `login` |
| POST | `/login` | — |
| GET | `/register` | `register` |
| POST | `/register` | — |
| POST | `/logout` | `logout` (auth) |

### Traveller (`middleware(['auth','not-admin'])`)

| Verb | URI | Name |
| --- | --- | --- |
| GET | `/dashboard` | `dashboard` |
| GET | `/dashboard/report` | `dashboard.report` |
| GET/PUT | `/profile` | `profile.edit` / `profile.update` |
| GET | `/profile/setup` | `profile.setup` |
| GET | `/settings` | `settings.edit` |
| PATCH | `/settings/theme` | `settings.theme` |
| PATCH | `/settings/preferences` | `settings.preferences` |
| PATCH | `/settings/notifications` | `settings.notifications` |
| GET | `/saved-trips` | `saved-trips` |
| GET | `/trips/import/{code}` | `trips.import` |
| GET | `/trips` | `trips.index` |
| GET | `/multi-trips` | `multi-trips.index` |
| GET | `/trips/plan` | `trips.plan` |
| GET | `/trips/plan/ai` | `trips.plan.ai` |
| GET | `/trips/type` | `trips.type` |
| GET | `/trips/create` | `trips.create` |
| POST | `/trips` | `trips.store` |
| GET | `/trips/{trip}` | `trips.show` |
| GET | `/trips/{trip}/edit` | `trips.edit` |
| PUT | `/trips/{trip}` | `trips.update` |
| DELETE | `/trips/{trip}` | `trips.destroy` |
| GET/POST | `/trips/{trip}/budget` | `trips.budget` / `trips.budgetStore` |
| GET | `/trips/{trip}/dashboard` | `trips.dashboard` |
| GET/POST | `/trips/{trip}/estimate` | `trips.estimate` / `trips.applyEstimates` |
| GET | `/expenses` | `expenses.index` |
| GET | `/expenses/create` | `expenses.create` |
| POST | `/expenses` | `expenses.store` |
| GET | `/expenses/{expense}/edit` | `expenses.edit` |
| PUT | `/expenses/{expense}` | `expenses.update` |
| DELETE | `/expenses/{expense}` | `expenses.destroy` |
| POST | `/expenses/ocr` | `expenses.ocr` |
| GET | `/attractions` | `attractions.index` |
| GET | `/attractions/{attraction}` | `attractions.show` |
| GET | `/destinations` | `destinations.index` |
| GET | `/destinations/{destination}` | `destinations.show` |
| GET | `/compare` | `compare.index` |
| GET | `/itinerary` | `itinerary.index` |
| GET | `/moments` | `moments.index` |
| POST | `/itinerary` | `itinerary.store` |
| DELETE | `/itinerary/{item}` | `itinerary.destroy` |
| GET | `/reviews` | `reviews.index` |
| POST | `/reviews` | `reviews.store` |
| PUT | `/reviews/{review}` | `reviews.update` |
| DELETE | `/reviews/{review}` | `reviews.destroy` |
| POST | `/reviews/{review}/flag` | `reviews.flag` |
| POST | `/reviews/{review}/helpful` | `reviews.helpful` |
| GET | `/alerts` | `alerts.index` |
| PATCH | `/alerts/read-all` | `alerts.read-all` |
| PATCH | `/alerts/{notification}/read` | `alerts.read` |
| GET | `/reports` | `reports.index` |
| GET | `/reports/download` | `reports.download` |
| GET | `/savings` | `savings.index` |
| GET | `/savings/create` | `savings.create` |
| POST | `/savings` | `savings.store` |
| GET | `/savings/{goal}/edit` | `savings.edit` |
| PUT | `/savings/{goal}` | `savings.update` |
| DELETE | `/savings/{goal}` | `savings.destroy` |

### Admin (`middleware(['auth','admin'])->prefix('admin')->name('admin.')`)

See the table in §11.

### Console (`routes/console.php`)

`app:send-trip-reminders` (daily), `app:send-itinerary-reminders` (hourly),
`app:fill-destination-images` (daily), `app:fill-attraction-images` (daily).

---

## Appendix B — Suggested study order

1. **§0** — the whole thing, especially the pesos model and `CurrencyConverterService`.
2. `routes/web.php` end-to-end, `bootstrap/app.php`, the three middlewares.
3. **§1 Auth** — smallest complete conventional flow.
4. **§6 Expenses** — `ExpenseController` + `ExpenseObserver` + the `create.blade.php` JS. This
   is the clearest example of a conventional flow *and* the currency/observer machinery.
5. **§12 Data Model** — `User::accessibleTrips` / `canAccessTrip`, `Trip::resolvedStatus`.
6. **§4 Traditional trips** + `BudgetService`.
7. **§2 Dashboard/Reports** — how the aggregates are built; `ReportService` vs `BudgetService`.
8. **§7 Savings**, **§5 Saved/Shared**, **§8 Itinerary/Moments**, **§9 Catalogue/Reviews**,
   **§10 Profile/Settings** — the mid-size Livewire features.
9. **§3 Planner + AI** last — it's the biggest, and it reuses concepts from everything above
   (drafts, currency, `summary_data`, selection snapshots, the provider fallback pattern also
   seen in `OcrService`).
10. **§11 Admin**, then **§13** to clear the dead code out of your mental map.
