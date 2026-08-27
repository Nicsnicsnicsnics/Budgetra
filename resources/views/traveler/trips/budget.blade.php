@extends('layouts.app')
@section('title', 'Budget Allocation')
@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
    <h1>Budget: {{ $trip->destination }}</h1>
    <a href="{{ route('trips.show', $trip) }}" class="btn btn-secondary">← Back to Trip</a>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card"><div class="card-body">
<form method="POST" action="{{ route('trips.budgetStore', $trip) }}">
    @csrf
    @foreach ($categories as $category)
        <div class="form-group">
            <label>{{ $category }}</label>
            <input type="number" step="0.01" name="estimated_cost[{{ $category }}]"
                   class="form-control" min="0"
                   value="{{ old("estimated_cost.{$category}", $budgets[$category] ?? 0) }}">
        </div>
    @endforeach
    <button type="submit" class="btn btn-primary">Save Budget</button>
</form>
</div></div>
@endsection
