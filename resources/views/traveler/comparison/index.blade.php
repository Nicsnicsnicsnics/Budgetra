@extends('layouts.app')
@section('title', 'Compare Destinations')
@section('content')
<h1>Compare Destination Costs</h1>

<div class="card" style="margin-bottom:1.5rem;">
    <div class="card-body">
        <form method="GET" action="{{ route('compare.index') }}">
            <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
                @for($i = 0; $i < 3; $i++)
                    <div class="form-group" style="margin:0;min-width:160px;">
                        <label>Destination {{ $i+1 }}</label>
                        <select name="destinations[]" class="form-control">
                            <option value="">— None —</option>
                            @foreach($allDestinations as $d)
                                <option value="{{ $d }}"
                                    {{ isset($selected[$i]) && $selected[$i] === $d ? 'selected' : '' }}>{{ $d }}</option>
                            @endforeach
                        </select>
                    </div>
                @endfor
                <div class="form-group" style="margin:0;">
                    <label>Days</label>
                    <input type="number" name="days" class="form-control" value="{{ $days }}" min="1" max="30" style="width:80px;">
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Travelers</label>
                    <input type="number" name="travelers" class="form-control" value="{{ $travelers }}" min="1" max="20" style="width:80px;">
                </div>
                <button type="submit" class="btn btn-primary">Compare</button>
            </div>
        </form>
    </div>
</div>

@if(count($comparisons))
<div style="display:grid;grid-template-columns:repeat({{ count($comparisons) }},1fr);gap:1rem;">
    @foreach($comparisons as $comp)
        <div class="card">
            <div class="card-body" style="text-align:center;padding:1.5rem;">
                <h3>{{ $comp['destination'] }}</h3>
                <span style="background:#e0f0ff;color:#2980b9;padding:2px 8px;border-radius:4px;font-size:.8rem;">{{ $comp['cost_level'] }}</span>
                <div style="font-size:2rem;font-weight:800;margin:1rem 0;">{{ currency_symbol() }}{{ number_format($comp['total'], 0) }}</div>
                <p style="color:var(--muted);font-size:.9rem;">Total for {{ $days }} days, {{ $travelers }} traveler(s)</p>
                <p>{{ currency_symbol() }}{{ number_format($comp['per_day'], 0) }} / day</p>
                <p style="font-size:.85rem;color:var(--muted);">Multiplier: {{ $comp['multiplier'] }}×</p>
                <a href="{{ route('trips.create') }}"
                   class="btn btn-primary btn-sm" style="margin-top:.5rem;">Plan This Trip</a>
            </div>
        </div>
    @endforeach
</div>
@endif
@endsection
