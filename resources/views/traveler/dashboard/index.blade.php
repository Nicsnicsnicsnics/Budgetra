@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')

@if ($trips->isEmpty() && !auth()->user()?->userProfile)
{{-- Empty state: brand-new user with no profile set up yet — matches the
     other empty-state icon blocks' style/spacing exactly, no card wrapper. --}}
<div class="empty-state-center" style="min-height:80vh;">
    <div style="width:64px;height:64px;border-radius:16px;background:var(--primary);display:flex;align-items:center;justify-content:center;margin-bottom:24px;">
        <i class="fa-solid fa-house" style="font-size:28px;color:#fff;"></i>
    </div>
    <h2 style="font-weight:700;font-size:22px;margin-bottom:10px;color:var(--dark);">Set up your profile first</h2>
    <p style="color:var(--muted);margin-bottom:28px;font-size:14px;max-width:320px;line-height:1.6;">Complete your travel profile before planning a trip and view trip statistics.</p>
    <a href="{{ route('profile.setup') }}" style="display:inline-flex;align-items:center;gap:10px;background:var(--primary);color:#fff;border-radius:30px;padding:14px 32px;font-size:13px;font-weight:700;letter-spacing:.06em;text-decoration:none;text-transform:uppercase;">
        <i class="fa-solid fa-user"></i> Set Up Your Profile First
    </a>
</div>
@else

@if ($trips->isEmpty())
{{-- Empty state: profile is set up, but no trips yet — no header, no stat
     cards, just the standard empty-state block used across the app. --}}
<div class="empty-state-center" style="min-height:80vh;">
    <div style="width:64px;height:64px;border-radius:16px;background:var(--primary);display:flex;align-items:center;justify-content:center;margin-bottom:24px;">
        <i class="fa-solid fa-house" style="font-size:28px;color:#fff;"></i>
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
        <h1>Dashboard</h1>
        <p class="text-muted">Welcome back, {{ auth()->user()?->full_name ?? '' }}!</p>
    </div>
    <a href="{{ route('dashboard.report') }}"
       x-data="{ loading: false, hover: false }"
       @click="loading = true; setTimeout(() => loading = false, 3000)"
       @mouseenter="hover = true" @mouseleave="hover = false"
       :style="'display:inline-flex;align-items:center;gap:8px;color:#fff;border-radius:10px;padding:11px 20px;font-size:13px;font-weight:700;text-decoration:none;transition:background .18s;background:' + (loading ? 'var(--primary)' : (hover ? 'var(--primary-dark)' : 'var(--primary)')) + ';' + (loading ? 'pointer-events:none;opacity:.75;' : '')">
        <i class="fa-solid fa-spinner fa-spin" x-show="loading" x-cloak></i>
        <i class="fa-solid fa-file-arrow-down" x-show="!loading"></i>
        <span>Download Report</span>
    </a>
</div>

{{-- Aggregate stat cards --}}
<div class="stats-row" style="margin-bottom:14px;">
    <div class="stat-card">
        <div class="stat-card-accent" style="background:var(--primary);"></div>
        <div class="stat-label"><i class="fa-solid fa-suitcase-rolling"></i> Total Trips</div>
        <div class="stat-value">{{ $trips->count() }}</div>
        <div class="stat-sub" style="color:var(--primary);">All time</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-accent" style="background:var(--secondary);"></div>
        <div class="stat-label"><i class="fa-solid fa-coins"></i> Total Budget</div>
        <div class="stat-value" style="color:var(--secondary);">{{ currency_symbol() }}{{ number_format($totalBudget, 0) }}</div>
        <div class="stat-sub">Across all trips</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-accent" style="background:var(--tertiary);"></div>
        <div class="stat-label"><i class="fa-regular fa-credit-card"></i> Total Spent</div>
        <div class="stat-value" style="color:var(--tertiary);">{{ currency_symbol() }}{{ number_format($totalSpent, 0) }}</div>
        <div class="stat-sub">Across all trips</div>
    </div>
</div>

{{-- Charts row --}}
<div style="display:grid;grid-template-columns:minmax(280px,1fr) minmax(340px,1.6fr);gap:16px;margin-bottom:14px;align-items:stretch;">

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

        @php $msMax = max(1, collect($monthlySpend)->max('value')); @endphp
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
    </div>
</div>

{{-- Budget usage per active trip --}}
@php $chartActiveTrips = $trips->whereIn('status', ['upcoming', 'active']); @endphp
@if ($chartActiveTrips->isNotEmpty())
<div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:16px;padding:16px 20px;margin-bottom:14px;">
    <div style="font-size:14px;font-weight:700;color:var(--dark);margin-bottom:12px;">Budget Usage by Trip</div>
    <div style="display:flex;flex-direction:column;gap:12px;">
        @foreach ($chartActiveTrips as $trip)
        @php
            $spent = $trip->total_spent ?? 0;
            $pct   = $trip->budget_limit > 0 ? min(100, round($spent / $trip->budget_limit * 100)) : 0;
            $over  = $spent > $trip->budget_limit && $trip->budget_limit > 0;
        @endphp
        <div>
            <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:6px;">
                <span style="font-size:13px;font-weight:600;color:var(--dark);">{{ $trip->trip_name ?? $trip->destination }}</span>
                <span style="font-size:12px;color:var(--muted);">{{ currency_symbol() }}{{ number_format($spent, 0) }} / {{ currency_symbol() }}{{ number_format($trip->budget_limit, 0) }}</span>
            </div>
            <div style="height:8px;background:var(--border-light);border-radius:99px;overflow:hidden;">
                <div style="height:100%;width:{{ $pct }}%;background:{{ $over ? 'var(--danger)' : 'var(--primary)' }};border-radius:99px;transition:width .4s ease;"></div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

@endif

@endif

@endsection
