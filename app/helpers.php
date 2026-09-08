<?php

/*
 * Every money column in this app is pesos: expenses.amount, trips.budget_limit,
 * trips.total_cost, trips.actual_spent, savings_goals.*, user_profiles.daily_budget.
 *
 * These used to return a per-account symbol from Settings → Preferences, which
 * defaulted to '$'/USD and converted nothing. That symbol was printed against
 * peso figures at ~40 sites, INCLUDING the expense and savings amount inputs —
 * so a traveller on the default setting typed 100 for a $100 dinner and the app
 * stored ₱100, then fed that into budget alerts and trip totals.
 *
 * Real conversion still exists where it means something: a trip displays in its
 * destination currency via trips.destination_currency and a live rate (see
 * SavedTrips and SavingsGoalManager). The account-level label was never that,
 * so it is gone. These stay as functions because ~40 call sites use them.
 */
if (! function_exists('currency_symbol')) {
    function currency_symbol(): string
    {
        return '₱';
    }
}

if (! function_exists('currency_code')) {
    function currency_code(): string
    {
        return 'PHP';
    }
}

/*
 * The traveller's OWN currency, from the country they picked at registration —
 * distinct from currency_code() above, which is the ledger everything is stored
 * in. A Canadian plans in CAD and Budgetra stores pesos; both are true at once.
 *
 * Single definition on purpose: the country → currency lookup was already
 * duplicated across ProfileBuilder and the trip catalogues, and a fourth copy is
 * how they drift apart.
 */
if (! function_exists('home_currency')) {
    function home_currency(): string
    {
        $country = auth()->check() ? (auth()->user()->country ?? null) : null;

        // country is nullable at registration, so PHP is the honest default.
        return $country
            ? (\App\Support\PlaceCatalog::COUNTRY_CURRENCIES[$country] ?? 'PHP')
            : 'PHP';
    }
}

if (! function_exists('home_currency_symbol')) {
    function home_currency_symbol(): string
    {
        $code = home_currency();

        // Falls back to the code itself, never to '₱' — labelling a CAD field
        // with a peso sign is the exact mistake this is here to prevent.
        return \App\Support\PlaceCatalog::CURRENCY_SYMBOLS[$code] ?? $code . ' ';
    }
}

if (! function_exists('place_with_country')) {
    /**
     * "Toronto, Canada" for display. Falls back to the bare place when the
     * country isn't known, and to null when there's no place at all — so a view
     * can still choose its own em dash.
     *
     * Thin wrapper over PlaceCatalog::withCountry() purely so views aren't
     * littered with the fully-qualified class name.
     */
    function place_with_country(?string $place): ?string
    {
        return \App\Support\PlaceCatalog::withCountry($place);
    }
}

if (! function_exists('display_tz')) {
    // Timestamps are stored as UTC ('timestamp without time zone' columns, so
    // they carry no offset of their own and app.timezone stays UTC to keep
    // that reading correct). Formatting them raw showed travelers a time up
    // to a full day behind their own clock — a moment posted 02:29 Manila
    // rendered as "Aug 18, 6:29 PM". Convert on the way out instead.
    function display_tz(): string
    {
        return config('app.display_timezone', 'Asia/Manila');
    }
}

if (! function_exists('local_time')) {
    /**
     * A stored UTC timestamp as wall-clock time in the display timezone.
     * Returns null for null so callers can chain ?-> safely.
     */
    function local_time($date): ?\Illuminate\Support\Carbon
    {
        if ($date === null) return null;

        return \Illuminate\Support\Carbon::parse($date)->setTimezone(display_tz());
    }
}

if (! function_exists('meter_color')) {
    /**
     * Fill colour for a progress meter, chosen by what its ratio *means*.
     *
     * Two meters in this app look alike and read opposite ways, so the ramp
     * cannot simply follow the number:
     *
     *   'spend'    share of a budget used — more is worse, so it runs
     *              teal → amber → red as the bar fills.
     *   'progress' share of a savings goal reached — more is better, so the
     *              same three colours run the other way, red → amber → teal.
     *              Colouring a nearly-finished goal red would invert its
     *              meaning.
     *
     * Returns a CSS var reference; see --meter-* in public/css/style.css.
     */
    function meter_color(float $percent, string $kind = 'spend'): string
    {
        if ($kind === 'progress') {
            return match (true) {
                $percent >= 70 => 'var(--meter-good)',
                $percent >= 30 => 'var(--meter-warn)',
                default        => 'var(--meter-bad)',
            };
        }

        // Deliberately not clamped: over-budget arrives here above 100 and
        // must stay red rather than wrapping back down the ramp.
        return match (true) {
            $percent >= 90 => 'var(--meter-bad)',
            $percent >= 70 => 'var(--meter-warn)',
            default        => 'var(--meter-good)',
        };
    }
}

if (! function_exists('trip_apply_display_currency')) {
    /**
     * Decide which currency a trip's money should be shown in, and hang the
     * answer off the model as display_currency_code / display_currency_symbol
     * / display_rate.
     *
     * Once a trip is Upcoming or Ongoing, its money is more useful in the
     * destination's own currency than in pesos — but only when a live rate is
     * actually reachable. A failed lookup falls back to the usual peso display
     * rather than blocking the page or showing an error on a passive card.
     * Draft and Past trips stay in pesos.
     *
     * Deliberately NOT currency_code()/currency_symbol(): that is a separate,
     * unsynced account Settings field that defaulted to USD for every account
     * regardless of the traveler's real currency, and was mislabeling genuine
     * peso figures as "USD 141,106".
     *
     * Lives here rather than in SavedTrips because the dashboard's trip stubs
     * show the same figures and have to agree with the Saved Trips cards.
     * CurrencyConverterService memoises per request and caches across them,
     * so calling this per trip costs at most one lookup per currency.
     */
    function trip_apply_display_currency($trip): void
    {
        $trip->setAttribute('display_currency_code', null);
        $trip->setAttribute('display_currency_symbol', null);
        $trip->setAttribute('display_rate', null);

        if (! in_array($trip->status, ['active', 'upcoming'], true) || ! $trip->destination_currency) {
            return;
        }

        $rate = (new \App\Services\CurrencyConverterService())->rateToPhp($trip->destination_currency);

        if ($rate === null) {
            return;
        }

        $trip->setAttribute('display_currency_code', $trip->destination_currency);
        $trip->setAttribute('display_currency_symbol',
            \App\Support\PlaceCatalog::CURRENCY_SYMBOLS[$trip->destination_currency] ?? $trip->destination_currency);
        $trip->setAttribute('display_rate', $rate);
    }
}

if (! function_exists('trip_amount')) {
    /**
     * A peso amount formatted for one trip's card — converted when
     * trip_apply_display_currency() found a live rate, plain pesos otherwise.
     */
    function trip_amount($trip, float $pesoAmount, int $decimals = 0): string
    {
        if ($trip->display_rate) {
            return $trip->display_currency_code . ' ' . number_format($pesoAmount / $trip->display_rate, $decimals);
        }

        return '₱' . number_format($pesoAmount, $decimals);
    }
}

if (! function_exists('trip_display_name')) {
    /**
     * What to call a trip on a card. The traveller can rename a trip from
     * Saved Trips, so a name they set wins; otherwise the destination stands
     * in. 'Draft' is a literal value in the destination column, not a place,
     * and reads like one next to a status badge.
     */
    function trip_display_name($trip): string
    {
        return $trip->trip_name
            ?: (($trip->destination && $trip->destination !== 'Draft') ? $trip->destination : 'No destination set');
    }
}
