@extends('layouts.app')
@section('title', $trip->destination)
@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
    <h1>{{ $trip->destination }}</h1>
    <div>
        <a href="{{ route('trips.edit', $trip) }}" class="btn btn-secondary">Edit</a>
        <a href="{{ route('trips.budget', $trip) }}" class="btn btn-primary">Manage Budget</a>
    </div>
</div>

<p>{{ $trip->start_date }} → {{ $trip->end_date }}
   &bull; {{ $trip->travel_type }}
   &bull; {{ $trip->num_travelers }} traveler(s)</p>

<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin:1.5rem 0;">
    <div class="card">
        <div class="card-body" style="text-align:center;padding:1rem;">
            <div style="font-size:1.5rem;font-weight:bold;">{{ number_format($summary['total_estimated'], 2) }}</div>
            <div style="color:var(--muted);font-size:.9rem;">Total Estimated</div>
        </div>
    </div>
    <div class="card">
        <div class="card-body" style="text-align:center;padding:1rem;">
            <div style="font-size:1.5rem;font-weight:bold;">{{ number_format($summary['total_spent'], 2) }}</div>
            <div style="color:var(--muted);font-size:.9rem;">Total Spent</div>
        </div>
    </div>
    <div class="card">
        <div class="card-body" style="text-align:center;padding:1rem;">
            <div style="font-size:1.5rem;font-weight:bold;{{ $summary['remaining'] < 0 ? 'color:red;' : '' }}">
                {{ number_format($summary['remaining'], 2) }}
            </div>
            <div style="color:var(--muted);font-size:.9rem;">Remaining</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h3>Budget Breakdown</h3>
        @if(count($summary['categories']))
            <table style="width:100%;border-collapse:collapse;">
                <thead><tr>
                    <th style="text-align:left;padding:8px;border-bottom:2px solid var(--border-light);">Category</th>
                    <th style="text-align:right;padding:8px;border-bottom:2px solid var(--border-light);">Estimated</th>
                    <th style="text-align:right;padding:8px;border-bottom:2px solid var(--border-light);">Spent</th>
                    <th style="text-align:right;padding:8px;border-bottom:2px solid var(--border-light);">Remaining</th>
                </tr></thead>
                <tbody>
                @foreach($summary['categories'] as $cat)
                    <tr>
                        <td style="padding:8px;border-bottom:1px solid var(--border-light);">{{ $cat['category'] }}</td>
                        <td style="padding:8px;border-bottom:1px solid var(--border-light);text-align:right;">{{ number_format($cat['estimated_cost'], 2) }}</td>
                        <td style="padding:8px;border-bottom:1px solid var(--border-light);text-align:right;">{{ number_format($cat['actual_spent'], 2) }}</td>
                        <td style="padding:8px;border-bottom:1px solid var(--border-light);text-align:right;{{ $cat['remaining'] < 0 ? 'color:red;' : '' }}">
                            {{ number_format($cat['remaining'], 2) }}
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <p>No budget set yet. <a href="{{ route('trips.budget', $trip) }}">Set your budget</a></p>
        @endif
    </div>
</div>
@endsection
