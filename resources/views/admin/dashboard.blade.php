@extends('layouts.admin')
@section('content')
@php
    $statCards = [
        ['label' => 'Trips',        'icon' => 'fa-solid fa-suitcase-rolling', 'data' => $stats['trips'],       'format' => 'int'],
        ['label' => 'Attractions',  'icon' => 'fa-solid fa-mountain-sun',     'data' => $stats['attractions'], 'format' => 'int'],
        ['label' => 'Users',        'icon' => 'fa-solid fa-users',            'data' => $stats['users'],       'format' => 'int'],
    ];
@endphp
<div class="admin-stat-row">
    @foreach ($statCards as $card)
    <div class="admin-stat-card">
        <div class="admin-stat-card-top">
            <div class="admin-stat-icon"><i class="{{ $card['icon'] }}"></i></div>
            <span class="admin-stat-change {{ $card['data']['change']['up'] ? 'up' : 'down' }}">
                <i class="fa-solid fa-arrow-{{ $card['data']['change']['up'] ? 'up' : 'down' }}"></i>
                {{ $card['data']['change']['pct'] }}%
            </span>
        </div>
        <strong class="admin-stat-value">{{ $card['format'] === 'money' ? '₱' . number_format($card['data']['total'], 0) : number_format($card['data']['total']) }}</strong>
        <span class="admin-stat-label" style="text-transform:none;letter-spacing:0;font-size:12.5px;">{{ $card['label'] }}</span>
    </div>
    @endforeach
</div>

<div class="admin-dash-grid">
    {{-- Top Destinations (replaces map) --}}
    <div class="admin-panel">
        <div class="admin-panel-head">
            <h3>Top Destinations</h3>
            <a href="{{ route('admin.destinations.index') }}">View all</a>
        </div>
        @forelse ($topDestinations as $i => $dest)
        <div class="admin-rank-row">
            <div class="admin-rank-num">{{ $i + 1 }}</div>
            <div class="admin-rank-body">
                <div class="admin-rank-top">
                    <span class="admin-rank-name">{{ $dest->name }}</span>
                    <span class="admin-rank-value">{{ $dest->trip_count }} {{ Str::plural('trip', $dest->trip_count) }}</span>
                </div>
                <div class="admin-rank-bar"><div class="admin-rank-bar-fill" style="width:{{ round($dest->trip_count / $topDestinationsMax * 100) }}%;"></div></div>
            </div>
        </div>
        @empty
        <div class="admin-panel-empty">No trips booked yet.</div>
        @endforelse
    </div>

    {{-- Trips by Type donut --}}
    <div class="admin-panel">
        <div class="admin-panel-head"><h3>Trips by Type</h3></div>
        @php
            $typeColors = ['Solo' => '#C2703D', 'Group' => '#E9C9A8'];
            $typeTotal  = $tripsByType->sum();
        @endphp
        @if ($typeTotal === 0)
        <div class="admin-panel-empty">No trips yet.</div>
        @else
        @php
            $r = 46; $cx = 60; $cy = 60; $circumference = 2 * M_PI * $r; $offsetAcc = 0;
        @endphp
        <div style="display:flex;justify-content:center;">
            <svg width="120" height="120" viewBox="0 0 120 120" style="transform:rotate(-90deg);">
                <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}" fill="none" stroke="var(--admin-bg)" stroke-width="14"></circle>
                @foreach ($tripsByType as $type => $count)
                @php
                    $frac = $count / $typeTotal;
                    $segLength = $frac * $circumference;
                @endphp
                <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}" fill="none"
                        stroke="{{ $typeColors[$type] ?? '#8A7A6C' }}" stroke-width="14"
                        stroke-dasharray="{{ max(0, $segLength - 2) }} {{ $circumference - $segLength + 2 }}"
                        stroke-dashoffset="{{ -$offsetAcc }}"></circle>
                @php $offsetAcc += $segLength; @endphp
                @endforeach
            </svg>
        </div>
        <div class="admin-donut-legend">
            @foreach ($tripsByType as $type => $count)
            <div class="admin-donut-legend-item">
                <span class="admin-donut-legend-dot" style="background:{{ $typeColors[$type] ?? '#8A7A6C' }};"></span>
                {{ $type }} ({{ round($count / $typeTotal * 100) }}%)
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Trips chart + recent trips --}}
    <div class="admin-panel">
        <div class="admin-panel-head"><h3>Top 5 Selection Trends</h3></div>
        @php $tbmMax = max(1, collect($tripsByMonth)->max('value')); @endphp
        <div style="display:flex;align-items:flex-end;gap:8px;height:70px;margin-bottom:16px;">
            @foreach ($tripsByMonth as $m)
            @php $barPct = $m['value'] > 0 ? max(6, round($m['value'] / $tbmMax * 100)) : 2; @endphp
            <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;height:100%;justify-content:flex-end;">
                <div title="{{ $m['label'] }}: {{ $m['value'] }}" style="width:100%;max-width:22px;height:{{ $barPct }}%;background:var(--admin-accent);border-radius:5px 5px 2px 2px;"></div>
                <span style="font-size:10px;color:var(--admin-muted);font-weight:600;">{{ $m['label'] }}</span>
            </div>
            @endforeach
        </div>
        @forelse ($recentTrips as $trip)
        <div class="admin-trip-row">
            <div class="admin-trip-avatar">{{ strtoupper(substr($trip->user->full_name ?? $trip->user->email ?? '?', 0, 1)) }}</div>
            <div class="admin-trip-row-body">
                <div class="admin-trip-row-name">{{ $trip->user->full_name ?? $trip->user->email ?? 'Traveler' }}</div>
                <div class="admin-trip-row-sub">{{ $trip->destination }} &bull; {{ $trip->created_at->format('M j, Y') }}</div>
            </div>
            <div class="admin-trip-row-amount">₱{{ number_format($trip->budget_limit, 0) }}</div>
        </div>
        @empty
        <div class="admin-panel-empty">No trips yet.</div>
        @endforelse
    </div>
