<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #333; }
    h1 { color: #934B19; margin-bottom: 4px; }
    h3 { color: #2c3e50; margin-top: 24px; margin-bottom: 6px; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    th { background: #934B19; color: white; padding: 8px; text-align: left; }
    th:last-child, td:last-child { text-align: right; }
    td { padding: 6px 8px; border-bottom: 1px solid #eee; }
    .totals td { font-weight: bold; background: #f8f9fa; }
    .header { border-bottom: 2px solid #934B19; padding-bottom: 12px; margin-bottom: 16px; }
    .day-label { font-weight: bold; color: #934B19; margin-top: 14px; margin-bottom: 4px; }
</style>
</head>
<body>
@php
    $peso = fn($amt) => 'PHP ' . number_format($amt);
@endphp
<div class="header">
    <h1>Trip Summary &amp; Cost Estimation</h1>
    @if($isMultiCity)
        <p><strong>Trip 1:</strong> {{ $from }} to {{ $dest }} &bull; {{ \Carbon\Carbon::parse($startDate)->format('M j, Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('M j, Y') }}</p>
        <p><strong>Trip 2:</strong> {{ $dest }} to {{ $leg2Dest }} &bull; {{ $leg2StartDate ? \Carbon\Carbon::parse($leg2StartDate)->format('M j, Y') : '—' }} - {{ $leg2EndDate ? \Carbon\Carbon::parse($leg2EndDate)->format('M j, Y') : '—' }}</p>
    @else
        <p>{{ $from }} to {{ $dest }} &bull; {{ \Carbon\Carbon::parse($startDate)->format('M j, Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('M j, Y') }}</p>
    @endif
    <p>Generated: {{ now()->format('F j, Y') }}</p>
</div>

<h3>Selection Summary</h3>
@if($isMultiCity)<p><strong>Leg 1 &mdash; {{ $dest }}</strong></p>@endif
<table>
    <thead><tr><th>Category</th><th>Selection</th><th>Cost</th></tr></thead>
    <tbody>
    @forelse($picks as $pk)
        <tr>
            <td>{{ $pk['label'] }}</td>
            <td>{{ $pk['val'] }}</td>
            <td>{{ $pk['cost'] ? $peso($pk['cost']) : 'Free' }}</td>
        </tr>
    @empty
        <tr><td colspan="3">No selections made.</td></tr>
    @endforelse
    </tbody>
</table>

@if($isMultiCity)
<p><strong>Leg 2 &mdash; {{ $leg2Dest }}</strong></p>
<table>
    <thead><tr><th>Category</th><th>Selection</th><th>Cost</th></tr></thead>
    <tbody>
    @forelse($picksLeg2 as $pk)
        <tr>
            <td>{{ $pk['label'] }}</td>
            <td>{{ $pk['val'] }}</td>
            <td>{{ $pk['cost'] ? $peso($pk['cost']) : 'Free' }}</td>
        </tr>
    @empty
        <tr><td colspan="3">No selections made.</td></tr>
    @endforelse
    </tbody>
</table>
@endif

<table>
    <tbody>
    <tr>
        <td>Emergency Fund</td>
        <td>Reserved safety budget</td>
        <td>{{ $peso($emergency) }}</td>
    </tr>
    </tbody>
</table>

<h3>Itinerary</h3>
@if($isMultiCity)<p><strong>Leg 1 &mdash; {{ $dest }}</strong></p>@endif
@if(!empty($picks))
<div class="day-label">Day 1 &mdash; Arrival</div>
<table>
    <tbody>
    @foreach($picks as $pk)
        <tr><td>{{ $pk['label'] }} &middot; {{ $pk['val'] }}</td><td>{{ $pk['cost'] ? $peso($pk['cost']) : 'Free' }}</td></tr>
    @endforeach
    </tbody>
</table>
@endif
@foreach($aiDays as $i => $day)
<div class="day-label">Day {{ $i + 2 }} &mdash; {{ $day['label'] ?? '' }}</div>
<table>
    <tbody>
    @foreach($day['activities'] ?? [] as $act)
        <tr>
            <td>{{ $act['time'] ?? '' }} &middot; {{ $act['title'] ?? '' }}</td>
            <td>{{ ($act['cost'] ?? 0) ? $peso((float)$act['cost']) : 'Free' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endforeach

@if($isMultiCity)
<p><strong>Leg 2 &mdash; {{ $leg2Dest }}</strong></p>
@if(!empty($picksLeg2))
<div class="day-label">Day 1 &mdash; Arrival</div>
<table>
    <tbody>
    @foreach($picksLeg2 as $pk)
        <tr><td>{{ $pk['label'] }} &middot; {{ $pk['val'] }}</td><td>{{ $pk['cost'] ? $peso($pk['cost']) : 'Free' }}</td></tr>
    @endforeach
    </tbody>
</table>
@endif
@foreach($aiDaysLeg2 as $i => $day)
<div class="day-label">Day {{ $i + 2 }} &mdash; {{ $day['label'] ?? '' }}</div>
<table>
    <tbody>
    @foreach($day['activities'] ?? [] as $act)
        <tr>
            <td>{{ $act['time'] ?? '' }} &middot; {{ $act['title'] ?? '' }}</td>
            <td>{{ ($act['cost'] ?? 0) ? $peso((float)$act['cost']) : 'Free' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endforeach
@endif

<h3>Cost Breakdown</h3>
<table>
    <thead><tr><th>Category</th><th>Amount</th></tr></thead>
    <tbody>
        <tr><td>Transportation</td><td>{{ $peso($flightCost) }}</td></tr>
        <tr><td>Accommodation</td><td>{{ $peso($hotelCost) }}</td></tr>
        <tr><td>Food &amp; Dining</td><td>{{ $peso($venueCost) }}</td></tr>
        <tr><td>Attractions</td><td>{{ $peso($attrCost) }}</td></tr>
        <tr><td>Emergency Fund</td><td>{{ $peso($emergency) }}</td></tr>
        <tr class="totals">
            <td>TOTAL COST</td>
            <td>{{ $peso($total) }} @if($budget > 0) (Budget: {{ $peso($budget) }}) @endif</td>
        </tr>
    </tbody>
</table>

</body>
</html>
