<?php

if (! function_exists('currency_symbol')) {
    // The signed-in user's chosen display currency symbol (Settings →
    // Preferences), falling back to the Peso for guests/unauthenticated
    // contexts where there's no user record to read a preference from.
    function currency_symbol(): string
    {
        return auth()->check() ? (auth()->user()->currency_symbol ?: '₱') : '₱';
    }
}

if (! function_exists('currency_code')) {
    function currency_code(): string
    {
        return auth()->check() ? (auth()->user()->currency_code ?: 'PHP') : 'PHP';
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
