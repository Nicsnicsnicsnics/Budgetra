@extends('layouts.app')
@section('title', 'Dashboard')

@push('styles')
<style>
/* ── Dashboard: KPI strip ──────────────────────────────────────────
   .stats-row is a hardcoded 3-column grid shared with the trip
   dashboard and the PDF report, so the 4-up variant is a modifier
   rather than a change to it. The breakpoints have to be restated
   here: .stats-row's own media rules carry the same specificity and
   would otherwise leave four columns on a narrow screen. */
.stats-row-4 { grid-template-columns: repeat(4, 1fr); }
@media (max-width: 1100px) { .stats-row-4 { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 600px)  { .stats-row-4 { grid-template-columns: 1fr; } }

/* ── Dashboard: charts row ──────────────────────────────────
   Was an inline style on the row itself, which cannot carry a media
   query — so its 636px floor (280 + 340 + the gap) held at every
   width and the page scrolled sideways on a phone. The trip stubs
   below got a breakpoint; this row never did. 900px is the one
   .stats-row already uses. */
.dash-charts-row {
    display: grid;
    grid-template-columns: minmax(280px, 1fr) minmax(340px, 1.6fr);
    gap: 16px;
    margin-bottom: 14px;
    align-items: stretch;
}
@media (max-width: 900px) { .dash-charts-row { grid-template-columns: 1fr; } }

/* ── Dashboard: panel shell ────────────────────────────────
   The surface every panel on this page sits on, which each one used
   to restate inline. .dash-bottom-grid, .alert-item* and
   .expense-row* come from style.css — they were written for this
   page and had never been used by any view. */
.dash-card {
    background: var(--bg-white);
    border: 1.5px solid var(--border);
    border-radius: 16px;
    padding: 16px 20px;
    /* A flex column so .dash-card-empty can claim the leftover height. The
       Recent Expenses card is a grid item and stretches to match the taller
       right-hand column, which left its empty state stranded at the top of a
       mostly blank card. */
    display: flex;
    flex-direction: column;
}
.dash-card + .dash-card { margin-top: 16px; }
.dash-card-head {
    display: flex; align-items: baseline; justify-content: space-between;
    gap: 12px; margin-bottom: 12px;
}
.dash-card-title { font-size: 14px; font-weight: 700; color: var(--dark); }
.dash-card-link { font-size: 12.5px; font-weight: 700; color: var(--primary); text-decoration: none; white-space: nowrap; }
.dash-card-empty {
    display: flex; flex-direction: column; align-items: center;
    justify-content: center; text-align: center; gap: 8px; padding: 22px 12px;
    /* Takes whatever height the rows would have filled, so the message sits
       in the middle of it rather than against the heading. */
    flex: 1;
}
/* Not var(--border), which the chart empty states above use: at 1.2:1
   against the card on nightflight that icon is effectively invisible. */
.dash-card-empty i { font-size: 22px; color: var(--muted); opacity: .5; }
.dash-card-empty p { margin: 0; font-size: 13px; color: var(--muted); line-height: 1.5; }

/* The whole row is a link; it must not look like one. */
.dash-alert-link { text-decoration: none; color: inherit; }
.dash-alert-link:hover .alert-item-title { color: var(--primary); }

/* ── Dashboard: active-trip stubs ──────────────────────────────────
   A boarding pass — the trip on the left, a tear-off day counter on
   the right. The perforation is a dashed border with two notches
   punched through the card's edges, filled with the page background
   so they read as holes rather than as dots. */
.trip-stub {
    position: relative;
    display: grid;
    grid-template-columns: minmax(0, 1fr) 104px;
    background: var(--bg-white);
    border: 1.5px solid var(--border);
    border-radius: 16px;
    margin-bottom: 14px;
}
.trip-stub-main { padding: 18px 20px; min-width: 0; }
.trip-stub-side {
    background: var(--border-light);
    border-left: 2px dashed var(--border);
    border-radius: 0 15px 15px 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 2px;
    padding: 10px 6px;
    text-align: center;
}
.trip-stub-notch {
    position: absolute;
    right: 104px;
    width: 16px;
    height: 16px;
    margin-right: -9px;
    border-radius: 50%;
    background: var(--bg);
    border: 1.5px solid var(--border);
}
.trip-stub-notch.is-top    { top: -9px; }
.trip-stub-notch.is-bottom { bottom: -9px; }

.trip-stub-badge {
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #fff;
    padding: 3px 10px;
    border-radius: 20px;
}
.trip-stub-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    font-weight: 600;
    color: var(--muted);
    background: var(--border-light);
    padding: 4px 9px;
    border-radius: 6px;
    white-space: nowrap;
}
.trip-stub-chip span {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    flex-shrink: 0;
}
.trip-stub-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-size: 12.5px;
    font-weight: 700;
    padding: 8px 15px;
    border-radius: 9px;
    text-decoration: none;
    border: 1.5px solid transparent;
    transition: background .18s, color .18s;
}
.trip-stub-btn.is-primary       { background: var(--primary); color: #fff; }
.trip-stub-btn.is-primary:hover { background: var(--primary-dark); color: #fff; }
/* Outlined in --primary, the way .btn-secondary already is, rather than in
   --border: on nightflight --border is #2A2F3D against a #1C202B surface,
   which is 1.2:1 — the button lost its shape entirely and read as loose
   text next to the filled one. --primary is a brand colour on every theme,
   so the outline stays visible on all of them. */
.trip-stub-btn.is-ghost         { background: transparent; color: var(--primary); border-color: var(--primary); }
.trip-stub-btn.is-ghost:hover   { background: var(--primary-light); color: var(--primary); }
.trip-stub-btn i                { font-size: 11px; }

/* ── Dashboard: report button ──────────────────────────────────────
   Was a long :style binding; a class lets :hover live in CSS and
   leaves the Alpine state to say one thing only — whether the report
   is still being generated. */
.report-btn {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--primary);
    color: #fff;
    border-radius: 10px;
    padding: 11px 20px;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    transition: background .18s, opacity .18s;
}
.report-btn:hover      { background: var(--primary-dark); color: #fff; }
.report-btn.is-loading { pointer-events: none; opacity: .75; }

/* While generating, the spinner is all that shows. The label and its icon
   are hidden rather than removed, and the spinner is centred over them, so
   the button keeps the width it had and nothing on the row shifts. */
.report-btn.is-loading > :not(.report-btn-spinner) { visibility: hidden; }
.report-btn-spinner {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

@media (max-width: 640px) {
    .trip-stub { grid-template-columns: minmax(0, 1fr); }
    .trip-stub-side {
        border-left: none;
        border-top: 2px dashed var(--border);
        border-radius: 0 0 15px 15px;
        flex-direction: row;
        gap: 8px;
    }
    .trip-stub-notch { display: none; }
}
</style>
@endpush

@section('content')

@if ($trips->isEmpty() && !auth()->user()?->userProfile)
{{-- Empty state: brand-new user with no profile set up yet — matches the
     other empty-state icon blocks' style/spacing exactly, no card wrapper.
     Skipping swaps in the same "no trips yet" prompt the branch below shows
     once the profile exists, and holds across every tab. --}}
<div class="empty-state-center" style="min-height:80vh;">
    <div style="width:64px;height:64px;border-radius:16px;background:var(--primary);display:flex;align-items:center;justify-content:center;margin-bottom:24px;">
        <x-nav-icon icon="dashboard" style="font-size:28px;color:#fff;" />
    </div>
    <div class="empty-state-swap" data-empty-when="profile">
        <h2 style="font-weight:700;font-size:22px;margin-bottom:10px;color:var(--dark);">Set up your profile first</h2>
        <p style="color:var(--muted);margin-bottom:28px;font-size:14px;max-width:320px;line-height:1.6;">Complete your travel profile before planning a trip and view trip statistics.</p>
        <a href="{{ route('profile.setup') }}" style="display:inline-flex;align-items:center;gap:10px;background:var(--primary);color:#fff;border-radius:30px;padding:14px 32px;font-size:13px;font-weight:700;letter-spacing:.06em;text-decoration:none;text-transform:uppercase;">
            <i class="fa-solid fa-user"></i> Set Up Your Profile First
        </a>
        <button type="button" class="empty-state-skip" onclick="budgetraSkipProfileSetup()">Skip for now</button>
    </div>
    <div class="empty-state-swap" data-empty-when="skipped">
        <h2 style="font-weight:700;font-size:22px;margin-bottom:10px;color:var(--dark);">No trips yet</h2>
        <p style="color:var(--muted);margin-bottom:28px;font-size:14px;max-width:320px;line-height:1.6;">Plan a trip first to see your trip statistics.</p>
        <a href="{{ route('trips.plan') }}" style="display:inline-flex;align-items:center;gap:10px;background:var(--primary);color:#fff;border-radius:30px;padding:14px 32px;font-size:13px;font-weight:700;letter-spacing:.06em;text-decoration:none;text-transform:uppercase;">
            <i class="fa-solid fa-plane"></i> Plan Your First Trip
        </a>
    </div>
</div>
@else

@if ($trips->isEmpty())
{{-- Empty state: profile is set up, but no trips yet — no header, no stat
     cards, just the standard empty-state block used across the app. --}}
<div class="empty-state-center" style="min-height:80vh;">
    <div style="width:64px;height:64px;border-radius:16px;background:var(--primary);display:flex;align-items:center;justify-content:center;margin-bottom:24px;">
        <x-nav-icon icon="dashboard" style="font-size:28px;color:#fff;" />
    </div>
    <h2 style="font-weight:700;font-size:22px;margin-bottom:10px;color:var(--dark);">No trips yet</h2>
    <p style="color:var(--muted);margin-bottom:28px;font-size:14px;max-width:320px;line-height:1.6;">Plan a trip first to see your trip statistics.</p>
    <a href="{{ route('trips.plan') }}" style="display:inline-flex;align-items:center;gap:10px;background:var(--primary);color:#fff;border-radius:30px;padding:14px 32px;font-size:13px;font-weight:700;letter-spacing:.06em;text-decoration:none;text-transform:uppercase;">
        <i class="fa-solid fa-plane"></i> Plan Your First Trip
    </a>
</div>
@else

{{-- Header --}}
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:14px;">
    <div>
        {{-- The greeting is the page's heading now that the "Dashboard" title
             is gone — kept as an h1 so the page still has one, styled down to
             the size the greeting already was. --}}
        <h1 class="text-muted" style="font-size:15px;font-weight:400;margin:0;">
            Welcome back, <span style="font-weight:700;color:var(--dark);">{{ auth()->user()?->full_name ?? '' }}</span>!
        </h1>
    </div>
    {{-- The spinner runs for as long as the report actually takes: the click
         fetches the PDF and hands the blob to the browser once it arrives,
         rather than guessing at a fixed three seconds that could end while
         the server is still rendering. --}}
    <a href="{{ route('dashboard.report') }}"
       class="report-btn"
       x-data="{ loading: false }"
       :class="{ 'is-loading': loading }"
       @click.prevent="loading = true; budgetraDownloadReport($el.href).finally(() => loading = false)">
        <i class="fa-solid fa-spinner fa-spin report-btn-spinner" x-show="loading" x-cloak></i>
        <i class="fa-solid fa-file-arrow-down"></i>
        <span>Download Report</span>
    </a>
</div>

@php
    $today       = \Carbon\Carbon::today();
    $pctOfBudget = $totalBudget > 0 ? round($totalSpent / $totalBudget * 100) : 0;
    $ongoing     = $activeTrips->where('status', 'active')->count();
    $upcoming    = $activeTrips->where('status', 'upcoming')->count();

    // Passed explicitly so a future date reads positive: the default for
    // Carbon's $absolute argument differs between Carbon 2 and 3.
    $daysToNext = $nextDeparture
        ? max(0, (int) round($today->diffInDays($nextDeparture->start_date, false)))
        : null;
@endphp

{{-- Aggregate stat cards --}}
<div class="stats-row stats-row-4" style="margin-bottom:14px;">
    <div class="stat-card">
        <div class="stat-label"><i class="fa-solid fa-coins"></i> Total Costs</div>
        <div class="stat-value" style="color:var(--secondary);">{{ currency_symbol() }}{{ number_format($totalCost, 0) }}</div>
        <div class="stat-sub">Planned across all trips</div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><i class="fa-regular fa-credit-card"></i> Total Spent</div>
        <div class="stat-value" style="color:var(--tertiary);">{{ currency_symbol() }}{{ number_format($totalSpent, 0) }}</div>
        <div class="stat-sub">{{ $pctOfBudget }}% of budget used</div>
        <div style="height:5px;background:var(--border-light);border-radius:99px;overflow:hidden;margin-top:8px;">
            {{-- Width clamped, colour not: an over-budget total stays red
                 instead of wrapping back down the ramp. --}}
            <div style="height:100%;width:{{ min(100, $pctOfBudget) }}%;background:{{ meter_color($pctOfBudget) }};border-radius:99px;"></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><i class="fa-solid fa-suitcase-rolling"></i> Active Trips</div>
        <div class="stat-value">{{ $activeTrips->count() }}</div>
        <div class="stat-sub" style="color:var(--primary);">
            @if ($activeTrips->isEmpty())
            Nothing on the calendar
            @else
            {{ $ongoing }} ongoing · {{ $upcoming }} upcoming
            @endif
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><i class="fa-solid fa-plane-departure"></i> Next Departure</div>
        @if ($nextDeparture)
        <div class="stat-value" style="color:var(--success);">{{ $daysToNext === 0 ? 'Today' : $daysToNext . 'd' }}</div>
        <div class="stat-sub">{{ trip_display_name($nextDeparture) }}</div>
        @else
        {{-- One em dash for every no-departure case, including trips running
             right now: this card answers "what's next", and a trip already
             under way isn't an answer to that. Whether any are ongoing is
             what the Active Trips card next door is for. --}}
        <div class="stat-value" style="color:var(--muted);">—</div>
        <div class="stat-sub">No upcoming trips booked</div>
        @endif
    </div>
</div>

{{-- Needs your attention.

     ExpenseObserver raises budget_warning at 50-80% of a category budget and
     budget_alert past 80%, SendTripReminders and SendItineraryReminders raise
     the other two. Until now all four went straight to /notifications and the
     dashboard never mentioned them, so the page could show a trip 140% through
     its food budget with nothing but a red bar to say so.

     Renders only when there is something unread to show: on a quiet account
     this is the page it has always been. .alert-item* is from style.css and
     was written for exactly this panel. --}}
@if ($attentionAlerts->isNotEmpty())
<div class="dash-card" style="margin-bottom:14px;">
    <div class="dash-card-head">
        <div class="dash-card-title">
            <i class="fa-solid fa-triangle-exclamation" style="color:var(--warning);margin-right:6px;"></i>Needs your attention
        </div>
        <a href="{{ route('notifications.index') }}" class="dash-card-link">All notifications &rarr;</a>
    </div>
    @foreach ($attentionAlerts as $alert)
    {{-- Title, icon and tile colour come from the same helper the
         notifications page uses, so a type is never named two things. --}}
    @php $meta = notification_meta($alert->type); @endphp
    <a href="{{ route('notifications.index') }}" class="alert-item dash-alert-link">
        <div class="alert-item-icon {{ $meta['style'] }}"><i class="fa-solid {{ $meta['icon'] }}"></i></div>
        <div style="min-width:0;">
            <div class="alert-item-title">{{ $meta['title'] }}</div>
            <div class="alert-item-sub">{{ $alert->message }}</div>
            <div class="alert-item-sub" style="opacity:.7;">
                @if ($alert->trip){{ trip_display_name($alert->trip) }} &middot; @endif{{ $alert->created_at->diffForHumans() }}
            </div>
        </div>
    </a>
    @endforeach
</div>
@endif

{{-- Active trips. Carries the spend bar that "Budget Usage by Trip" used to
     show separately — same trips, same figures, so it lived here instead.

     The heading renders either way. Active means not past and not draft, so a
     traveler whose trips have all finished still reaches this page with stats
     and charts to look at — and used to find the whole section silently
     missing, with no hint that planning another one was the way out of it. --}}
<div style="display:flex;align-items:baseline;justify-content:space-between;gap:12px;margin-bottom:12px;">
    <div style="font-size:15px;font-weight:700;color:var(--dark);">Active Trips</div>
    <a href="{{ route('saved-trips') }}" style="font-size:12.5px;font-weight:700;color:var(--primary);text-decoration:none;">View all trips →</a>
</div>

@if ($activeTrips->isNotEmpty())

{{-- Three stubs, not all of them: a boarding pass is tall, and ten active
     trips pushed the charts entirely below the fold. Capped here rather
     than in the controller because the Active Trips KPI above counts the
     same collection and has to keep seeing every one. --}}
@foreach ($activeTrips->take(3) as $trip)
@php
    $spent     = (float) ($trip->total_spent ?? 0);
    $budget    = (float) ($trip->budget_limit ?? 0);
    $pctRaw    = $trip->pct_used;            // unclamped, so the colour can go past 100
    $isOngoing = $trip->status === 'active';
    $typeLabel = strtoupper($trip->travel_type ?? 'Solo');
    $typeColor = $typeLabel === 'GROUP' ? '#A855F7' : '#14B8A6';

    // Same label and colour arms as the Saved Trips card, so one trip reads
    // identically on both pages.
    $statusLabel = match ($trip->status) {
        'active'   => 'Ongoing',
        'upcoming' => 'Upcoming',
        'past'     => 'Finished',
        default    => ucfirst($trip->status),
    };
    $statusColor = match ($trip->status) {
        'active'   => '#22C55E',
        'upcoming' => '#3B82F6',
        default    => 'var(--muted)',
    };

    // Day counter, inclusive of both ends — a trip that starts and finishes
    // on the same day is "D1 of 1", not "D1 of 0".
    $tripDays = max(1, (int) round($trip->start_date->diffInDays($trip->end_date, false)) + 1);
    $dayIndex = min($tripDays, max(1, (int) round($trip->start_date->diffInDays($today, false)) + 1));
    $daysLeft = max(0, (int) round($today->diffInDays($trip->start_date, false)));
    $chips    = $tripChips[$trip->id] ?? [];
@endphp
<div class="trip-stub">
    <div class="trip-stub-notch is-top"></div>
    <div class="trip-stub-notch is-bottom"></div>

    <div class="trip-stub-main">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
            <span class="trip-stub-badge" style="background:{{ $statusColor }};">{{ $statusLabel }}</span>
            <span class="trip-stub-badge" style="background:{{ $typeColor }};">{{ $typeLabel }}</span>
        </div>

        <div style="font-size:18px;font-weight:700;color:var(--dark);line-height:1.3;margin-bottom:3px;">
            {{ trip_display_name($trip) }}
        </div>
        <div style="font-size:12.5px;color:var(--muted);margin-bottom:14px;">
            {{ $trip->destination_code ?? place_with_country($trip->destination) }} ·
            {{ $trip->start_date->format('M j') }} – {{ $trip->end_date->format('M j, Y') }}
            @if (($trip->num_travelers ?? 1) > 1)
            · {{ $trip->num_travelers }} travelers
            @endif
        </div>

        <div style="display:flex;align-items:baseline;justify-content:space-between;gap:10px;margin-bottom:6px;">
            {{-- Destination currency when Saved Trips would convert it, pesos
                 when it wouldn't — the two views show the same money. --}}
            <span style="font-size:13px;color:var(--muted);">
                <b style="font-size:15px;font-weight:700;color:var(--dark);">{{ trip_amount($trip, $spent) }}</b>
                / {{ trip_amount($trip, $budget) }}
            </span>
            <span style="font-size:12.5px;font-weight:700;color:var(--dark);">{{ $pctRaw }}%</span>
        </div>
        <div style="height:7px;background:var(--border-light);border-radius:99px;overflow:hidden;margin-bottom:14px;">
            {{-- Same clamped-width / raw-colour rule as every other spend meter
                 in the app, so this agrees with Multi-Trip Hub. --}}
            <div style="height:100%;width:{{ min(100, $pctRaw) }}%;background:{{ meter_color($pctRaw) }};border-radius:99px;transition:width .4s ease;"></div>
        </div>

        @if (!empty($chips))
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px;">
            @foreach ($chips as $chip)
            <span class="trip-stub-chip">
                <span style="background:{{ $chip['color'] }};"></span>
                {{ $chip['label'] }} {{ trip_amount($trip, $chip['value']) }}
            </span>
            @endforeach
        </div>
        @endif

        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="{{ route('expenses.create', ['trip_id' => $trip->id]) }}" class="trip-stub-btn is-primary">
                <i class="fa-solid fa-receipt"></i> Add Expense
            </a>
            {{-- Saved Trips, not the per-trip dashboard: that page is a
                 read-only summary, while everything a traveler wants to do
                 with a trip from here — rename it, share it, add members,
                 delete it — lives on the card in Saved Trips. Matches the
                 "View all trips" link above, plain href and all. --}}
            <a href="{{ route('saved-trips') }}" class="trip-stub-btn is-ghost">
                View Trip <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>
    </div>

    <div class="trip-stub-side">
        @if ($isOngoing)
        <div style="font-size:21px;font-weight:800;color:var(--primary);line-height:1;">D{{ $dayIndex }}</div>
        <div style="font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);">of {{ $tripDays }}</div>
        @else
        <div style="font-size:21px;font-weight:800;color:var(--primary);line-height:1;">{{ $daysLeft }}</div>
        <div style="font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);">{{ $daysLeft === 1 ? 'day left' : 'days left' }}</div>
        @endif
        <i class="fa-solid fa-plane" style="font-size:14px;color:var(--muted);opacity:.55;margin-top:5px;"></i>
    </div>
</div>
@endforeach

@if ($activeTrips->count() > 3)
@php $hidden = $activeTrips->count() - 3; @endphp
<div style="margin:-4px 0 14px;font-size:12.5px;color:var(--muted);">
    +{{ $hidden }} more active {{ Str::plural('trip', $hidden) }} &middot;
    <a href="{{ route('saved-trips') }}" style="color:var(--primary);font-weight:700;text-decoration:none;">View all</a>
</div>
@endif

@else
{{-- Their other trips are all in the past, so the wording is "no active
     trips" rather than the page-level "no trips yet", and the button says
     Plan a Trip rather than Plan Your First Trip. --}}
<div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:16px;padding:32px 20px;margin-bottom:14px;display:flex;flex-direction:column;align-items:center;text-align:center;">
    <div style="width:48px;height:48px;border-radius:14px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;margin-bottom:14px;">
        <i class="fa-solid fa-suitcase-rolling" style="font-size:20px;color:var(--primary);"></i>
    </div>
    <div style="font-size:15px;font-weight:700;color:var(--dark);margin-bottom:6px;">No active trips yet</div>
    <p style="color:var(--muted);font-size:13px;margin:0 0 20px;max-width:320px;line-height:1.6;">Your trips have all wrapped up. Plan another one to see it tracked here.</p>
    <a href="{{ route('trips.plan') }}" style="display:inline-flex;align-items:center;gap:10px;background:var(--primary);color:#fff;border-radius:30px;padding:12px 28px;font-size:12.5px;font-weight:700;letter-spacing:.06em;text-decoration:none;text-transform:uppercase;">
        <i class="fa-solid fa-plane"></i> Plan a Trip
    </a>
</div>
@endif

{{-- Charts row --}}
<div class="dash-charts-row">

    {{-- Donut: spend by category --}}
    <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:16px;padding:16px 20px;">
        <div style="font-size:14px;font-weight:700;color:var(--dark);margin-bottom:2px;">Spending by Category</div>
        <div style="font-size:12px;color:var(--muted);margin-bottom:12px;">Across all your trips</div>

        @if (empty($categorySpend))
        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:20px 12px;">
            <i class="fa-solid fa-chart-pie" style="font-size:24px;color:var(--border);margin-bottom:8px;"></i>
            <p style="color:var(--muted);font-size:13px;margin:0;">Log an expense to see your spending breakdown here.</p>
        </div>
        @else
        @php
            $csTotal = array_sum(array_column($categorySpend, 'value'));
            $r  = 48; $cx = 64; $cy = 64;
            $circumference = 2 * M_PI * $r;
            $offsetAcc = 0;
        @endphp
        <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
            <svg width="128" height="128" viewBox="0 0 128 128" role="img" aria-label="Donut chart of spending by category" style="flex-shrink:0;transform:rotate(-90deg);">
                <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}" fill="none" stroke="var(--border-light)" stroke-width="16"></circle>
                @foreach ($categorySpend as $seg)
                @php
                    $frac      = $csTotal > 0 ? $seg['value'] / $csTotal : 0;
                    $segLength = $frac * $circumference;
                    $gap       = 2; // 2px surface gap between segments
                @endphp
                <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}" fill="none"
                        stroke="{{ $seg['color'] }}" stroke-width="16"
                        stroke-dasharray="{{ max(0, $segLength - $gap) }} {{ $circumference - $segLength + $gap }}"
                        stroke-dashoffset="{{ -$offsetAcc }}"
                        stroke-linecap="butt">
                    <title>{{ $seg['label'] }}: {{ currency_symbol() }}{{ number_format($seg['value'], 0) }}</title>
                </circle>
                @php $offsetAcc += $segLength; @endphp
                @endforeach
            </svg>
            <div style="flex:1;min-width:140px;display:flex;flex-direction:column;gap:10px;">
                @foreach ($categorySpend as $seg)
                @php $pctLabel = $csTotal > 0 ? round($seg['value'] / $csTotal * 100) : 0; @endphp
                <div style="display:flex;align-items:center;gap:8px;">
                    <span style="width:9px;height:9px;border-radius:50%;background:{{ $seg['color'] }};flex-shrink:0;"></span>
                    <span style="flex:1;font-size:12px;color:var(--dark);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $seg['label'] }}</span>
                    <span style="font-size:12px;font-weight:700;color:var(--dark);white-space:nowrap;">{{ $pctLabel }}%</span>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- Bars: monthly spend trend --}}
    <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:16px;padding:16px 20px;display:flex;flex-direction:column;">
        <div style="font-size:14px;font-weight:700;color:var(--dark);margin-bottom:2px;">Monthly Spending</div>
        <div style="font-size:12px;color:var(--muted);margin-bottom:12px;">Last 6 months</div>

        @php $msMax = collect($monthlySpend)->max('value') ?? 0; @endphp

        {{-- Every month at zero drew six 2%-high stubs with their amounts
             hidden — a chart that looked broken rather than empty. Matched to
             the donut's empty state next door. --}}
        @if ($msMax <= 0)
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:20px 12px;">
            <i class="fa-solid fa-chart-column" style="font-size:24px;color:var(--border);margin-bottom:8px;"></i>
            <p style="color:var(--muted);font-size:13px;margin:0;">Log an expense to see how your spending moves month to month.</p>
        </div>
        @else
        <div style="flex:1;display:flex;align-items:flex-end;gap:14px;min-height:110px;padding-top:8px;">
            @foreach ($monthlySpend as $m)
            @php $barPct = max(2, round($m['value'] / $msMax * 100)); @endphp
            <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:8px;height:100%;justify-content:flex-end;">
                <span style="font-size:11px;font-weight:700;color:var(--dark);{{ $m['value'] > 0 ? '' : 'visibility:hidden;' }}">{{ currency_symbol() }}{{ number_format($m['value'], 0) }}</span>
                <div title="{{ $m['label'] }}: {{ currency_symbol() }}{{ number_format($m['value'], 2) }}"
                     style="width:100%;max-width:36px;height:{{ $barPct }}%;background:var(--primary);border-radius:6px 6px 3px 3px;transition:height .3s ease;"></div>
                <span style="font-size:11px;color:var(--muted);font-weight:600;">{{ $m['label'] }}</span>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

{{-- Bottom grid: what happened, what is next, what it is being saved for.
     .dash-bottom-grid (1fr 320px, collapsing at 1100px) is from style.css and
     had never been used by a view. --}}
<div class="dash-bottom-grid">

    {{-- Recent expenses --}}
    <div class="dash-card">
        <div class="dash-card-head">
            <div class="dash-card-title">Recent Expenses</div>
            <a href="{{ route('expenses.index') }}" class="dash-card-link">View all &rarr;</a>
        </div>

        @php
            // Glyph only, not colour: .expense-row-icon's tile is one themed
            // pair for every row, so the icon is what tells the categories
            // apart. The donut next door is where the palette lives.
            $catIcons = [
                'Transportation'     => 'fa-car',
                'Accommodation'      => 'fa-bed',
                'Food'               => 'fa-utensils',
                'Activities'         => 'fa-camera',
                'Shopping'           => 'fa-bag-shopping',
                'Emergency Expenses' => 'fa-kit-medical',
            ];
        @endphp

        @forelse ($recentExpenses as $expense)
        <div class="expense-row">
            <div class="expense-row-icon">
                <i class="fa-solid {{ $catIcons[$expense->category] ?? 'fa-receipt' }}"></i>
            </div>
            <div class="expense-row-name" style="min-width:0;">
                <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    {{ $expense->description ?: $expense->category }}
                </div>
                <div class="expense-row-time">
                    {{ trip_display_name($expense->trip) }} &middot; {{ $expense->expense_date->format('M j') }}
                </div>
            </div>
            {{-- The figure they actually handed over: originalAmountLabel()
                 prints the foreign amount when the expense was paid in one,
                 and pesos otherwise. --}}
            <div class="expense-row-amt">{{ $expense->originalAmountLabel() }}</div>
        </div>
        @empty
        <div class="dash-card-empty">
            <i class="fa-solid fa-receipt"></i>
            <p>Nothing logged yet. Add an expense and it will show up here.</p>
        </div>
        @endforelse
    </div>

    <div>
        {{-- Today & next up --}}
        <div class="dash-card">
            <div class="dash-card-head">
                <div class="dash-card-title">Today &amp; Next Up</div>
                <a href="{{ route('itinerary.index') }}" class="dash-card-link">Itinerary &rarr;</a>
            </div>

            @php
                // The same four arms getEventsProperty() colours the calendar
                // with, so an item looks the same in both places.
                $evMeta = [
                    'Flight'         => ['icon' => 'fa-plane',  'color' => '#1D4ED8'],
                    'Hotel'          => ['icon' => 'fa-bed',    'color' => '#16A34A'],
                    'Transportation' => ['icon' => 'fa-car',    'color' => '#D97706'],
                    'Activity'       => ['icon' => 'fa-camera', 'color' => '#8B3A10'],
                ];
            @endphp

            @php
                // Filtered before the loop rather than skipped inside it, so
                // $loop->first means "first heading actually drawn" — with a
                // @continue it still meant "Today", and a day with nothing on
                // it left "Coming up" wearing Today's top margin.
                $eventGroups = array_values(array_filter(
                    [['Today', $todayEvents], ['Coming up', $upcomingEvents]],
                    fn ($group) => $group[1]->isNotEmpty()
                ));
            @endphp

            @if (empty($eventGroups))
            <div class="dash-card-empty">
                <i class="fa-regular fa-calendar"></i>
                <p>No scheduled events on your active trips.</p>
            </div>
            @else
                @foreach ($eventGroups as [$label, $events])
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin:{{ $loop->first ? '0' : '14px' }} 0 6px;">{{ $label }}</div>
                @foreach ($events as $item)
                @php $ev = $evMeta[$item->type] ?? $evMeta['Activity']; @endphp
                <div class="alert-item" style="padding:9px 0;">
                    <div class="alert-item-icon" style="background:var(--border-light);color:{{ $ev['color'] }};">
                        <i class="fa-solid {{ $ev['icon'] }}"></i>
                    </div>
                    <div style="min-width:0;">
                        <div class="alert-item-title" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $item->title }}</div>
                        <div class="alert-item-sub">
                            {{ $label === 'Today' ? $item->start_datetime->format('g:i A') : $item->start_datetime->format('M j, g:i A') }}
                            @if ($item->trip) &middot; {{ trip_display_name($item->trip) }} @endif
                        </div>
                    </div>
                </div>
                @endforeach
                @endforeach
            @endif
        </div>

        {{-- Savings goals --}}
        <div class="dash-card">
            <div class="dash-card-head">
                <div class="dash-card-title">Savings Goals</div>
                <a href="{{ route('savings.index') }}" class="dash-card-link">View all &rarr;</a>
            </div>

            @forelse ($savingsSnapshot as $goal)
            <div style="{{ $loop->last ? '' : 'margin-bottom:14px;' }}">
                <div style="display:flex;align-items:baseline;justify-content:space-between;gap:10px;margin-bottom:6px;">
                    <span style="font-size:13px;font-weight:600;color:var(--dark);min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $goal['name'] }}</span>
                    <span style="font-size:12.5px;font-weight:700;color:var(--dark);">{{ $goal['pct'] }}%</span>
                </div>
                <div style="height:7px;background:var(--border-light);border-radius:99px;overflow:hidden;margin-bottom:5px;">
                    {{-- The 'progress' ramp, not the default 'spend' one: more
                         saved is better, so this runs red to teal. --}}
                    <div style="height:100%;width:{{ $goal['pct'] }}%;background:{{ meter_color((float) $goal['pct'], 'progress') }};border-radius:99px;transition:width .4s ease;"></div>
                </div>
                <div style="font-size:11.5px;color:var(--muted);">
                    {{ currency_symbol() }}{{ number_format($goal['saved'], 0) }} of {{ currency_symbol() }}{{ number_format($goal['target'], 0) }}
                    @if ($goal['deadline']) &middot; by {{ $goal['deadline']->format('M j, Y') }} @endif
                </div>
            </div>
            @empty
            {{-- A goal is created per trip the first time /savings is opened,
                 so "none yet" here usually means that page has never been
                 visited, not that nothing is being saved. --}}
            <div class="dash-card-empty">
                <i class="fa-solid fa-piggy-bank"></i>
                <p>Open Saving Goals to start tracking what each trip needs.</p>
            </div>
            @endforelse
        </div>
    </div>
</div>

@endif

@endif

@endsection

@push('scripts')
<script>
    // Fetches the PDF so the button can show a spinner for the real duration
    // of the request, then hands the finished file to the browser. A plain
    // <a href> download gives no signal at all while the server renders.
    //
    // Defined as a global rather than an Alpine component so it exists the
    // moment the page is parsed, with no dependency on when Alpine boots.
    window.budgetraDownloadReport = async function (url) {
        try {
            const res = await fetch(url, { headers: { Accept: 'application/pdf' } });
            if (!res.ok) throw new Error('HTTP ' + res.status);

            const blob = await res.blob();
            const href = URL.createObjectURL(blob);
            const link = document.createElement('a');

            // Matches the server's filename (DashboardController::downloadReport).
            const d = new Date();
            const pad = (n) => String(n).padStart(2, '0');

            link.href = href;
            link.download = 'dashboard-report-' +
                d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + '.pdf';

            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(href);
        } catch (e) {
            // Never leave the traveller without their report: fall back to
            // letting the browser request it the ordinary way.
            window.location.href = url;
        }
    };
</script>
@endpush