</div>

<div class="admin-dash-grid-2">
    {{-- Popular Attractions --}}
    <div class="admin-panel">
        <div class="admin-panel-head">
            <h3>Popular Attractions</h3>
            <a href="{{ route('admin.attractions.index') }}">View all</a>
        </div>
        @forelse ($popularAttractions as $attr)
        <div class="admin-rank-row">
            <div class="admin-rank-body" style="width:100%;">
                <div class="admin-rank-top">
                    <span class="admin-rank-name">{{ $attr->name }}</span>
                    <span class="admin-rank-value">{{ $attr->reviews_count }} {{ Str::plural('review', $attr->reviews_count) }}</span>
                </div>
                <div class="admin-rank-bar"><div class="admin-rank-bar-fill" style="width:{{ round($attr->reviews_count / $popularAttractionsMax * 100) }}%;"></div></div>
            </div>
        </div>
        @empty
        <div class="admin-panel-empty">No reviewed attractions yet.</div>
        @endforelse
    </div>

    {{-- Recently curated destinations --}}
    <div class="admin-panel">
        <div class="admin-panel-head">
            <h3>Recent Bookings</h3>
            <a href="{{ route('admin.destinations.index') }}">View all</a>
        </div>
        @forelse ($recentlyCurated as $dest)
        <div class="admin-curated-row">
            <div class="admin-curated-thumb" style="{{ $dest->image ? 'background-image:url(' . asset('storage/' . $dest->image) . ')' : '' }}">
                @unless ($dest->image)<i class="fa-solid fa-compass"></i>@endunless
            </div>
            <div>
                <div class="admin-curated-name">{{ $dest->name }}</div>
                <div class="admin-curated-country">{{ $dest->country ?? '—' }}</div>
            </div>
        </div>
        @empty
        <div class="admin-panel-empty">No curated destinations yet.</div>
        @endforelse
    </div>
</div>
@endsection
