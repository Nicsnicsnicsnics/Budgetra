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
