@extends('layouts.app')
@section('title', 'Cost Estimate')
@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
    <h1>Cost Estimate: {{ $trip->destination }}</h1>
    <a href="{{ route('trips.show', $trip) }}" class="btn btn-secondary">← Back to Trip</a>
</div>
<p>{{ $days }} day(s) &bull; {{ $trip->num_travelers ?? 1 }} traveler(s) &bull; {{ $trip->travel_type }}</p>

<div class="card" style="margin-bottom:1.5rem;">
    <div class="card-body">
        <h3>Estimated Budget Breakdown</h3>
        <table style="width:100%;border-collapse:collapse;">
            <thead><tr>
                <th style="text-align:left;padding:8px;border-bottom:2px solid var(--border-light);">Category</th>
                <th style="text-align:right;padding:8px;border-bottom:2px solid var(--border-light);">Estimated ({{ currency_symbol() }})</th>
            </tr></thead>
            <tbody>
            @foreach($categories as $cat => $amount)
                <tr>
                    <td style="padding:8px;border-bottom:1px solid var(--border-light);">{{ $cat }}</td>
                    <td style="padding:8px;border-bottom:1px solid var(--border-light);text-align:right;">{{ number_format($amount, 2) }}</td>
                </tr>
            @endforeach
            <tr style="font-weight:800;">
                <td style="padding:8px;">TOTAL</td>
                <td style="padding:8px;text-align:right;">{{ currency_symbol() }}{{ number_format($total, 2) }}</td>
            </tr>
            </tbody>
        </table>
        <form method="POST" action="{{ route('trips.budgetStore', $trip) }}" style="margin-top:1rem;">
            @csrf
            @foreach($categories as $cat => $amount)
                <input type="hidden" name="estimated_cost[{{ $cat }}]" value="{{ $amount }}">
            @endforeach
            <button type="submit" class="btn btn-primary">Apply Estimates to Budget</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h3>Suggested Activities via Klook <span style="font-size:.8rem;font-weight:400;color:var(--muted);">(estimated prices)</span></h3>
        @foreach($activities as $activity)
            <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border-light);">
                <div>{{ $activity['name'] }} <span style="color:#f5a623;">⭐ {{ $activity['rating'] }}</span></div>
                <div>{{ currency_symbol() }}{{ number_format($activity['price'], 0) }}</div>
            </div>
        @endforeach
    </div>
</div>
@endsection
