@extends('layouts.admin')

@section('content')
@php
    $statCards = [
        ['label' => 'Trips created', 'icon' => 'fa-solid fa-suitcase-rolling', 'data' => $stats['trips'], 'format' => 'int'],
        ['label' => 'Planned budget', 'icon' => 'fa-solid fa-peso-sign', 'data' => ['total' => $budget['total']['value'], 'change' => $budget['total']['change']], 'format' => 'money'],
        ['label' => 'New travelers', 'icon' => 'fa-solid fa-users', 'data' => $stats['users'], 'format' => 'int'],
        ['label' => 'Attractions added', 'icon' => 'fa-solid fa-mountain-sun', 'data' => $stats['attractions'], 'format' => 'int'],
    ];
    $periodLabel = $rangeLabel;
@endphp

<div class="admin-dashboard-head">
    <div>
        <h1>Dashboard</h1>
        <p>Overview for {{ $from->format('M j, Y') }} to {{ now()->format('M j, Y') }}.</p>
    </div>
</div>

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
        <strong class="admin-stat-value">{{ $card['format'] === 'money' ? 'PHP ' . number_format($card['data']['total'], 0) : number_format($card['data']['total']) }}</strong>
        <span class="admin-stat-label" style="text-transform:none;letter-spacing:0;font-size:12.5px;">{{ $card['label'] }}</span>
    </div>
    @endforeach
</div>

{{-- Lifecycle snapshot across every trip on the platform. Tiles will link
     to a filtered admin trip list (?status=…) once that page exists. --}}
<div class="admin-panel admin-trip-status">
    <div class="admin-panel-head"><h3>Trip status overview</h3><span class="admin-panel-caption">All time</span></div>
    <div class="admin-trip-status-grid">
        @foreach ($tripStatus as $status)
        <div class="admin-trip-status-tile">
            <div class="admin-trip-status-top">
                <i class="{{ $status['icon'] }}" style="color:{{ $status['color'] }};"></i>
                <strong class="admin-trip-status-count">{{ number_format($status['count']) }}</strong>
            </div>
            <span class="admin-trip-status-label">{{ $status['label'] }}</span>
        </div>
        @endforeach
    </div>
</div>

