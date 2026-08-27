<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: Arial, sans-serif; font-size: 12px; color: #333; }
    h1 { color: #2c3e50; margin: 0 0 6px; font-size: 20px; }
    h2 {
        color: #2c3e50; font-size: 14px; margin: 26px 0 4px;
        border-bottom: 2px solid #2c3e50; padding-bottom: 5px;
    }
    h3 { font-size: 12px; margin: 18px 0 4px; color: #2c3e50; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    th { background: #2c3e50; color: white; padding: 8px; text-align: left; }
    td { padding: 6px 8px; border-bottom: 1px solid #eee; vertical-align: top; }
    .totals td { font-weight: bold; background: #f8f9fa; border-top: 2px solid #2c3e50; }
    .header { border-bottom: 2px solid #2c3e50; padding-bottom: 12px; margin-bottom: 4px; }
    .header p { margin: 3px 0; color: #555; }
    .muted { color: #777; }
    .empty { color: #777; font-style: italic; padding: 8px 0; }
    .num { text-align: right; white-space: nowrap; }
    /* Day heading: a full-width bar so the eye can find where each day
       starts when the itinerary runs over a page break. */
    .day-bar {
        background: #f1f4f7; border-left: 4px solid #2c3e50;
        padding: 6px 10px; margin: 14px 0 0; font-weight: bold; color: #2c3e50;
    }
    .day-bar .day-date { font-weight: normal; color: #555; }
    .itin-table td { border-bottom: 1px solid #f0f0f0; }
    .col-time { width: 74px; white-space: nowrap; }
    .col-type { width: 96px; }
    .type-tag { display: inline-block; padding: 2px 7px; border-radius: 3px; font-size: 10px; font-weight: bold; }
    /* The same four categories, in the same colours, as the Itinerary screen. */
    .type-Flight         { background: #EFF6FF; color: #1D4ED8; }
    .type-Hotel          { background: #F0FDF4; color: #16A34A; }
    .type-Transportation { background: #FFF7ED; color: #D97706; }
    .type-Activity       { background: #F5EDE7; color: #8B3A10; }
    .note { color: #666; font-size: 11px; margin-top: 2px; }
    .extra { color: #8B3A10; font-size: 11px; margin-top: 2px; }
    .neg { color: #c0392b; }
    .figures td { border-bottom: 1px solid #f0f0f0; }
    .figures .label { color: #555; }
</style>
</head>
<body>
@php $cur = currency_code(); @endphp

<div class="header">
    <h1>Trip Report &mdash; {{ $trip->trip_name ?: $trip->destination }}</h1>
    <p>{{ $trip->start_date?->format('F j, Y') }} to {{ $trip->end_date?->format('F j, Y') }}
       &bull; {{ $trip->travel_type }} &bull; {{ $trip->num_travelers }} traveler(s)</p>
    <p class="muted">Generated {{ now()->format('F j, Y') }}</p>
</div>

{{-- ── Itinerary ───────────────────────────────────────────── --}}
<h2>Itinerary</h2>
@forelse ($itinerary as $date => $items)
    @php
        $day = \Carbon\Carbon::parse($date);
        // Day number relative to departure; an item dated before the trip
        // starts (an early booking, say) falls back to just the weekday.
        $dayNo = $trip->start_date
            ? (int) $trip->start_date->copy()->startOfDay()->diffInDays($day, false) + 1
            : null;
    @endphp
    <p class="day-bar">
        {{ $dayNo && $dayNo >= 1 ? 'Day '.$dayNo : $day->format('D') }}
        <span class="day-date">&mdash; {{ $day->format('l, F j, Y') }}</span>
    </p>
    <table class="itin-table">
        @foreach ($items as $item)
        <tr>
            <td class="col-time">{{ $item->start_datetime->format('g:i A') }}</td>
            <td class="col-type"><span class="type-tag type-{{ $item->type }}">{{ $item->type }}</span></td>
            <td>
                <strong>{{ $item->title }}</strong>
                @if ($item->location)<div class="note">{{ $item->location }}</div>@endif
                @if ($item->notes)<div class="note">{{ $item->notes }}</div>@endif
            </td>
        </tr>
        @endforeach
    </table>
@empty
    <p class="empty">No itinerary items have been added for this trip yet.</p>
@endforelse

{{-- ── Cost breakdown ──────────────────────────────────────── --}}
<h2>Cost Breakdown</h2>
@if (count($costRows))
<table>
    <thead><tr><th>Category</th><th>What it covers</th><th class="num">Estimated</th></tr></thead>
    <tbody>
    @foreach ($costRows as $row)
        <tr>
            <td><strong>{{ $row['label'] }}</strong></td>
            <td>
                {{ $row['detail'] ?: '—' }}
                @if ($row['extra'] > 0)
                <div class="extra">+{{ $row['extra'] }} more from suggested itinerary</div>
                @endif
            </td>
            <td class="num">{{ $cur }} {{ number_format($row['cost'], 2) }}</td>
        </tr>
    @endforeach
        <tr class="totals">
            <td colspan="2">TOTAL ESTIMATED COST</td>
            <td class="num">{{ $cur }} {{ number_format($totals['estimated'], 2) }}</td>
        </tr>
    </tbody>
</table>
@else
<p class="empty">No cost estimate was saved for this trip.</p>
@endif

@if (count($summary['categories']))
{{-- Only rendered when a trip actually carries allocated budget rows;
     trips planned through the wizard carry the estimate above instead. --}}
<h3>Budget Allocation</h3>
<table>
    <thead><tr><th>Category</th><th class="num">Estimated</th><th class="num">Spent</th><th class="num">Remaining</th></tr></thead>
    <tbody>
    @foreach($summary['categories'] as $cat)
        <tr>
            <td>{{ $cat['category'] }}</td>
            <td class="num">{{ $cur }} {{ number_format($cat['estimated_cost'], 2) }}</td>
            <td class="num">{{ $cur }} {{ number_format($cat['actual_spent'], 2) }}</td>
            <td class="num {{ $cat['remaining'] < 0 ? 'neg' : '' }}">{{ $cur }} {{ number_format($cat['remaining'], 2) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endif

<h3>Where the trip stands</h3>
<table class="figures">
    <tr>
        <td class="label">Budget limit</td>
        <td class="num">{{ $cur }} {{ number_format($totals['budget_limit'], 2) }}</td>
    </tr>
    <tr>
        <td class="label">Estimated cost</td>
        <td class="num">{{ $cur }} {{ number_format($totals['estimated'], 2) }}</td>
    </tr>
    <tr>
        <td class="label">Actually spent so far</td>
        <td class="num">{{ $cur }} {{ number_format($totals['spent'], 2) }}</td>
    </tr>
    <tr class="totals">
        <td>Remaining against the estimate</td>
        <td class="num {{ $totals['remaining'] < 0 ? 'neg' : '' }}">{{ $cur }} {{ number_format($totals['remaining'], 2) }}</td>
    </tr>
    @if ($totals['per_person'] !== null)
    <tr>
        <td class="label">Per person &mdash; split between {{ $totals['heads'] }} travelers</td>
        <td class="num">{{ $cur }} {{ number_format($totals['per_person'], 2) }}</td>
    </tr>
    @endif
</table>

<h3>Recorded Expenses</h3>
<table>
    <thead><tr><th>Date</th><th>Category</th><th>Description</th><th class="num">Amount</th></tr></thead>
    <tbody>
    @forelse($expenses as $exp)
        <tr>
            <td>{{ $exp->expense_date?->format('M j, Y') }}</td>
            <td>{{ $exp->category }}</td>
            <td>{{ $exp->description ?: '—' }}</td>
            <td class="num">{{ $cur }} {{ number_format($exp->amount, 2) }}</td>
        </tr>
    @empty
        <tr><td colspan="4" class="empty">No expenses recorded.</td></tr>
    @endforelse
    </tbody>
</table>
</body>
</html>