<div class="admin-dash-grid">
    <div class="admin-panel">
        <div class="admin-panel-head"><h3>Top destinations</h3><a href="{{ route('admin.destinations.index') }}">Manage</a></div>
        @forelse ($topDestinations as $i => $dest)
        <div class="admin-rank-row">
            <div class="admin-rank-num">{{ $i + 1 }}</div>
            <div class="admin-rank-body">
                <div class="admin-rank-top"><span class="admin-rank-name">{{ $dest->name }}</span><span class="admin-rank-value">{{ $dest->trip_count }} {{ Str::plural('trip', $dest->trip_count) }}</span></div>
                <div class="admin-rank-bar"><div class="admin-rank-bar-fill" style="width:{{ round($dest->trip_count / $topDestinationsMax * 100) }}%;"></div></div>
            </div>
        </div>
        @empty
        <div class="admin-panel-empty">No trips were created during this period.</div>
        @endforelse
    </div>

    <div class="admin-panel">
        <div class="admin-panel-head"><h3>Trips by type</h3><span class="admin-panel-caption">{{ $periodLabel }}</span></div>
        @php $typeColors = ['Solo' => '#C2703D', 'Group' => '#E9C9A8']; $typeTotal = $tripsByType->sum(); @endphp
        @if ($typeTotal === 0)
        <div class="admin-panel-empty">No trip-type data for this period.</div>
        @else
        @php $radius = 46; $circumference = 2 * M_PI * $radius; $offset = 0; @endphp
        <div style="display:flex;justify-content:center;">
            <svg width="120" height="120" viewBox="0 0 120 120" style="transform:rotate(-90deg);" role="img" aria-label="Trips by type">
                <circle cx="60" cy="60" r="{{ $radius }}" fill="none" stroke="var(--admin-bg)" stroke-width="14"></circle>
                @foreach ($tripsByType as $type => $count)
                @php $segment = $count / $typeTotal * $circumference; @endphp
                <circle cx="60" cy="60" r="{{ $radius }}" fill="none" stroke="{{ $typeColors[$type] ?? '#8A7A6C' }}" stroke-width="14" stroke-dasharray="{{ max(0, $segment - 2) }} {{ $circumference - $segment + 2 }}" stroke-dashoffset="{{ -$offset }}"></circle>
                @php $offset += $segment; @endphp
                @endforeach
            </svg>
        </div>
        <div class="admin-donut-legend">
            @foreach ($tripsByType as $type => $count)
            <div class="admin-donut-legend-item"><span class="admin-donut-legend-dot" style="background:{{ $typeColors[$type] ?? '#8A7A6C' }};"></span>{{ $type }}: {{ $count }} ({{ round($count / $typeTotal * 100) }}%)</div>
            @endforeach
        </div>
        @endif
    </div>

    <div class="admin-panel">
        <div class="admin-panel-head"><h3>Trips created over time</h3><span class="admin-panel-caption">{{ $periodLabel }}</span></div>
        @php $trendMax = max(1, collect($trendBuckets)->max('value')); @endphp
        <div class="admin-trend-chart">
            @foreach ($trendBuckets as $bucket)
            @php $barPct = $bucket['value'] > 0 ? max(6, round($bucket['value'] / $trendMax * 100)) : 2; @endphp
            <div class="admin-trend-column">
                <div class="admin-trend-bar" title="{{ $bucket['label'] }}: {{ $bucket['value'] }} trips" style="height:{{ $barPct }}%;"></div>
                <span>{{ $bucket['label'] }}</span>
            </div>
            @endforeach
        </div>
        <div class="admin-panel-subhead">Recent trips</div>
        @forelse ($recentTrips as $trip)
        <div class="admin-trip-row">
            <div class="admin-trip-avatar">{{ strtoupper(substr($trip->user->full_name ?? $trip->user->email ?? '?', 0, 1)) }}</div>
            <div class="admin-trip-row-body"><div class="admin-trip-row-name">{{ $trip->user->full_name ?? $trip->user->email ?? 'Traveler' }}</div><div class="admin-trip-row-sub">{{ $trip->destination }} &bull; {{ $trip->created_at->format('M j, Y') }}</div></div>
            <div class="admin-trip-row-amount">PHP {{ number_format($trip->budget_limit, 0) }}</div>
        </div>
        @empty
        <div class="admin-panel-empty">No recent trips for this period.</div>
        @endforelse
    </div>
</div>

<div class="admin-dash-grid-2">
    <div class="admin-panel">
        <div class="admin-panel-head"><h3>Popular attractions</h3><a href="{{ route('admin.attractions.index') }}">Manage</a></div>
        @forelse ($popularAttractions as $attr)
        <div class="admin-rank-row"><div class="admin-rank-body" style="width:100%;"><div class="admin-rank-top"><span class="admin-rank-name">{{ $attr->name }}</span><span class="admin-rank-value">{{ $attr->reviews_count }} {{ Str::plural('review', $attr->reviews_count) }}</span></div><div class="admin-rank-bar"><div class="admin-rank-bar-fill" style="width:{{ round($attr->reviews_count / $popularAttractionsMax * 100) }}%;"></div></div></div></div>
        @empty
        <div class="admin-panel-empty">No active reviews were added during this period.</div>
        @endforelse
    </div>

    <div class="admin-panel">
        <div class="admin-panel-head"><h3>Recently updated destinations</h3><a href="{{ route('admin.destinations.index') }}">Manage</a></div>
        @forelse ($recentlyCurated as $dest)
        <div class="admin-curated-row"><div class="admin-curated-thumb" style="{{ $dest->image ? 'background-image:url(' . asset('storage/' . $dest->image) . ')' : '' }}">@unless ($dest->image)<i class="fa-solid fa-compass"></i>@endunless</div><div><div class="admin-curated-name">{{ $dest->name }}</div><div class="admin-curated-country">{{ $dest->country ?? 'No country set' }}</div></div></div>
        @empty
        <div class="admin-panel-empty">No destinations were updated during this period.</div>
        @endforelse
    </div>
</div>
@endsection
